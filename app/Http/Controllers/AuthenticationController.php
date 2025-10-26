<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthenticationController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $name = sprintf('api-%s', now()->format('YmdHis'));
        $plainTextToken = $user->createToken($name)->plainTextToken;

        return response()->json($this->tokenResponse($plainTextToken));
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        $user = $request->user('api');

        if ($user) {
            $current = $user->currentAccessToken();
            $plainTextToken = $user->createToken('api-refresh-'.now()->format('YmdHis'));
            if ($current) {
                $current->delete();
            }

            return response()->json($this->tokenResponse($plainTextToken->plainTextToken));
        }

        $data = $request->validated();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        if ($email && $password) {
            $user = User::where('email', (string) $email)->first();
            if ($user && Hash::check((string) $password, (string) $user->password)) {
                $plainTextToken = $user->createToken('api-refresh-'.now()->format('YmdHis'))->plainTextToken;

                return response()->json($this->tokenResponse($plainTextToken));
            }
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * @return array{token: string, token_type: string, expires_at: string|null}
     */
    private function tokenResponse(string $plainTextToken): array
    {
        $expirationMinutes = config('sanctum.expiration');
        $expiresAt = $expirationMinutes ? now()->addMinutes((int) $expirationMinutes) : null;

        return [
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
        ];
    }
}
