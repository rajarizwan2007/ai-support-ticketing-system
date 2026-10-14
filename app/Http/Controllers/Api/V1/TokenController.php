<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Unauthenticated;

/**
 * API tokens (Laravel Sanctum). Users are tenant-scoped, so the login only
 * finds users of the organization in the subdomain, and a token used on
 * another organization's subdomain finds no user (401).
 */
#[Group('Authentication')]
class TokenController extends Controller
{
    /**
     * Create a token
     *
     * Log in with email and password and get a token for the Authorization header.
     */
    #[Unauthenticated]
    #[ScribeResponse(['token' => '1|aBcDeFgHiJkLmNoPqRsTuVwXyZ'], 201)]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return response()->json(['token' => $user->createToken($validated['device_name'])->plainTextToken], 201);
    }

    /**
     * Revoke the current token
     */
    #[ScribeResponse(status: 204)]
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
