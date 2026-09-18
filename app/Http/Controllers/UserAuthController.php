<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\UserProfile;
use App\Services\AuditLogService;
use App\Services\UserTokenService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    public function __construct(private readonly AuditLogService $logs)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = UserProfile::where('email', $credentials['email'])->first();

        if (! $user || ! $user->verifyPassword($credentials['password'])) {
            throw ValidationException::withMessages([
                'email' => ['Неверный e-mail или пароль'],
            ]);
        }

        $token = app(UserTokenService::class)->issue($user, 'web');

        $this->logs->record(
            AuditAction::AuthLogin,
            'target_user_id',
            (int) $user->id,
            (int) $user->id,
            ['method' => 'password'],
            (int) $user->id,
        );

        return response()->json([
            'token' => $token,
            'user'  => new UserProfileResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $header = (string) $request->header('Authorization', '');
        $plain = str_starts_with($header, 'Bearer ')
            ? trim(substr($header, 7))
            : '';

        $token = $plain !== '' ? app(UserTokenService::class)->resolve($plain) : null;
        if ($token !== null) {
            app(UserTokenService::class)->revoke($token);

            $this->logs->record(
                AuditAction::AuthLogout,
                'target_user_id',
                (int) $token->user_id,
                (int) $token->user_id,
                [],
                (int) $token->user_id,
            );
        }

        CurrentUser::set(null);

        return response()->json(null, 204);
    }
}