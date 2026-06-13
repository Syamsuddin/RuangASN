<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'           => Hash::make($password),
                    'failed_login_count' => 0,
                    'locked_until'       => null,
                    'remember_token'     => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                $this->audit->log(AuditAction::PASSWORD_CHANGED, 'User', $user->id);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('Password berhasil direset. Silakan masuk.'));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
