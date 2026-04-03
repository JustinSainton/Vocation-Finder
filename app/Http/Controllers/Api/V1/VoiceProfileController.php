<?php

namespace App\Http\Controllers\Api\V1;

use App\Ai\Agents\VoiceAnalyzerAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->voiceProfile;

        return response()->json(['data' => $profile]);
    }

    public function submitSamples(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'samples' => ['required', 'array', 'min:2', 'max:10'],
            'samples.*' => ['required', 'string', 'min:50', 'max:5000'],
        ]);

        $agent = new VoiceAnalyzerAgent($validated['samples']);
        $response = $agent->prompt($agent->buildPrompt());
        $analysis = $response->json();

        $profile = $request->user()->voiceProfile;

        $data = [
            'style_analysis' => $analysis,
            'banned_phrases' => $analysis['banned_phrases'] ?? [],
            'preferred_verbs' => $analysis['preferred_verbs'] ?? [],
            'avg_sentence_length' => $analysis['avg_sentence_length'] ?? null,
            'tone_register' => $analysis['tone_register'] ?? null,
            'sample_count' => count($validated['samples']),
            'writing_samples' => $validated['samples'],
        ];

        if ($profile) {
            $profile->update($data);
        } else {
            $profile = $request->user()->voiceProfile()->create($data);
        }

        return response()->json(['data' => $profile]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'banned_phrases' => ['nullable', 'array'],
            'banned_phrases.*' => ['string'],
        ]);

        $profile = $request->user()->voiceProfile;

        if (! $profile) {
            return response()->json(['message' => 'No voice profile found. Submit writing samples first.'], 404);
        }

        $profile->update($validated);

        return response()->json(['data' => $profile]);
    }
}
