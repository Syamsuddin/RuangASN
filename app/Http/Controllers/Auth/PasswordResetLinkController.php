<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always send reset link regardless of whether email exists (anti-enumeration)
        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('Jika email terdaftar, tautan reset password telah dikirim.'));
    }
}
