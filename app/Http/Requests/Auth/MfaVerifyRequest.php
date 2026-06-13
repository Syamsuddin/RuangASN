<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MfaVerifyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'mfa_token' => ['required', 'string'],
            'otp_code'  => ['required', 'string', 'min:6', 'max:8'],
        ];
    }
}
