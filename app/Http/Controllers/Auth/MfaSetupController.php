<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MfaBackupCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MfaSetupController extends Controller
{
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);

        $secret = $google2fa->generateSecretKey();
        $qrUrl  = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $user->update(['mfa_secret' => encrypt($secret)]);

        return response()->json([
            'secret' => $secret,
            'qr_url' => $qrUrl,
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $request->validate(['otp_code' => ['required', 'string', 'size:6']]);

        $user = $request->user();
        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);
        $secret = decrypt($user->mfa_secret);

        if (! $google2fa->verifyKey($secret, $request->otp_code)) {
            return response()->json(['message' => 'Kode OTP tidak valid.'], 422);
        }

        // Generate 10 backup codes
        $codes = collect(range(1, 10))->map(fn () => Str::random(10));
        $user->mfaBackupCodes()->delete();
        $codes->each(fn ($code) => MfaBackupCode::create([
            'user_id'   => $user->id,
            'code_hash' => Hash::make($code),
        ]));

        $user->update(['mfa_enabled' => true]);

        return response()->json([
            'message'      => 'MFA berhasil diaktifkan.',
            'backup_codes' => $codes->all(),
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->user()->update(['mfa_enabled' => false, 'mfa_secret' => null]);
        $request->user()->mfaBackupCodes()->delete();
        return response()->json(['message' => 'MFA dinonaktifkan.']);
    }
}
