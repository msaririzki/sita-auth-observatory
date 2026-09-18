# SITA Workload Identity Observatory

SITA Workload Identity Observatory adalah instrumen penelitian untuk merekam,
memvalidasi, mengolah, dan memvisualisasikan bukti autentikasi automated
deployment SITA melalui tiga konfigurasi:

1. OAuth statis;
2. Workload Identity Federation (WIF) dasar; dan
3. WIF multi-klaim berbasis GitHub Actions OpenID Connect (OIDC).

Repositori ini tidak berisi aplikasi SITA dan tidak menjadi pengambil keputusan
akses. SITA tetap berada pada repositori `msaririzki/sita`, sedangkan repositori
ini menjadi alat ukur independen agar kegagalan deployment tidak menghilangkan
bukti penelitian.

## Dokumen Perencanaan

- [Implementation Plan](docs/IMPLEMENTATION_PLAN.md)
- [UI/UX Blueprint](docs/UI_UX_BLUEPRINT.md)
- [ADR 001: Isolasi Kebijakan Akses Laboratorium](docs/ADR/001-laboratory-tailnet-isolation.md)

Implementasi berada pada branch `codex/observatory-foundation`. Fondasi memakai
Laravel 13, PHP 8.4, Inertia 3, React 19, dan database SQLite pada volume
Docker. Aplikasi SITA tetap berjalan pada repositori terpisah.

## Status Implementasi

- Dashboard dan rancangan eksperimen berurutan tersedia.
- GitHub App memicu `workflow_dispatch` tanpa menaruh credential GitHub di
  browser.
- Batch menjalankan satu trial pada satu waktu. Setelah bukti trial tervalidasi,
  worker database queue menjadwalkan trial berikutnya setelah jeda eksperimen.
- Jalur deployment hanya dipakai satu eksperimen aktif pada satu waktu.
- JSON Schema bukti v1 dan fixture TP/TN tersedia pada `schemas/` dan
  `fixtures/`.
- Algoritma klasifikasi TP, TN, FP, dan FN telah memiliki unit test.
- Pilot OAuth statis, WIF dasar, dan WIF multi-klaim telah membuktikan jalur
  autentikasi yang diizinkan. Skenario penolakan WIF multi-klaim untuk branch
  yang tidak diizinkan juga telah tercatat sebagai TN; seluruh pilot tetap
  dipisahkan dari data perbandingan final.

Validasi kontrak bukti dijalankan dengan:

```bash
npm run evidence:validate
```

## Production Docker deployment

The production stack runs Laravel behind an Nginx container, an isolated queue
worker, and binds the web service only to the host loopback interface. Copy `.env.example` to
`.env.production`, set production values, and run:

```bash
docker compose -f compose.production.yml up -d --build
```

The default origin is `http://127.0.0.1:8012`. A reverse proxy or Cloudflare
Tunnel can publish that origin without exposing the container port directly.
