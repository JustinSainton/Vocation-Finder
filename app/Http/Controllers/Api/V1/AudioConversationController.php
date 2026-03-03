<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\Agents\ConversationAgent;
use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeAssessmentJob;
use App\Models\Answer;
use App\Models\Assessment;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\Question;
use App\Services\Ai\ConversationModelSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;
use Throwable;

class AudioConversationController extends Controller
{
    public function __construct(
        protected ConversationModelSelector $modelSelector,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assessment_id' => 'required|uuid|exists:assessments,id',
        ]);

        $assessment = Assessment::findOrFail($validated['assessment_id']);

        $session = ConversationSession::create([
            'assessment_id' => $assessment->id,
            'status' => 'active',
            'current_question_index' => 0,
        ]);

        return response()->json([
            'session_id' => $session->id,
            'current_question_index' => 0,
            'status' => 'active',
        ], 201);
    }

    public function uploadAudio(Request $request, ConversationSession $session): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|mimes:aac,m4a,mp3,wav|max:10240',
        ]);

        $path = $request->file('audio')->store('conversation-audio', 's3');

        $transcriptionResponse = Transcription::fromUpload($request->file('audio'))
            ->language('en')
            ->generate();

        return response()->json([
            'audio_path' => $path,
            'transcript' => $transcriptionResponse->text,
            'status' => 'transcribed',
        ]);
    }

    public function processTurn(Request $request, ConversationSession $session): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'audio_storage_path' => 'nullable|string',
            'duration_seconds' => 'nullable|integer',
        ]);

        $nextSortOrder = $session->turns()->max('sort_order') + 1;

        // Save user turn
        ConversationTurn::create([
            'conversation_session_id' => $session->id,
            'role' => 'user',
            'content' => $validated['content'],
            'audio_storage_path' => $validated['audio_storage_path'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'sort_order' => $nextSortOrder,
        ]);

        $questions = Question::orderBy('sort_order')->get();
        $currentQuestion = $questions->get($session->current_question_index);

        if (! $currentQuestion) {
            return response()->json([
                'error' => 'No more questions available.',
            ], 422);
        }

        // Gather previous turns for the current question to provide conversation context
        $previousTurns = $session->turns()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ConversationTurn $turn) => [
                'role' => $turn->role,
                'content' => $turn->content,
            ])
            ->toArray();

        // Remove the latest user turn from previousTurns since it is passed separately
        $latestTurn = array_pop($previousTurns);

        $agent = new ConversationAgent(
            questionText: $currentQuestion->question_text,
            userResponse: $validated['content'],
            followUpPrompts: $currentQuestion->follow_up_prompts ?? [],
            previousTurns: $previousTurns,
        );

        $modelSelection = $this->modelSelector->forSessionId($session->id);
        $response = $agent->prompt(
            $agent->buildPrompt(),
            provider: $modelSelection['provider'],
            model: $modelSelection['model'],
        );
        $result = $response->structured;

        $this->storeConversationExperimentMetadata($session, $modelSelection);
        Log::info('conversation_turn_processed', [
            'session_id' => $session->id,
            'assessment_id' => $session->assessment_id,
            'variant' => $modelSelection['variant'],
            'provider' => $modelSelection['provider'],
            'model' => $modelSelection['model'],
            'is_sufficient' => $result['is_sufficient'] ?? null,
        ]);

        if ($result['is_sufficient']) {
            // Save the synthesized answer for this question
            Answer::create([
                'assessment_id' => $session->assessment_id,
                'question_id' => $currentQuestion->id,
                'response_text' => $result['synthesized_answer'],
                'audio_storage_path' => $validated['audio_storage_path'] ?? null,
                'duration_seconds' => $validated['duration_seconds'] ?? null,
            ]);

            // Advance to the next question
            $nextIndex = $session->current_question_index + 1;
            $session->update(['current_question_index' => $nextIndex]);

            $nextQuestion = $questions->get($nextIndex);
            $isComplete = $nextQuestion === null;

            $aiMessage = $nextQuestion
                ? $nextQuestion->conversation_prompt ?? $nextQuestion->question_text
                : 'Thank you for completing all the questions. Your responses have been recorded.';

            ConversationTurn::create([
                'conversation_session_id' => $session->id,
                'role' => 'assistant',
                'content' => $aiMessage,
                'sort_order' => $nextSortOrder + 1,
            ]);

            return response()->json([
                'response' => $aiMessage,
                'current_question_index' => $nextIndex,
                'is_follow_up' => false,
                'is_complete' => $isComplete,
                'reasoning' => $result['reasoning'],
                'model_variant' => $modelSelection['variant'],
            ]);
        }

        // Not sufficient — ask a follow-up
        $followUp = $result['follow_up_question'];

        ConversationTurn::create([
            'conversation_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $followUp,
            'sort_order' => $nextSortOrder + 1,
        ]);

        return response()->json([
            'response' => $followUp,
            'current_question_index' => $session->current_question_index,
            'is_follow_up' => true,
            'is_complete' => false,
            'reasoning' => $result['reasoning'],
            'model_variant' => $modelSelection['variant'],
        ]);
    }

    public function synthesizeSpeech(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:2500',
        ]);

        try {
            $disk = config('vocation.audio.tts_audio_disk', 's3');
            $expiresAt = now()->addMinutes((int) config('vocation.audio.tts_audio_ttl_minutes', 15));

            $audio = Audio::of($validated['text'])
                ->voice(config('vocation.audio.tts_voice', 'nova'))
                ->instructions(config('vocation.audio.tts_instructions', 'Warm, natural, and conversational.'))
                ->generate(
                    config('vocation.audio.tts_provider', 'openai'),
                    config('vocation.audio.tts_model', 'gpt-4o-mini-tts'),
                );

            $path = $audio->store('conversation-tts', $disk);

            if (! is_string($path)) {
                throw new \RuntimeException('Audio storage failed.');
            }

            $filesystem = Storage::disk($disk);

            try {
                $audioUrl = $filesystem->temporaryUrl($path, $expiresAt);
            } catch (Throwable) {
                $audioUrl = $filesystem->url($path);
            }

            return response()->json([
                'audio_url' => $audioUrl,
                'mime_type' => $audio->mimeType() ?? 'audio/mpeg',
                'provider' => config('vocation.audio.tts_provider', 'openai'),
                'model' => config('vocation.audio.tts_model', 'gpt-4o-mini-tts'),
                'voice' => config('vocation.audio.tts_voice', 'nova'),
            ]);
        } catch (Throwable $e) {
            Log::error('conversation_tts_failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to synthesize voice right now.',
            ], 502);
        }
    }

    public function complete(Request $request, ConversationSession $session): JsonResponse
    {
        $session->update(['status' => 'completed']);

        $assessment = $session->assessment;
        $assessment->update([
            'status' => 'analyzing',
            'completed_at' => now(),
        ]);

        AnalyzeAssessmentJob::dispatch($assessment);

        return response()->json(['status' => 'analyzing']);
    }

    protected function storeConversationExperimentMetadata(ConversationSession $session, array $modelSelection): void
    {
        $assessment = $session->assessment;
        $metadata = $assessment->metadata ?? [];

        if (($metadata['conversation_model_experiment']['variant'] ?? null) !== null) {
            return;
        }

        $metadata['conversation_model_experiment'] = [
            'variant' => $modelSelection['variant'],
            'provider' => $modelSelection['provider'],
            'model' => $modelSelection['model'],
            'rollout_percentage' => $modelSelection['rollout_percentage'],
            'enabled' => $modelSelection['experiment_enabled'],
            'assigned_at' => now()->toIso8601String(),
            'assignment_unit' => 'conversation_session_id',
            'assignment_key' => $session->id,
        ];

        $assessment->update(['metadata' => $metadata]);
    }
}
