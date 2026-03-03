<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AudioConversationController extends Controller
{
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

        // TODO: Dispatch transcription job
        // TranscribeAudioJob::dispatch($session, $path);

        return response()->json([
            'audio_path' => $path,
            'status' => 'transcribing',
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

        // TODO: Use Laravel AI SDK Agent to determine follow-up or advance
        // For now, return a placeholder response
        $aiResponse = 'Thank you for sharing that. Let me ask you the next question.';

        ConversationTurn::create([
            'conversation_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $aiResponse,
            'sort_order' => $nextSortOrder + 1,
        ]);

        return response()->json([
            'response' => $aiResponse,
            'current_question_index' => $session->current_question_index,
            'is_follow_up' => false,
        ]);
    }

    public function complete(Request $request, ConversationSession $session): JsonResponse
    {
        $session->update(['status' => 'completed']);

        $assessment = $session->assessment;
        $assessment->update([
            'status' => 'analyzing',
            'completed_at' => now(),
        ]);

        // TODO: Dispatch AI analysis job
        // AnalyzeAssessmentJob::dispatch($assessment);

        return response()->json(['status' => 'analyzing']);
    }
}
