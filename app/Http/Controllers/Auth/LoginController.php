<?php
namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\MfaVerifyRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->login)
            ->orWhere('nip', $request->login)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            if ($user) {
                $this->handleFailedAttempt($user);
            }
            $this->audit->log(AuditAction::LOGIN_FAILED, 'User', null, [], ['login' => $request->login]);
            return response()->json(['message' => 'Kredensial tidak valid.'], 401);
        }

        if ($user->isLocked()) {
            return response()->json([
                'message' => 'Akun dikunci. Coba lagi setelah ' . $user->locked_until->diffForHumans(),
            ], 423);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Akun tidak aktif.'], 403);
        }

        // Reset failed attempts on success
        $user->update(['failed_login_count' => 0, 'locked_until' => null]);

        if ($user->mfa_enabled) {
            // Return MFA challenge token (signed, short-lived)
            $mfaToken = encrypt(['user_id' => $user->id, 'expires_at' => now()->addMinutes(5)->timestamp]);
            return response()->json([
                'requires_mfa' => true,
                'mfa_token'    => $mfaToken,
            ]);
        }

        return $this->issueToken($user, $request);
    }

    public function mfaVerify(MfaVerifyRequest $request): JsonResponse
    {
        try {
            $payload = decrypt($request->mfa_token);
        } catch (\Exception) {
            return response()->json(['message' => 'Token MFA tidak valid.'], 401);
        }

        if ($payload['expires_at'] < now()->timestamp) {
            return response()->json(['message' => 'Token MFA kadaluarsa.'], 401);
        }

        $user = User::findOrFail($payload['user_id']);

        if ($user->isLocked()) {
            return response()->json(['message' => 'Akun dikunci.'], 423);
        }

        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);
        $valid = $google2fa->verifyKey($user->mfa_secret, $request->otp_code);

        if (! $valid) {
            // Try backup code
            $valid = $this->verifyBackupCode($user, $request->otp_code);
            if (! $valid) {
                $this->handleFailedAttempt($user);
                $this->audit->log(AuditAction::MFA_FAILED, 'User', $user->id);
                return response()->json(['message' => 'Kode OTP tidak valid.'], 401);
            }
        }

        $user->update(['failed_login_count' => 0, 'locked_until' => null]);
        $this->audit->log(AuditAction::MFA_VERIFIED, 'User', $user->id);

        return $this->issueToken($user, $request);
    }

    public function logout(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()?->delete();
        $this->audit->log(AuditAction::LOGOUT, 'User', $user->id);
        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function me(\Illuminate\Http\Request $request): JsonResponse
    {
        return response()->json([
            'data' => new \App\Http\Resources\UserResource($request->user()->load('organization')),
        ]);
    }

    private function issueToken(User $user, $request): JsonResponse
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);
        $this->audit->log(AuditAction::LOGIN, 'User', $user->id);

        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json([
            'token' => $token,
            'user'  => new \App\Http\Resources\UserResource($user->load('organization')),
        ]);
    }

    private function handleFailedAttempt(User $user): void
    {
        $attempts = $user->failed_login_count + 1;
        $maxAttempts = (int) config('lockout.max_attempts', 5);
        $lockDuration = (int) config('lockout.duration', 300);

        $user->update([
            'failed_login_count' => $attempts,
            'locked_until'       => $attempts >= $maxAttempts ? now()->addSeconds($lockDuration) : null,
        ]);

        if ($attempts >= $maxAttempts) {
            $this->audit->log(AuditAction::ACCOUNT_LOCKED, 'User', $user->id);
        }
    }

    private function verifyBackupCode(User $user, string $code): bool
    {
        $backupCode = $user->mfaBackupCodes()
            ->whereNull('used_at')
            ->get()
            ->first(fn ($bc) => \Illuminate\Support\Facades\Hash::check($code, $bc->code_hash));

        if ($backupCode) {
            $backupCode->update(['used_at' => now()]);
            return true;
        }

        return false;
    }
}
