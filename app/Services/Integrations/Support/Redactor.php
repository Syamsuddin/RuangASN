<?php
namespace App\Services\Integrations\Support;

/**
 * Scrubs secrets and PII out of any text/array that is about to be persisted to
 * integration_runs.payload_excerpt or webhook_events.headers/body_excerpt.
 *
 * Two layers of defence:
 *   1. Known-secret list — exact configured secret strings are masked verbatim.
 *   2. Heuristics — sensitive header/JSON keys (authorization, token, api_key,
 *      password, secret, signature, nik, nip) have their VALUES masked, and any
 *      bearer/long-token-looking substring is masked.
 * Output is always truncated so a large body never bloats the audit row.
 */
final class Redactor
{
    public const MASK = '[REDACTED]';

    private const MAX_LEN = 1000;

    /** Header/JSON keys whose values must never be stored. */
    private const SENSITIVE_KEYS = [
        'authorization', 'auth', 'token', 'access_token', 'refresh_token',
        'api_key', 'apikey', 'x-api-key', 'client_secret', 'secret',
        'password', 'passwd', 'signature', 'x-hub-signature', 'x-hub-signature-256',
        'verify_token', 'webhook_secret', 'webhook_verify_token',
        'nik', 'nip', 'phone', 'phone_number', 'email',
    ];

    /**
     * Redact a free-text string. `$knownSecrets` are exact values (decrypted
     * credentials) that MUST be masked even when they appear inline.
     *
     * @param array<int, string|null> $knownSecrets
     */
    public static function text(?string $value, array $knownSecrets = []): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        foreach ($knownSecrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $value = str_replace($secret, self::MASK, $value);
            }
        }

        // Mask bearer tokens and long opaque tokens that slipped through.
        $value = (string) preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer ' . self::MASK, $value);
        $value = (string) preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', self::MASK, $value);

        // Indonesian PII floor: mask raw digit runs in free text even below the
        // 32-char token threshold. Longest-first so an 18-digit NIP isn't chewed
        // into a NIK + phone, and a 16-digit NIK isn't split by the phone rule.
        //   NIP  = 18 digits, NIK = 16 digits, phone = 8–15 digit runs.
        $value = (string) preg_replace('/(?<!\d)\d{18}(?!\d)/', self::MASK, $value); // NIP
        $value = (string) preg_replace('/(?<!\d)\d{16}(?!\d)/', self::MASK, $value); // NIK
        $value = (string) preg_replace('/(?<!\d)\+?\d{8,15}(?!\d)/', self::MASK, $value); // phone

        return self::truncate($value);
    }

    /**
     * Redact a structured payload (array) and return a compact JSON excerpt.
     * Sensitive keys are masked recursively.
     *
     * @param array<mixed> $data
     * @param array<int, string|null> $knownSecrets
     */
    public static function payload(array $data, array $knownSecrets = []): string
    {
        $masked = self::maskArray($data, $knownSecrets);
        $json   = json_encode($masked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return self::truncate($json === false ? '' : $json);
    }

    /**
     * Redact a header/array map in place (returns a new array). Used for the
     * webhook_events.headers column.
     *
     * @param array<mixed> $data
     * @param array<int, string|null> $knownSecrets
     * @return array<mixed>
     */
    public static function maskArray(array $data, array $knownSecrets = []): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $lowered = is_string($key) ? strtolower($key) : '';
            if ($lowered !== '' && self::isSensitiveKey($lowered)) {
                $out[$key] = self::MASK;
                continue;
            }
            if (is_array($value)) {
                $out[$key] = self::maskArray($value, $knownSecrets);
            } elseif (is_string($value)) {
                $out[$key] = self::text($value, $knownSecrets);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function truncate(string $value): string
    {
        if (mb_strlen($value) <= self::MAX_LEN) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_LEN) . '…';
    }
}
