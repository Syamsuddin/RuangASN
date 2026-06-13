<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Resources\UserResource;
use App\Models\MfaBackupCode;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): Response
    {
        $user = $request->user()->load(['organization', 'roles', 'notificationPreference']);

        return Inertia::render('Settings/Index', [
            'user'                   => new UserResource($user),
            'sessions'               => $this->buildSessionList($user),
            'mfa_enabled'            => $user->mfa_enabled,
            'notificationPreference' => $user->prefs(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'bio'      => ['nullable', 'string', 'max:1000'],
            'timezone' => ['nullable', 'string'],
            'locale'   => ['nullable', 'string', Rule::in(['id', 'en'])],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:2048']]);

        $user = $request->user();
        $disk = config('filesystems.evidence_disk', 'local');
        $ext  = $request->file('avatar')->getClientOriginalExtension();
        $path = "avatars/{$user->id}.{$ext}";

        Storage::disk($disk)->putFileAs('', $request->file('avatar'), $path);
        $user->update(['avatar_path' => $path]);

        return back()->with('success', 'Avatar berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        $user->update(['password' => $request->password]);
        $this->audit->log(AuditAction::PASSWORD_CHANGED, 'User', $user->id);

        return back()->with('success', 'Password berhasil diubah.');
    }

    public function mfaSetup(Request $request): RedirectResponse
    {
        $user     = $request->user();
        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);

        $secret = $google2fa->generateSecretKey();
        $qrUrl  = $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        // Store pending secret in session so mfaEnable can verify it
        session(['mfa_pending_secret' => $secret]);

        return back()->with('mfa_setup', ['secret' => $secret, 'qr_url' => $qrUrl]);
    }

    public function mfaEnable(Request $request): RedirectResponse
    {
        $request->validate(['otp_code' => ['required', 'string', 'size:6']]);

        $pendingSecret = session('mfa_pending_secret');
        if (! $pendingSecret) {
            return back()->withErrors(['otp_code' => 'Setup MFA belum dimulai. Klik "Aktifkan MFA" terlebih dahulu.']);
        }

        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);
        if (! $google2fa->verifyKey($pendingSecret, $request->otp_code)) {
            return back()->withErrors(['otp_code' => 'Kode OTP tidak valid.']);
        }

        $user = $request->user();
        $user->update(['mfa_secret' => encrypt($pendingSecret), 'mfa_enabled' => true]);
        session()->forget('mfa_pending_secret');

        $codes = collect(range(1, 10))->map(fn () => Str::random(10));
        $user->mfaBackupCodes()->delete();
        $codes->each(fn ($code) => MfaBackupCode::create([
            'user_id'   => $user->id,
            'code_hash' => Hash::make($code),
        ]));

        return back()->with('backup_codes', $codes->all());
    }

    public function mfaDisable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();
        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Password tidak sesuai.']);
        }

        $user->update(['mfa_enabled' => false, 'mfa_secret' => null]);
        $user->mfaBackupCodes()->delete();

        return back()->with('success', 'MFA berhasil dinonaktifkan.');
    }

    public function revokeSession(Request $request, int|string $tokenId): RedirectResponse
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();
        if (! $token) {
            return back()->withErrors(['session' => 'Sesi tidak ditemukan.']);
        }

        $token->delete();

        return back()->with('success', 'Sesi berhasil dicabut.');
    }

    private function buildSessionList($user): array
    {
        return $user->tokens()->orderByDesc('last_used_at')->get()->map(fn ($t) => [
            'id'           => $t->id,
            'name'         => $t->name,
            'last_used_at' => $t->last_used_at?->toISOString(),
            'created_at'   => $t->created_at->toISOString(),
        ])->toArray();
    }
}
