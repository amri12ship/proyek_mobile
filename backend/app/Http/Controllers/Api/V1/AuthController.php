<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AttendanceException;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use App\Resources\Api\V1\UserResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->failed('auth.login', $user, null, 'Kredensial salah untuk '.$credentials['email']);

            throw AttendanceException::make('Email atau password salah.', 'INVALID_CREDENTIALS', 401);
        }

        if (! $user->is_active) {
            $this->audit->failed('auth.login', $user, null, 'Akun nonaktif');

            throw AttendanceException::make(
                'Akun Anda sedang dinonaktifkan. Hubungi administrator.',
                'ACCOUNT_INACTIVE',
                403,
            );
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $deviceName = $credentials['device_name'] ?? $request->userAgent() ?? 'mobile';

        $token = $user->createToken($deviceName, ['mobile']);

        $this->audit->success('auth.login', $user, $user->employee, 'Login dari '.$deviceName);

        return response()->json([
            'message' => 'Login berhasil.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user->load('employee.department', 'employee.position')),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($this->user($request)->load('employee.department', 'employee.position')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $name = $token instanceof PersonalAccessToken ? $token->name : null;

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->audit->success('auth.logout', $this->user($request), null, 'Token dicabut: '.Str::limit((string) $name, 40));

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
