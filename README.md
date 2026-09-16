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

Implementasi belum dimulai. Keputusan arsitektur, data, metrik, batas keamanan,
dan tahapan pengerjaan harus mengikuti dokumen perencanaan tersebut.
