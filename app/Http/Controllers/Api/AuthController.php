<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Vida de un token de dispositivo. */
    private const TOKEN_DAYS = 180;

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }

        $token = $user->createToken($data['device_name'], ['*'], now()->addDays(self::TOKEN_DAYS));

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => $this->user($user),
        ], 201);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $this->user($request->user())]);
    }

    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function user(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }
}
