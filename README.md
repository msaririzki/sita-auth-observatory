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

Implementasi dimulai pada branch `codex/observatory-foundation`. Fondasi awal
mencakup Laravel 13, Inertia 3, React 19, model eksperimen berurutan, dan halaman
untuk merancang batch OAuth statis, WIF dasar, atau WIF multi-klaim.

## Status Implementasi

- Dashboard dan rancangan eksperimen berurutan tersedia.
- Backend dapat mengirim satu trial pertama melalui `workflow_dispatch` tanpa
  menaruh credential GitHub di browser.
- Klik ganda ditahan dengan penguncian status database dan permintaan dispatch
  tidak diulang otomatis.
- JSON Schema bukti v1 dan fixture TP/TN tersedia pada `schemas/` dan
  `fixtures/`.
- Algoritma klasifikasi TP, TN, FP, dan FN telah memiliki unit test.
- Workflow pilot WIF dasar disiapkan pada branch `codex/wif-poc` repositori
  SITA. OAuth statis dan WIF multi-klaim belum dinyatakan siap.

Validasi kontrak bukti dijalankan dengan:

```bash
npm run evidence:validate
```

## Production Docker deployment

The production stack runs Laravel behind an Nginx container and binds the web
service only to the host loopback interface. Copy `.env.example` to
`.env.production`, set production values, and run:

```bash
docker compose -f compose.production.yml up -d --build
```

The default origin is `http://127.0.0.1:8012`. A reverse proxy or Cloudflare
Tunnel can publish that origin without exposing the container port directly.
