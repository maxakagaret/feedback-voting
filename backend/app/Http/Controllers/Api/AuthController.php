<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Sanctum personal access tokens: the SPA stores the token and sends it as a Bearer header. */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        return response()->json([
            'token' => $user->createToken('spa', [$user->role->value])->plainTextToken,
            'user' => $this->present($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request->user())]);
    }

    /** Demo convenience: the login screen offers these synthetic accounts. */
    public function demoAccounts(): JsonResponse
    {
        return response()->json(['data' => User::orderByDesc('role')->orderBy('id')->limit(4)->get()
            ->map(fn (User $u) => ['email' => $u->email, 'name' => $u->name, 'role' => $u->role->value])]);
    }

    private function present(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->value];
    }
}
