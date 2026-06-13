# RuangASN

## Digital Operating System untuk Aparatur Sipil Negara (ASN) Indonesia

RuangASN menyatukan tugas harian, kolaborasi rapat, manajemen dokumen, pelaporan kinerja (SKP), dan asisten AI dalam satu workspace terpadu — *One ASN, One Workspace*.

---

## Daftar Isi

- [Tentang](#tentang)
- [Fitur Utama](#fitur-utama)
- [Tech Stack](#tech-stack)
- [Arsitektur](#arsitektur)
- [Struktur Proyek](#struktur-proyek)
- [Local Development](#local-development)
- [Roadmap](#roadmap)
- [Keamanan](#keamanan)
- [Lisensi](#lisensi)

---

## Tentang

RuangASN adalah platform modular monolith yang dirancang untuk memecahkan masalah siloisasi aplikasi di pemerintahan daerah (OPD). Platform ini menyediakan satu titik masuk bagi seluruh ASN — dari staf pelaksana hingga kepala dinas — untuk bekerja, berkolaborasi, dan melaporkan kinerja secara digital, aman, dan patuh regulasi nasional (SPBE & UU PDP).

**Target pengguna:**

| Persona | Peran |
|---|---|
| Super Admin | Admin tingkat provinsi/kabupaten/kota, konfigurasi global & pendaftaran OPD |
| Admin OPD | Pengelola kepegawaian/IT dinas, konfigurasi SOTK, audit internal |
| Pimpinan | Kepala Dinas/Bidang/Seksi — pantau kinerja staf, delegasi tugas, approval |
| Staf ASN | Pelaksana — kelola tugas harian, upload bukti kerja, laporan kinerja |
| Sekretariat | Notulis rapat — kelola risalah, distribusi action items |

---

## Fitur Utama

### Fase 1 — Core (MVP)

- **Autentikasi & MFA** — Login NIP + Password, TOTP (Google Authenticator), lockout brute-force, revokasi sesi
- **Multi-Tenant Isolation** — Isolasi data mutlak antar-OPD via `organization_id` row-level scoping
- **Task Management** — CRUD tugas, assignment, prioritas, state machine lengkap (`draft → open → assigned → in_progress → waiting_review → completed → closed → archived`), upload bukti (evidence wajib sebelum selesai)
- **Organizational Structure** — Visualisasi SOTK, delegasi wewenang sementara (Plt./Plh.)
- **Notifikasi Real-time** — In-app + email via Laravel Reverb (WebSocket)
- **Audit Trail Immutable** — Seluruh mutasi data dicatat dengan hash chain SHA-256

### Fase 2 — Workspace

- **Meeting Workspace** — Penjadwalan rapat, video call (Jitsi), daftar hadir, risalah rapat (MoM), auto-konversi action items menjadi Tasks
- **Document Workspace** — Versioning dokumen, klasifikasi L1–L4, approval workflow, watermark
- **Calendar** — Kalender personal & tim terintegrasi
- **Reporting** — Laporan kerja dengan workflow draft → submitted → approved

### Fase 3 — AI & Kinerja

- **AI Secretary Agent** — Daily briefing otomatis pukul 07.30, ringkasan agenda, draf risalah rapat
- **AI Meeting Agent** — Transkripsi audio rapat (Whisper), draf notulen otomatis
- **AI Report Agent** — Pembuatan laporan dari evidence tasks
- **Performance / SKP** — SKP digital sesuai PermenPANRB 6/2022, realisasi IKI terintegrasi task
- **RAG + Knowledge Base** — Pencarian semantik atas seluruh dokumen & pengetahuan OPD

### Fase 4 — Integrasi & Advanced

- Integrasi SIASN, SRIKANDI, SSO Nasional (SAML/OIDC)
- Executive Dashboard (Bupati/Walikota view)
- Chat kolaborasi in-app
- Notifikasi WhatsApp Business API
- Semantic search skala penuh

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| **Backend** | Laravel 12, PHP 8.4+, Laravel Sanctum, Spatie Permission |
| **Web Frontend** | Vue 3 (Composition API), Inertia.js 2, TypeScript 5, TailwindCSS 4, Shadcn Vue |
| **Mobile** | Flutter 3.24 (Dart 3.5), Riverpod, Drift (SQLite), GoRouter |
| **Database** | PostgreSQL 17 (primary), Redis 7 (cache/queue/event bus) |
| **Object Storage** | MinIO (S3-compatible) |
| **Vector DB** | Qdrant |
| **Realtime** | Laravel Reverb (WebSocket) |
| **Search** | PostgreSQL FTS (MVP) → OpenSearch (skala) |
| **Queue** | Laravel Horizon (Redis workers) |
| **AI Models** | Gemini 2.0 (default), Claude 3.5, GPT-4o; lokal: Qwen 2.5/Llama via Ollama |
| **Embedding** | BGE-M3 (BAAI) |
| **STT** | OpenAI Whisper |
| **Reverse Proxy** | Traefik v3 (TLS 1.3, rate limiter) |
| **Monitoring** | Prometheus + Grafana + Grafana Loki + Sentry |
| **CI/CD** | GitHub Actions |

---

## Arsitektur

### Prinsip Desain (Axioms — Tidak Boleh Dilanggar)

| Axiom | Aturan |
|---|---|
| AXIOM-01 | Setiap ASN memiliki tepat satu Personal Workspace permanen |
| AXIOM-02 | Setiap rapat memiliki satu ruang digital permanen; action items wajib menjadi Task |
| AXIOM-03 | Task tidak bisa `completed` tanpa minimal 1 evidence |
| AXIOM-04 | AI hanya menyarankan (`proposed_actions`), tidak pernah mengeksekusi atau approve |
| AXIOM-05 | Semua entitas utama memiliki state machine terdefinisi & riwayat status |
| AXIOM-06 | Audit log immutable — tidak bisa diubah siapapun, termasuk Super Admin |
| AXIOM-07 | Pengetahuan organisasi tidak hilang saat ASN mutasi/pensiun |
| AXIOM-08 | Permission check sebelum data apapun dikembalikan ke client |

### Pola Kode Utama

**Base Model** — semua model domain extends ini:
```php
abstract class BaseModel extends Model {
    use BelongsToOrganization, SoftDeletes, HasUlid;
    public $incrementing = false;
    protected $keyType = 'string'; // ULID CHAR(26)
}
```

**Service Layer** — business logic dalam `DB::transaction()`:
```php
public function create(array $data, User $creator): Task {
    return DB::transaction(function () use ($data, $creator) {
        $task = $this->repository->create([...$data, 'organization_id' => $creator->organization_id]);
        $this->outbox->publish('task.created', $task->toEventPayload());
        return $task;
    });
}
```

**Controller** — `authorize()` selalu di baris pertama:
```php
public function store(StoreTaskRequest $request, TaskService $service): JsonResponse {
    $this->authorize('create', Task::class);
    return response()->json(['data' => TaskResource::make($service->create($request->validated(), $request->user()))], 201);
}
```

### Keputusan Teknis Terkunci

| # | Keputusan | Nilai |
|---|---|---|
| TD-01 | Auth strategy | Laravel Sanctum — cookie session (SPA), PAT (Flutter/API); tanpa JWT |
| TD-02 | Primary key | ULID `CHAR(26)` via `Str::ulid()` — sortable, URL-safe |
| TD-03 | Multi-tenant | Row-level isolation via `organization_id` global scope |
| TD-04 | Vector DB | Qdrant (bukan pgvector) |
| TD-05 | Pagination | Cursor-based (bukan offset) |
| TD-06 | Event bus | Redis Streams v1, upgrade ke Kafka saat scale |
| TD-07 | File storage | MinIO self-hosted S3-compatible |
| TD-08 | Realtime | Laravel Reverb (bukan Pusher berbayar) |

---

## Struktur Proyek

```
RuangASN/
├── app/
│   ├── Http/Controllers/        # API + Inertia controllers
│   ├── Models/                  # Eloquent models (extends BaseModel)
│   ├── Services/                # Business logic layer
│   ├── Policies/                # Authorization policies
│   └── Http/Requests/           # Form request validation
├── database/
│   ├── migrations/              # ULID-based migrations
│   ├── seeders/                 # RBAC seeder (40+ permissions, 5 roles)
│   └── factories/
├── resources/js/
│   ├── Pages/                   # Vue 3 Inertia pages
│   ├── Components/              # Shared UI components
│   └── Layouts/                 # AppLayout (sidebar + header)
├── routes/
│   ├── api.php                  # REST API v1 routes
│   └── web.php                  # Inertia web routes
├── tests/Feature/               # Feature tests (Auth, Task, RBAC, Tenant)
├── docker-compose.yml
└── docs/blueprint/              # 18 spesifikasi .md — sumber kebenaran tunggal
```

> Blueprint di `docs/blueprint/` adalah sumber kebenaran tunggal. Jika ada konflik antara kode dan blueprint, **blueprint menang**.

---

## Local Development

### Prasyarat

- Docker & Docker Compose
- PHP 8.4+, Composer
- Node.js 20+

### Setup

```bash
# 1. Clone & masuk ke direktori
git clone https://github.com/your-org/ruangasn.git
cd ruangasn

# 2. Jalankan semua service
docker compose up -d
# Services: PostgreSQL:5432, Redis:6379, MinIO:9000/9001,
#           Qdrant:6333, Mailpit:1025/8025, Reverb:8080

# 3. Install dependencies
composer install
npm install

# 4. Setup environment
cp .env.example .env
php artisan key:generate

# 5. Migrasi & seed database
php artisan migrate:fresh --seed

# 6. Jalankan frontend dev server
npm run dev
```

### Perintah Berguna

```bash
# Jalankan test suite
php artisan test

# Static analysis
vendor/bin/phpstan analyse

# Cek routes yang terdaftar
php artisan route:list

# Build frontend production
npm run build
```

---

## Roadmap

| Fase | Durasi | Deliverable | Status |
|---|---|---|---|
| **Fase 1 — Core** | Bulan 1–3 | Login MFA, Task Management, RBAC, Notifikasi | `v1.0.0` — In Progress |
| **Fase 2 — Workspace** | Bulan 4–6 | Meeting, Document, Calendar, Reporting | `v2.0.0` — Planned |
| **Fase 3 — AI & Kinerja** | Bulan 7–9 | SKP digital, AI Secretary/Meeting/Report Agents, RAG | `v3.0.0` — Planned |
| **Fase 4 — Advanced** | Bulan 10–12 | SIASN/SRIKANDI integration, Executive Dashboard, Chat | `v4.0.0` — Planned |

### Status Fase 1 (per 2026-06-13)

- ✅ Auth: login NIP/email, MFA TOTP, lockout, logout
- ✅ Multi-tenant scope (BelongsToOrganization)
- ✅ RBAC: 40+ permission, 5 role (via Spatie)
- ✅ Task Management: CRUD + state machine + evidence gate + outbox + audit
- ✅ Notifikasi in-app + Reverb WebSocket broadcast
- ✅ Audit log (immutable, hash chain)
- ✅ 14/14 feature test hijau (Auth, Task, RBAC, tenant isolation)
- 🔶 Frontend: Login + Dashboard + Task list (kanban & halaman detail belum)
- ⏳ Calendar CRUD, password reset, Flutter mobile — belum dimulai

---

## Keamanan

RuangASN menerapkan model **Zero Trust** berlapis:

```
Layer 1: Network       → Traefik + TLS 1.3, WAF
Layer 2: Autentikasi   → Laravel Sanctum + MFA (TOTP)
Layer 3: Otorisasi     → RBAC + Organization Scope
Layer 4: Aplikasi      → FormRequest validation, CSRF, XSS protection
Layer 5: Data          → Enkripsi kolom AES-256-GCM untuk data L4 (Restricted)
Layer 6: Storage       → MinIO SSE, Qdrant access control
Layer 7: Audit         → Immutable audit log dengan hash chain
```

**Highlights:**
- Data L4 (NIK, alamat, nomor telepon, MFA secret) dienkripsi di level kolom
- Brute force protection: 5 gagal → lockout 5 menit; 10 gagal → lockout permanen
- Rate limiting: 60 req/menit (API), 20 req/menit (AI), 5 req/menit (auth)
- Tidak ada hard delete — semua data domain menggunakan soft delete
- AI tidak bisa membuat/mengubah data tanpa konfirmasi eksplisit user

---

## Kepatuhan Regulasi

- **SPBE** (Sistem Pemerintahan Berbasis Elektronik) — Perpres 95/2018
- **UU PDP** (Perlindungan Data Pribadi) — UU No. 27/2022
- **PermenPANRB 6/2022** — Pengelolaan Kinerja ASN (SKP)

---

## Lisensi

Proprietary — Hak cipta dilindungi. Lihat file `LICENSE` untuk detail.
