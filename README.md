# RuangASN

## Digital Workspace untuk Aparatur Sipil Negara (ASN) Indonesia

RuangASN menyatukan tugas harian, kolaborasi rapat, manajemen dokumen, kalender, pelaporan, basis pengetahuan, kinerja (SKP), dan asisten AI dalam satu workspace terpadu — *One ASN, One Workspace*.

> **Status:** Fase 1, 2, dan 3 **selesai & teruji** — **192 feature test hijau**, PHPStan level 5 bersih, build produksi bersih. Fase 4 (integrasi & advanced) dalam perencanaan.

![phase](https://img.shields.io/badge/Fase_1–3-Selesai-10B981) ![tests](https://img.shields.io/badge/Feature_Tests-192_passed-3B82F6) ![phpstan](https://img.shields.io/badge/PHPStan-level_5_clean-8B5CF6) ![laravel](https://img.shields.io/badge/Laravel-12-FF2D20) ![vue](https://img.shields.io/badge/Vue-3_+_Inertia-42B883)

---

## Daftar Isi

- [Tentang](#tentang)
- [Status Implementasi](#status-implementasi)
- [Fitur per Modul](#fitur-per-modul)
- [Tech Stack](#tech-stack)
- [Arsitektur & Invarian](#arsitektur--invarian)
- [Design System (Dual Mode)](#design-system-dual-mode)
- [Struktur Proyek](#struktur-proyek)
- [Local Development](#local-development)
- [Pengujian & Kualitas](#pengujian--kualitas)
- [Roadmap](#roadmap)
- [Keamanan](#keamanan)
- [Kepatuhan Regulasi](#kepatuhan-regulasi)
- [Lisensi](#lisensi)

---

## Tentang

RuangASN adalah platform **modular monolith** yang memecahkan masalah siloisasi aplikasi di pemerintahan daerah (OPD). Satu titik masuk bagi seluruh ASN — dari staf pelaksana hingga kepala dinas — untuk bekerja, berkolaborasi, dan melaporkan kinerja secara digital, aman, dan patuh regulasi nasional (SPBE & UU PDP).

Repository ini adalah **satu folder gabungan**: kode aplikasi Laravel ada di root, dan spesifikasi blueprint (`docs/blueprint/`, 18 file `.md` + `DESIGN.md`) adalah **sumber kebenaran tunggal** — saat ada konflik antara kode dan blueprint, blueprint menang.

**Target pengguna:**

| Persona | Peran |
|---|---|
| Super Admin | Admin provinsi/kabupaten/kota, konfigurasi global & pendaftaran OPD |
| Admin Pemda/OPD | Pengelola kepegawaian/IT dinas, konfigurasi SOTK, manajemen pengguna, audit |
| Pimpinan | Kepala Dinas/Bidang/Seksi — pantau kinerja staf, delegasi tugas, approval, evaluasi SKP |
| Staf ASN | Pelaksana — kelola tugas harian, upload bukti kerja, isi realisasi SKP |
| Sekretariat | Notulis rapat — kelola risalah, distribusi action items |

---

## Status Implementasi

| Fase | Lingkup | Status |
|---|---|---|
| **Fase 1 — Core** | Auth/MFA, RBAC, Multi-tenant, Task, Notifikasi, Scheduler, Profil/Settings, Admin | ✅ **Selesai** |
| **Fase 2 — Workspace** | Meeting, Document, Calendar, Reporting, Knowledge Base, Search | ✅ **Selesai** |
| **Fase 3 — AI & Kinerja** | SKP/Performance, AI Foundation (orchestrator, RAG, 5 agen, confirm-flow) | ✅ **Selesai** |
| **Fase 4 — Advanced** | Executive Dashboard, integrasi SIASN/SRIKANDI, Chat, WhatsApp, Mobile | 🔜 Direncanakan |

**Metrik kode saat ini:** 40 model · 20 migrasi · 40 service · 36 enum · 164 route · 34 halaman Vue · 23 file feature test (**192 test, 568 assertions**).

**Gate kualitas (terverifikasi):**

| Gate | Hasil |
|---|---|
| Feature test (`php artisan test`) | ✅ 192 passed |
| Static analysis (`phpstan` level 5 + Larastan) | ✅ No errors |
| `migrate:fresh --seed` di PostgreSQL 18 | ✅ Sukses |
| Build produksi (`vite build`) | ✅ Bersih |

Setiap modul memenuhi **Definition of Done**: migration, model, factory, policy, validasi, resource, service, controller, route, permission (RBAC), organization scope, soft delete, audit/outbox, dan feature test (happy path + RBAC allow/deny + tenant isolation + state machine).

---

## Fitur per Modul

### Fase 1 — Core

- **Autentikasi & MFA** — login NIP/email + password, TOTP (Google Authenticator) + 10 backup code, lockout brute-force (5 gagal → kunci 5 menit), reset password via email, manajemen sesi (revokasi token)
- **Multi-Tenant Isolation** — isolasi data mutlak antar-OPD via `organization_id` global scope (`BelongsToOrganization`)
- **RBAC** — Spatie Permission, 5 role + 80+ permission per matriks; permission-first di setiap endpoint
- **Task Management** — Kanban + List + halaman detail; state machine lengkap (`draft → open → assigned → in_progress → waiting_review → completed → closed → archived`); checklist, komentar, **evidence wajib** sebelum `completed`; upload bukti
- **Notifikasi** — pusat notifikasi + dropdown bell, kanal email, preferensi per-tipe, **realtime via Laravel Reverb** (WebSocket, Echo listener)
- **Scheduler** — deteksi tugas overdue (→ notifikasi) & generate tugas berulang (terjadwal harian)
- **Profil & Settings** — profil, ganti password, kelola MFA, tema (dual mode), preferensi notifikasi, sesi aktif
- **Admin** — manajemen pengguna (CRUD + role), pohon struktur organisasi, audit log viewer, tim + anggota, delegasi (Plt./Plh.), jabatan
- **Audit Trail** — seluruh mutasi penting & aksi AI dicatat (audit log + outbox event)

### Fase 2 — Workspace

- **Meeting Workspace** — penjadwalan, state machine 8-status, agenda, peserta + RSVP, keputusan, **action item → auto-create Task**, notulensi (rich text), **absensi via QR code** (check-in tertandatangani), **auto-create event kalender** saat dijadwalkan
- **Document Workspace** — versioning, klasifikasi keamanan **L1–L4** (akses need-to-know), approval workflow bertingkat, **antrean persetujuan**, **download bertandatangan + watermark** (gambar L3/L4) + audit, **PDF viewer in-app** dengan overlay watermark
- **Calendar** — tampilan bulan/agenda, **feed terpadu** menggabungkan event + meeting + task; buat/edit acara
- **Reporting** — workflow `draft → submitted → in_review → approved → published`, riwayat status, draf AI
- **Knowledge Base** — wiki (9 tipe: SOP/FAQ/best-practice/…), kategori, versioning, editor **TipTap**, view/helpful count
- **Search** — pencarian full-text (PostgreSQL `to_tsvector` + fallback LIKE) lintas Task/Meeting/Document/Report/Knowledge, **permission-aware** & tenant-scoped; **Command Palette ⌘K**

### Fase 3 — AI & Kinerja

- **Performance / SKP** — sesuai **PermenPANRB 6/2022**: periode, rencana, indikator (4 perspektif), realisasi (tertaut task/dokumen), evaluasi atasan, dan `SkpCalculationService` (capaian per-IKI cap 120, skor kinerja tertimbang, skor perilaku 5 dimensi, nilai akhir 70% kinerja + 30% perilaku, predikat sangat_baik…sangat_kurang); dashboard, editor, input realisasi, form evaluasi, analitik atasan
- **AI Foundation** — fondasi asisten AI yang patuh invarian:
  - **Provider abstraction + fallback chain** — Gemini / Claude / OpenAI / Qwen / dst. (pluggable); default dev memakai *fake provider* deterministik sehingga seluruh pipeline teruji tanpa API key
  - **RAG + citations** — retrieval lintas knowledge/dokumen/meeting/laporan (tenant + permission scoped), jawaban menyertakan sumber
  - **Orchestrator + IntentClassifier + 5 agen** — Secretary, Meeting, Report, Knowledge, Performance
  - **Draf AI human-review** — draf notulensi rapat & draf laporan dibuat AI lalu **diedit manusia** (tak pernah auto-save)
  - **AXIOM-04 (confirm-before-execute)** — AI hanya mengusulkan `proposed_actions`; `confirmAction` adalah **satu-satunya** jalur eksekusi, dijalankan lewat policy pengguna (no privilege escalation), **idempotent** (double-confirm ditolak, tanpa duplikat)
  - **AXIOM-06 (traced)** — setiap interaksi (query/route/response/action) tercatat di `ai_interaction_logs`
  - **UI** — floating AI panel, kartu konfirmasi aksi (Setuju/Tolak), tampilan citation, pengaturan memori AI

> **Catatan cakupan Fase 3:** transkripsi audio rapat (Whisper), vector search Qdrant penuh, dan kunci LLM produksi adalah *drop-in* di atas abstraksi yang sudah ada (saat ini retrieval berbasis FTS DB + fake provider untuk reprodusibilitas & pengujian).

### Fase 4 — Direncanakan

Executive Dashboard (Bupati/Walikota), integrasi SIASN & SRIKANDI, SSO Nasional (SAML/OIDC), Chat kolaborasi in-app, notifikasi WhatsApp Business API, semantic search skala penuh, dan aplikasi Mobile (Flutter).

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| **Backend** | Laravel 12, PHP 8.4+, Laravel Sanctum (auth), Spatie Permission (RBAC) |
| **Web Frontend** | Vue 3 (Composition API), Inertia.js, TypeScript, TailwindCSS 4, Lucide |
| **Rich Text** | TipTap (editor notulensi/laporan/knowledge) |
| **Database** | PostgreSQL 18 (primary), Redis 7 (cache/queue/session/event bus, via predis) |
| **Object Storage** | Local disk (dev) → MinIO / S3 (produksi) |
| **Realtime** | Laravel Reverb (WebSocket) |
| **Search** | PostgreSQL Full-Text Search (`to_tsvector`) + fallback LIKE |
| **AI (pluggable)** | Provider abstraction: Gemini / Claude / OpenAI / Qwen / DeepSeek / Llama / Mistral; *fake provider* deterministik untuk dev/test |
| **QR** | `bacon/bacon-qr-code` (absensi meeting) |
| **Mobile** | Flutter 3.24 (Fase 4) |
| **Static Analysis** | PHPStan level 5 + Larastan |

---

## Arsitektur & Invarian

### Invarian Arsitektur (TIDAK BOLEH dilanggar)

| # | Aturan |
|---|---|
| 1 | **ULID everywhere** — semua primary key `CHAR(26)` via `Str::ulid()` |
| 2 | **Multi-tenant always** — setiap query domain memakai `BelongsToOrganization` global scope |
| 3 | **Permission first** — `authorize()` sebelum query data di setiap endpoint |
| 4 | **Soft delete only** — tidak ada hard delete untuk data domain |
| 5 | **Audit via outbox** — state change penting publish event ke outbox |
| 6 | **No magic strings** — semua status/tipe/kanal pakai PHP Enum |
| AXIOM-04 | **AI cannot decide** — AI hanya menghasilkan `proposed_actions`, eksekusi butuh konfirmasi user & lewat permission user |
| AXIOM-06 | **Nothing important without trace** — setiap aksi AI tercatat di `ai_interaction_logs` |

### Pola Kode Utama

**Base Model** — setiap model domain extends ini:
```php
abstract class BaseModel extends Model {
    use BelongsToOrganization, SoftDeletes, HasUlid;
    public $incrementing = false;
    protected $keyType = 'string'; // ULID CHAR(26)
}
```

**Service Layer** — business logic dalam `DB::transaction()` + outbox + audit:
```php
public function create(array $data, User $creator): Task {
    return DB::transaction(function () use ($data, $creator) {
        $task = Task::create([...$data, 'organization_id' => $creator->organization_id]);
        $this->outbox->publish('task.created', $task->fresh()->toArray(), 'Task', $task->id);
        return $task;
    });
}
```

**Controller** — `authorize()` selalu di baris pertama:
```php
public function store(StoreTaskRequest $request, TaskService $service) {
    $this->authorize('create', Task::class);
    return TaskResource::make($service->create($request->validated(), $request->user()));
}
```

---

## Design System (Dual Mode)

Seluruh UI **wajib mendukung dual mode dark + light**. Acuan kanonikal: [`docs/blueprint/DESIGN.md`](docs/blueprint/DESIGN.md).

- **Token via CSS custom properties** (`var(--bg-primary)`, `var(--card-bg)`, `var(--text-primary)`, …) — berganti otomatis lewat atribut `data-theme="dark|light"` pada `<html>`
- **Font:** Plus Jakarta Sans (UI) + JetBrains Mono (kode)
- **Warna brand:** primary `#3B82F6`, secondary/AI `#8B5CF6`, success `#10B981`, warning `#F59E0B`, danger `#EF4444`
- **Toggle tema** tersimpan di `localStorage` (`ruangasn-theme`), tersedia di halaman login & topbar
- Sebelum membuat/memodifikasi komponen Vue, baca `docs/blueprint/DESIGN.md`, `resources/css/app.css`, dan `resources/js/Pages/Dashboard.vue` sebagai acuan

---

## Struktur Proyek

```
RuangASN/                          ← git root + Laravel app root
├── app/
│   ├── Enums/                     # 36 PHP enum (status, tipe, kanal, AI, SKP)
│   ├── Http/Controllers/          # Inertia web + API v1 + Admin/* + Auth/*
│   ├── Models/                    # 40 Eloquent model (extends BaseModel)
│   ├── Services/                  # business logic; Services/Ai/* (orchestrator, agen, provider, RAG)
│   ├── Policies/                  # authorization policies
│   └── Console/Commands/          # scheduler (overdue, recurring)
├── config/ai.php                  # konfigurasi provider AI + fallback chain
├── database/
│   ├── migrations/                # 20 migrasi berbasis ULID
│   ├── seeders/                   # RbacSeeder (5 role, 80+ permission), Organization, User
│   └── factories/
├── resources/
│   ├── css/app.css                # token CSS custom properties (dual mode)
│   └── js/
│       ├── Pages/                 # 34 halaman Vue 3 Inertia (Tasks, Meetings, Documents, …, Ai, Performance)
│       ├── components/            # RichTextEditor, ai/*, CommandPalette
│       ├── composables/           # useTheme, useNotifications, useAiChat
│       └── Layouts/AppLayout.vue  # app shell (sidebar + topbar + AI panel)
├── routes/
│   ├── web.php                    # route Inertia (164 route)
│   └── api.php                    # REST API v1
├── tests/Feature/                 # 23 file feature test (192 test)
├── phpstan.neon                   # PHPStan level 5 + Larastan
├── docker-compose.yml             # opsional (jalur utama dev = native)
└── docs/blueprint/                # 18 spesifikasi .md + DESIGN.md — sumber kebenaran tunggal
```

> Blueprint di `docs/blueprint/` adalah sumber kebenaran tunggal. Jika ada konflik antara kode dan blueprint, **blueprint menang**.

---

## Local Development

Lingkungan dev utama bersifat **native (tanpa Docker)** di macOS. PostgreSQL 18 + Redis berjalan sebagai layanan native. `docker-compose.yml` disertakan sebagai opsi alternatif.

### Prasyarat

- PHP 8.4+ & Composer
- Node.js 20+ & npm
- PostgreSQL 18 (port 5432)
- Redis 7 (cache/queue/session)

### Setup

```bash
# 1. Layanan native (sekali setup)
brew services start redis
# PostgreSQL 18 berjalan sebagai daemon di port 5432

# 2. Buat DB + user (sekali; butuh superuser postgres)
/Library/PostgreSQL/18/bin/psql -h 127.0.0.1 -U postgres -d postgres <<'SQL'
CREATE ROLE "RuangASN_usr" WITH LOGIN PASSWORD 'secret';
CREATE DATABASE "RuangASN_db" OWNER "RuangASN_usr";
\connect "RuangASN_db"
GRANT ALL ON SCHEMA public TO "RuangASN_usr";
SQL

# 3. Dependencies
composer install
npm install

# 4. Environment
cp .env.example .env
php artisan key:generate

# 5. Migrasi & seed
php artisan migrate:fresh --seed

# 6. Jalankan app (web + queue + vite) atau serve saja
composer run dev          # atau: php artisan serve & npm run dev
```

**Catatan environment:** `DB_CONNECTION=pgsql` (`RuangASN_db` / `RuangASN_usr` / 5432); cache/queue/session pakai Redis via **predis** (`REDIS_CLIENT=predis`, tanpa ekstensi phpredis); `FILESYSTEM_DISK=local` + `EVIDENCE_DISK=local` (ganti ke `s3` untuk MinIO/produksi); `MAIL_MAILER=log`. Realtime opsional: `php artisan reverb:start`. AI default memakai *fake provider* (tanpa API key); set `AI_PROVIDER` + kunci terkait di `config/ai.php` untuk provider nyata.

### Perintah Berguna

```bash
php artisan test                              # feature test (SQLite in-memory)
php -d memory_limit=1G vendor/bin/phpstan analyse   # static analysis
php artisan route:list                        # daftar route
php artisan schedule:list                     # tugas terjadwal (overdue, recurring)
npm run build                                 # build frontend produksi
```

---

## Pengujian & Kualitas

- **Feature test** dijalankan di SQLite in-memory (tanpa butuh PG/Redis), mencakup happy path, RBAC allow/deny, tenant isolation, state machine, dan invarian AI.
- **Test wajib lintas fase yang hijau:** Task lifecycle + evidence gate · approval dokumen · signed download · kontrol akses L3/L4 · reporting workflow · meeting action-item→task · **SKP calculation** (boundary predikat) · **AI proposed-action confirmation** · **RAG citation** · **provider fallback** · **AI audit log**.
- **Review adversarial multi-lensa** dijalankan pada modul SKP & AI; temuan nyata (mis. guard idempotensi `confirmAction`, overflow kolom skor perilaku di PostgreSQL, isolasi child-resource SKP) telah diperbaiki & ditutup test regresi.

---

## Roadmap

| Fase | Deliverable | Versi | Status |
|---|---|---|---|
| **Fase 1 — Core** | Auth/MFA, RBAC, Task, Notifikasi, Scheduler, Profil, Admin | `v1.0.0` | ✅ Selesai |
| **Fase 2 — Workspace** | Meeting, Document, Calendar, Reporting, Knowledge, Search | `v2.0.0` | ✅ Selesai |
| **Fase 3 — AI & Kinerja** | SKP digital, AI Foundation (RAG, agen, confirm-flow) | `v3.0.0` | ✅ Selesai |
| **Fase 4 — Advanced** | Executive Dashboard, SIASN/SRIKANDI, Chat, WhatsApp, Mobile | `v4.0.0` | 🔜 Direncanakan |

---

## Keamanan

Model **Zero Trust** berlapis:

```text
Layer 1: Autentikasi   → Laravel Sanctum + MFA (TOTP) + lockout
Layer 2: Otorisasi     → RBAC (Spatie) + Organization Scope (permission-first)
Layer 3: Aplikasi      → validasi request, CSRF, soft-delete, state machine
Layer 4: Data          → klasifikasi L1–L4, akses need-to-know, signed URL + watermark
Layer 5: AI Safety     → AXIOM-04 (confirm-before-execute, no escalation) + AXIOM-06 (traced)
Layer 6: Audit         → audit log + outbox event di setiap mutasi penting
```

**Highlights:**

- Brute force: 5 gagal → lockout 5 menit
- Maks upload evidence 20 MB; timezone disimpan UTC (I/O ISO 8601, default `Asia/Jakarta`)
- Tidak ada hard delete — semua data domain soft delete
- Download dokumen terklasifikasi: URL bertandatangan (TTL pendek) + audit + watermark
- AI mewarisi permission user aktif; tidak bisa membuat/mengubah/menghapus data atau approve tanpa konfirmasi eksplisit

---

## Kepatuhan Regulasi

- **SPBE** (Sistem Pemerintahan Berbasis Elektronik) — Perpres 95/2018
- **UU PDP** (Perlindungan Data Pribadi) — UU No. 27/2022
- **PermenPANRB 6/2022** — Pengelolaan Kinerja ASN (SKP)

---

## Lisensi

Proprietary — Hak cipta dilindungi. Lihat file `LICENSE` untuk detail.
