<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Create a customer account and issue a token.
     *
     * @return array{0: User, 1: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // hashed automatically by the 'hashed' cast
        ]);

        return [$user, $user->createToken('api')->plainTextToken];
    }

    /**
     * Check credentials and issue a token.
     *
     * @return array{0: User, 1: string}
     */
    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            abort(401, 'Invalid email or password.');
        }

        return [$user, $user->createToken('api')->plainTextToken];
    }

    /**
     * Revoke only the token used for this request.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
