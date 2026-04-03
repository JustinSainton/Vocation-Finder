<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\GuestUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, GuestUpgradeService $upgradeService): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $upgradeService->upgrade($user, $request->guest_token);

        return $this->respondWithToken($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        return $this->respondWithToken($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function socialGoogle(Request $request, GuestUpgradeService $upgradeService): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'guest_token' => ['nullable', 'string'],
        ]);

        $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->id_token);

        $user = User::where('provider', 'google')
            ->where('provider_id', $googleUser->getId())
            ->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                ]);
            }
        }

        $upgradeService->upgrade($user, $request->guest_token);

        return $this->respondWithToken($user);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => __($status)]);
    }

    public function me(Request $request, FeatureFlagService $flags): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
            'features' => $flags->allFlags(),
        ]);
    }

    private function respondWithToken(User $user, int $status = 200): JsonResponse
    {
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
        ], $status);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'organizations' => $user->organizations()
                ->withPivot('role')
                ->get()
                ->map(fn ($org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'role' => $org->pivot->role,
                ]),
        ];
    }
}
