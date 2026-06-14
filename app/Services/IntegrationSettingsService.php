<?php
namespace App\Services;

use App\Enums\AuditAction;
use App\Models\IntegrationSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single source of truth for effective external-integration configuration.
 *
 * Layering: DB row (per-organization) overlays the static config()/env fallback.
 * Secret fields are stored ENCRYPTED at rest (Crypt) and NEVER returned in
 * plaintext to the client — `all()` exposes only `['configured' => bool]` for
 * secrets. App code reads effective values via `get()`; the AI provider stack is
 * wired from `aiConfig()`.
 */
class IntegrationSettingsService
{
    /**
     * Per-request memo of loaded rows, keyed by organization_id (P3). aiConfig()
     * calls get() ~35× per resolution; without this each would re-query.
     *
     * @var array<string, array<string, IntegrationSetting>>
     */
    private array $rowsCache = [];

    /**
     * Per-request memo of the env/config fallback map (P3) — built once.
     *
     * @var array<string, mixed>|null
     */
    private ?array $configMapCache = null;

    public function __construct(private readonly AuditService $audit) {}

    /**
     * COMPLETE field metadata driving form + validation + masking.
     * Each group: ['label','icon','description','phase4'?,'fields'=>[...]].
     * Each field: ['key','label','type','secret'?,'options'?,'placeholder'?,'help'?,'step'?].
     *
     * @return array<string, array{label:string, icon:string, description:string, phase4?:bool, fields: array<int, array<string, mixed>>}>
     */
    public function schema(): array
    {
        $aiProviders = ['gemini', 'claude', 'openai', 'qwen', 'deepseek', 'llama', 'mistral'];

        $providerDefaults = [
            'gemini'   => ['base_url' => 'https://generativelanguage.googleapis.com', 'model' => 'gemini-2.0-flash'],
            'claude'   => ['base_url' => 'https://api.anthropic.com', 'model' => 'claude-3-5-sonnet'],
            'openai'   => ['base_url' => 'https://api.openai.com', 'model' => 'gpt-4o'],
            'qwen'     => ['base_url' => 'https://dashscope.aliyuncs.com', 'model' => 'qwen2.5-72b-instruct'],
            'deepseek' => ['base_url' => 'https://api.deepseek.com', 'model' => 'deepseek-chat'],
            'llama'    => ['base_url' => 'http://127.0.0.1:11434', 'model' => 'llama3.1'],
            'mistral'  => ['base_url' => 'https://api.mistral.ai', 'model' => 'mistral-large-latest'],
        ];

        $aiFields = [
            ['key' => 'default_provider', 'label' => 'Provider Default', 'type' => 'select', 'options' => ['fake', 'gemini', 'claude', 'openai', 'qwen', 'deepseek', 'llama', 'mistral'], 'help' => 'Provider LLM utama yang dipakai Asisten AI.'],
            ['key' => 'fallback_order', 'label' => 'Urutan Fallback', 'type' => 'text', 'placeholder' => 'gemini,claude,openai,fake', 'help' => 'Daftar provider dipisah koma; "fake" selalu jadi cadangan terakhir.'],
        ];
        foreach ($aiProviders as $p) {
            $aiFields[] = ['key' => "providers.{$p}.enabled", 'label' => 'Aktif', 'type' => 'bool', 'provider' => $p];
            $aiFields[] = ['key' => "providers.{$p}.api_key", 'label' => 'API Key', 'type' => 'secret', 'secret' => true, 'provider' => $p];
            $aiFields[] = ['key' => "providers.{$p}.base_url", 'label' => 'Base URL', 'type' => 'text', 'placeholder' => $providerDefaults[$p]['base_url'], 'provider' => $p];
            $aiFields[] = ['key' => "providers.{$p}.model", 'label' => 'Model', 'type' => 'text', 'placeholder' => $providerDefaults[$p]['model'], 'provider' => $p];
            $aiFields[] = ['key' => "providers.{$p}.max_tokens", 'label' => 'Max Tokens', 'type' => 'number', 'placeholder' => '2048', 'provider' => $p];
            $aiFields[] = ['key' => "providers.{$p}.temperature", 'label' => 'Temperature', 'type' => 'number', 'step' => '0.1', 'placeholder' => '0.7', 'provider' => $p];
        }
        $aiFields[] = ['key' => 'embedding.provider', 'label' => 'Embedding Provider', 'type' => 'select', 'options' => ['fake', 'gemini', 'openai'], 'group_label' => 'Embedding'];
        $aiFields[] = ['key' => 'embedding.api_key', 'label' => 'Embedding API Key', 'type' => 'secret', 'secret' => true, 'group_label' => 'Embedding'];
        $aiFields[] = ['key' => 'embedding.model', 'label' => 'Embedding Model', 'type' => 'text', 'placeholder' => 'text-embedding-3-small', 'group_label' => 'Embedding'];
        $aiFields[] = ['key' => 'embedding.dimensions', 'label' => 'Dimensi', 'type' => 'number', 'placeholder' => '64', 'group_label' => 'Embedding'];

        return [
            'ai' => [
                'label'       => 'AI & LLM',
                'icon'        => 'Sparkles',
                'description' => 'Mengatur penyedia LLM untuk Asisten AI (model chat & embedding).',
                'fields'      => $aiFields,
            ],
            'vector' => [
                'label'       => 'Vector Database',
                'icon'        => 'Database',
                'description' => 'Penyimpanan vektor untuk pencarian semantik / RAG (Qdrant).',
                'fields'      => [
                    ['key' => 'driver', 'label' => 'Driver', 'type' => 'select', 'options' => ['database', 'qdrant']],
                    ['key' => 'host', 'label' => 'Host', 'type' => 'text', 'placeholder' => 'http://127.0.0.1:6333'],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'secret', 'secret' => true],
                    ['key' => 'collection', 'label' => 'Collection', 'type' => 'text', 'placeholder' => 'ruangasn'],
                ],
            ],
            'stt' => [
                'label'       => 'Speech-to-Text',
                'icon'        => 'Mic',
                'description' => 'Transkripsi otomatis rekaman rapat.',
                'phase4'      => true,
                'fields'      => [
                    ['key' => 'enabled', 'label' => 'Aktif', 'type' => 'bool'],
                    ['key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'options' => ['whisper', 'deepgram', 'google']],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'secret', 'secret' => true],
                    ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'placeholder' => 'whisper-1'],
                    ['key' => 'language', 'label' => 'Bahasa', 'type' => 'text', 'placeholder' => 'id'],
                ],
            ],
            'storage' => [
                'label'       => 'Object Storage',
                'icon'        => 'HardDrive',
                'description' => 'Penyimpanan berkas / evidence (MinIO atau S3).',
                'fields'      => [
                    ['key' => 'driver', 'label' => 'Driver', 'type' => 'select', 'options' => ['local', 's3', 'minio']],
                    ['key' => 'endpoint', 'label' => 'Endpoint', 'type' => 'text', 'placeholder' => 'http://127.0.0.1:9000'],
                    ['key' => 'region', 'label' => 'Region', 'type' => 'text', 'placeholder' => 'us-east-1'],
                    ['key' => 'bucket', 'label' => 'Bucket', 'type' => 'text', 'placeholder' => 'ruangasn'],
                    ['key' => 'access_key', 'label' => 'Access Key', 'type' => 'secret', 'secret' => true],
                    ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'secret', 'secret' => true],
                    ['key' => 'use_path_style', 'label' => 'Path-style Endpoint', 'type' => 'bool'],
                ],
            ],
            'mail' => [
                'label'       => 'Email / SMTP',
                'icon'        => 'Mail',
                'description' => 'Pengiriman email notifikasi & undangan.',
                'fields'      => [
                    ['key' => 'mailer', 'label' => 'Mailer', 'type' => 'select', 'options' => ['log', 'smtp', 'ses', 'mailgun']],
                    ['key' => 'host', 'label' => 'Host', 'type' => 'text', 'placeholder' => 'smtp.mailtrap.io'],
                    ['key' => 'port', 'label' => 'Port', 'type' => 'number', 'placeholder' => '587'],
                    ['key' => 'username', 'label' => 'Username', 'type' => 'text'],
                    ['key' => 'password', 'label' => 'Password', 'type' => 'secret', 'secret' => true],
                    ['key' => 'encryption', 'label' => 'Enkripsi', 'type' => 'select', 'options' => ['none', 'tls', 'ssl']],
                    ['key' => 'from_address', 'label' => 'Dari (Email)', 'type' => 'text', 'placeholder' => 'noreply@ruangasn.id'],
                    ['key' => 'from_name', 'label' => 'Dari (Nama)', 'type' => 'text', 'placeholder' => 'RuangASN'],
                ],
            ],
            'realtime' => [
                'label'       => 'Realtime',
                'icon'        => 'Radio',
                'description' => 'WebSocket realtime (Laravel Reverb atau Pusher).',
                'fields'      => [
                    ['key' => 'driver', 'label' => 'Driver', 'type' => 'select', 'options' => ['reverb', 'pusher']],
                    ['key' => 'app_id', 'label' => 'App ID', 'type' => 'text'],
                    ['key' => 'app_key', 'label' => 'App Key', 'type' => 'text'],
                    ['key' => 'app_secret', 'label' => 'App Secret', 'type' => 'secret', 'secret' => true],
                    ['key' => 'host', 'label' => 'Host', 'type' => 'text', 'placeholder' => '127.0.0.1'],
                    ['key' => 'port', 'label' => 'Port', 'type' => 'number', 'placeholder' => '8080'],
                    ['key' => 'scheme', 'label' => 'Scheme', 'type' => 'select', 'options' => ['http', 'https']],
                ],
            ],
            'video' => [
                'label'       => 'Video Conference',
                'icon'        => 'Video',
                'description' => 'Tautan rapat video (Jitsi, Zoom, Google Meet).',
                'phase4'      => true,
                'fields'      => [
                    ['key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'options' => ['none', 'jitsi', 'zoom', 'google_meet']],
                    ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'text', 'placeholder' => 'https://meet.jit.si'],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'secret', 'secret' => true],
                    ['key' => 'api_secret', 'label' => 'API Secret', 'type' => 'secret', 'secret' => true],
                ],
            ],
            'whatsapp' => [
                'label'       => 'WhatsApp Business',
                'icon'        => 'MessageCircle',
                'description' => 'Notifikasi via WhatsApp Business API.',
                'phase4'      => true,
                'fields'      => [
                    ['key' => 'enabled', 'label' => 'Aktif', 'type' => 'bool'],
                    ['key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'options' => ['meta_cloud', 'twilio']],
                    ['key' => 'phone_number_id', 'label' => 'Phone Number ID', 'type' => 'text'],
                    ['key' => 'access_token', 'label' => 'Access Token', 'type' => 'secret', 'secret' => true],
                    ['key' => 'webhook_verify_token', 'label' => 'Webhook Verify Token', 'type' => 'secret', 'secret' => true],
                ],
            ],
            'siasn' => [
                'label'       => 'Integrasi SIASN',
                'icon'        => 'Landmark',
                'description' => 'Sinkronisasi data kepegawaian dengan SIASN BKN.',
                'phase4'      => true,
                'fields'      => [
                    ['key' => 'enabled', 'label' => 'Aktif', 'type' => 'bool'],
                    ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'text', 'placeholder' => 'https://apimws.bkn.go.id'],
                    ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
                    ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'secret', 'secret' => true],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'secret', 'secret' => true],
                ],
            ],
            'srikandi' => [
                'label'       => 'Integrasi SRIKANDI',
                'icon'        => 'Archive',
                'description' => 'Integrasi persuratan & kearsipan dinamis SRIKANDI.',
                'phase4'      => true,
                'fields'      => [
                    ['key' => 'enabled', 'label' => 'Aktif', 'type' => 'bool'],
                    ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'text', 'placeholder' => 'https://srikandi.arsip.go.id'],
                    ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
                    ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'secret', 'secret' => true],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'secret', 'secret' => true],
                ],
            ],
            'sso' => [
                'label'       => 'SSO Nasional',
                'icon'        => 'KeyRound',
                'description' => 'Single Sign-On nasional (SAML atau OIDC).',
                'phase4'      => true,
                'fields'      => [
                    ['key' => 'enabled', 'label' => 'Aktif', 'type' => 'bool'],
                    ['key' => 'protocol', 'label' => 'Protokol', 'type' => 'select', 'options' => ['saml', 'oidc']],
                    ['key' => 'entity_id', 'label' => 'Entity ID', 'type' => 'text'],
                    ['key' => 'idp_metadata_url', 'label' => 'IdP Metadata URL', 'type' => 'text'],
                    ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
                    ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'secret', 'secret' => true],
                    ['key' => 'redirect_uri', 'label' => 'Redirect URI', 'type' => 'text'],
                ],
            ],
        ];
    }

    /**
     * Current values for every field, grouped. Secrets are NEVER returned in
     * plaintext: instead `['configured' => bool]`. Non-secret values fall back to
     * config()/env when no DB row exists.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(?Organization $org): array
    {
        $rows = $this->rows($org);
        $out  = [];

        foreach ($this->schema() as $group => $meta) {
            $out[$group] = [];
            foreach ($meta['fields'] as $field) {
                $key      = $field['key'];
                $isSecret = ! empty($field['secret']);
                $row      = $rows["{$group}.{$key}"] ?? null;

                if ($isSecret) {
                    $configured = $row !== null
                        ? ($row->value !== null && $row->value !== '')
                        : ($this->configFallback($group, $key) !== null && $this->configFallback($group, $key) !== '');
                    $out[$group][$key] = ['configured' => $configured];
                    continue;
                }

                if ($row !== null) {
                    $out[$group][$key] = $this->castOut($field, $row->value);
                } else {
                    $out[$group][$key] = $this->castOut($field, $this->configFallback($group, $key));
                }
            }
        }

        return $out;
    }

    /**
     * Effective value for app code: DB (decrypted if secret) → config fallback.
     */
    public function get(?Organization $org, string $group, string $key, mixed $default = null): mixed
    {
        $row = $this->rows($org)["{$group}.{$key}"] ?? null;

        if ($row !== null) {
            if ($row->is_secret) {
                if ($row->value === null || $row->value === '') {
                    return $this->configFallback($group, $key) ?? $default;
                }
                try {
                    return Crypt::decryptString($row->value);
                } catch (\Throwable) {
                    // Never log the secret value itself — only its coordinates.
                    Log::warning('integration secret decrypt failed', [
                        'group' => $group,
                        'key'   => $key,
                        'org'   => $org?->id,
                    ]);

                    return $default;
                }
            }

            return $row->value ?? $this->configFallback($group, $key) ?? $default;
        }

        $fallback = $this->configFallback($group, $key);

        return $fallback ?? $default;
    }

    /**
     * Validate against schema + upsert rows for ONE group. Secret fields only
     * overwrite when a non-empty value is submitted (empty = keep existing).
     * Encrypts secrets. Audits AuditAction::UPDATED 'IntegrationSetting'. Tx.
     *
     * @param array{group: string, fields: array<string, mixed>} $input
     */
    public function save(Organization $org, array $input, User $actor): void
    {
        $group  = (string) $input['group'];
        $schema = $this->schema();

        if (! isset($schema[$group])) {
            return;
        }

        $fieldMeta = [];
        foreach ($schema[$group]['fields'] as $f) {
            $fieldMeta[$f['key']] = $f;
        }

        $submitted = (array) $input['fields'];

        DB::transaction(function () use ($org, $group, $fieldMeta, $submitted, $actor) {
            foreach ($submitted as $key => $value) {
                if (! isset($fieldMeta[$key])) {
                    continue; // ignore unknown keys (defense)
                }
                $meta     = $fieldMeta[$key];
                $isSecret = ! empty($meta['secret']);

                $existing = IntegrationSetting::query()
                    ->where('organization_id', $org->id)
                    ->where('group', $group)
                    ->where('key', $key)
                    ->first();

                if ($isSecret) {
                    // Empty secret submission => keep whatever exists (no overwrite).
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $stored = Crypt::encryptString((string) $value);
                } else {
                    $stored = $this->normalizeIn($meta, $value);
                }

                if ($existing) {
                    $existing->update([
                        'value'      => $stored,
                        'is_secret'  => $isSecret,
                        'updated_by' => $actor->id,
                    ]);
                } else {
                    IntegrationSetting::create([
                        'id'              => (string) Str::ulid(),
                        'organization_id' => $org->id,
                        'group'           => $group,
                        'key'             => $key,
                        'value'           => $stored,
                        'is_secret'       => $isSecret,
                        'updated_by'      => $actor->id,
                    ]);
                }
            }

            $this->audit->log(
                AuditAction::UPDATED,
                'IntegrationSetting',
                $org->id,
                [],
                ['group' => $group, 'keys' => array_keys($submitted)],
                $org->id,
            );
        });

        // Invalidate the per-request rows memo for this org so a subsequent
        // read in the same request reflects the just-written values (P3).
        unset($this->rowsCache[$org->id]);
    }

    /**
     * Assemble effective AI config (DB overlay over config('ai')). Includes
     * decrypted provider api_keys — used ONLY server-side to wire providers.
     *
     * @return array<string, mixed>
     */
    public function aiConfig(?Organization $org): array
    {
        $base = (array) config('ai');

        $base['default_provider'] = (string) $this->get($org, 'ai', 'default_provider', $base['default_provider'] ?? 'fake');

        $rawOrder = $this->get($org, 'ai', 'fallback_order', null);
        if (is_string($rawOrder) && trim($rawOrder) !== '') {
            $base['fallback_order'] = array_values(array_filter(array_map('trim', explode(',', $rawOrder))));
            if (! in_array('fake', $base['fallback_order'], true)) {
                $base['fallback_order'][] = 'fake';
            }
        }

        $providers = (array) ($base['providers'] ?? []);
        foreach (['gemini', 'claude', 'openai', 'qwen', 'deepseek', 'llama', 'mistral'] as $p) {
            $current = (array) ($providers[$p] ?? []);

            $apiKey   = $this->get($org, 'ai', "providers.{$p}.api_key", $current['api_key'] ?? null);
            $baseUrl  = $this->get($org, 'ai', "providers.{$p}.base_url", $current['base_uri'] ?? null);
            $model    = $this->get($org, 'ai', "providers.{$p}.model", $current['model'] ?? null);
            $maxTok   = $this->get($org, 'ai', "providers.{$p}.max_tokens", $current['max_tokens'] ?? null);
            $temp     = $this->get($org, 'ai', "providers.{$p}.temperature", $current['temperature'] ?? null);
            $enabled  = $this->get($org, 'ai', "providers.{$p}.enabled", null);

            $providers[$p] = array_merge($current, array_filter([
                'api_key'     => $apiKey ?: null,
                'base_uri'    => $baseUrl ?: null,
                'model'       => $model ?: null,
                'max_tokens'  => $maxTok !== null && $maxTok !== '' ? (int) $maxTok : null,
                'temperature' => $temp !== null && $temp !== '' ? (float) $temp : null,
            ], fn ($v) => $v !== null));

            // Honour explicit disable: a provider toggled off drops its api_key
            // so isAvailable() returns false and it is skipped in fallback.
            if ($enabled === '0' || $enabled === 0 || $enabled === false) {
                $providers[$p]['api_key'] = null;
            }
        }
        $providers['fake'] = [];
        $base['providers'] = $providers;

        $base['embedding'] = array_merge((array) ($base['embedding'] ?? []), array_filter([
            'provider'   => $this->get($org, 'ai', 'embedding.provider', $base['embedding']['provider'] ?? 'fake'),
            'model'      => $this->get($org, 'ai', 'embedding.model', $base['embedding']['model'] ?? null),
            'dimensions' => ($d = $this->get($org, 'ai', 'embedding.dimensions', $base['embedding']['dimensions'] ?? null)) !== null && $d !== '' ? (int) $d : null,
            'api_key'    => $this->get($org, 'ai', 'embedding.api_key', null) ?: null,
        ], fn ($v) => $v !== null));

        $vectorDriver = $this->get($org, 'vector', 'driver', null);
        if (is_string($vectorDriver) && $vectorDriver !== '') {
            $base['vector_store'] = $vectorDriver;
        }

        return $base;
    }

    /**
     * Load all rows for an org keyed by "group.key".
     *
     * @return array<string, IntegrationSetting>
     */
    private function rows(?Organization $org): array
    {
        if ($org === null) {
            return [];
        }

        // Memoize per org for the lifetime of this instance (P3). Invalidated in
        // save() so a freshly written value is reflected on subsequent reads.
        return $this->rowsCache[$org->id] ??= IntegrationSetting::query()
            ->withoutGlobalScope('organization')
            ->where('organization_id', $org->id)
            ->get()
            ->keyBy(fn (IntegrationSetting $r) => "{$r->group}.{$r->key}")
            ->all();
    }

    /**
     * Resolve the config()/env fallback for a non-DB-backed value.
     */
    private function configFallback(string $group, string $key): mixed
    {
        $map = $this->configMap();

        return $map["{$group}.{$key}"] ?? null;
    }

    /**
     * Static mapping of group.key → live config() value (env-driven fallbacks).
     *
     * @return array<string, mixed>
     */
    private function configMap(): array
    {
        if ($this->configMapCache !== null) {
            return $this->configMapCache;
        }

        return $this->configMapCache = [
            // AI
            'ai.default_provider'           => config('ai.default_provider'),
            'ai.fallback_order'             => implode(',', (array) config('ai.fallback_order', [])),
            'ai.providers.gemini.api_key'   => config('ai.providers.gemini.api_key'),
            'ai.providers.gemini.base_url'  => config('ai.providers.gemini.base_uri'),
            'ai.providers.gemini.model'     => config('ai.providers.gemini.model'),
            'ai.providers.claude.api_key'   => config('ai.providers.claude.api_key'),
            'ai.providers.claude.base_url'  => config('ai.providers.claude.base_uri'),
            'ai.providers.claude.model'     => config('ai.providers.claude.model'),
            'ai.providers.openai.api_key'   => config('ai.providers.openai.api_key'),
            'ai.providers.openai.base_url'  => config('ai.providers.openai.base_uri'),
            'ai.providers.openai.model'     => config('ai.providers.openai.model'),
            'ai.providers.qwen.api_key'     => config('ai.providers.qwen.api_key'),
            'ai.providers.qwen.base_url'    => config('ai.providers.qwen.base_uri'),
            'ai.providers.qwen.model'       => config('ai.providers.qwen.model'),
            'ai.providers.deepseek.api_key' => config('ai.providers.deepseek.api_key'),
            'ai.providers.deepseek.base_url'=> config('ai.providers.deepseek.base_uri'),
            'ai.providers.deepseek.model'   => config('ai.providers.deepseek.model'),
            'ai.providers.llama.api_key'    => config('ai.providers.llama.api_key'),
            'ai.providers.llama.base_url'   => config('ai.providers.llama.base_uri'),
            'ai.providers.llama.model'      => config('ai.providers.llama.model'),
            'ai.providers.mistral.api_key'  => config('ai.providers.mistral.api_key'),
            'ai.providers.mistral.base_url' => config('ai.providers.mistral.base_uri'),
            'ai.providers.mistral.model'    => config('ai.providers.mistral.model'),
            'ai.embedding.provider'         => config('ai.embedding.provider'),
            'ai.embedding.model'            => config('ai.embedding.model'),
            'ai.embedding.dimensions'       => config('ai.embedding.dimensions'),
            // Vector
            'vector.driver'     => config('ai.vector_store'),
            'vector.host'       => config('ai.vector.host'),
            'vector.collection' => config('ai.vector.collection'),
            // Storage
            'storage.driver'         => config('filesystems.default'),
            'storage.endpoint'       => config('filesystems.disks.s3.endpoint'),
            'storage.region'         => config('filesystems.disks.s3.region'),
            'storage.bucket'         => config('filesystems.disks.s3.bucket'),
            'storage.use_path_style' => config('filesystems.disks.s3.use_path_style_endpoint'),
            // Mail
            'mail.mailer'       => config('mail.default'),
            'mail.host'         => config('mail.mailers.smtp.host'),
            'mail.port'         => config('mail.mailers.smtp.port'),
            'mail.username'     => config('mail.mailers.smtp.username'),
            'mail.encryption'   => config('mail.mailers.smtp.encryption'),
            'mail.from_address' => config('mail.from.address'),
            'mail.from_name'    => config('mail.from.name'),
            // Realtime
            'realtime.driver' => config('broadcasting.default'),
            'realtime.host'   => config('broadcasting.connections.reverb.options.host'),
            'realtime.port'   => config('broadcasting.connections.reverb.options.port'),
            'realtime.scheme' => config('broadcasting.connections.reverb.options.scheme'),
        ];
    }

    /**
     * Cast a stored/fallback value to a sensible client-facing shape.
     *
     * @param array<string, mixed> $field
     */
    private function castOut(array $field, mixed $value): mixed
    {
        if ($value === null) {
            return $field['type'] === 'bool' ? false : '';
        }

        return match ($field['type']) {
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? $value + 0 : '',
            default  => (string) $value,
        };
    }

    /**
     * Normalize an inbound non-secret value to its stored string form.
     *
     * @param array<string, mixed> $field
     */
    private function normalizeIn(array $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($field['type']) {
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'number' => $value === '' ? null : (string) $value,
            default  => (string) $value,
        };
    }
}
