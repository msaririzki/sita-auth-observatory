# ADR 001: Isolasi Kebijakan Akses untuk Laboratorium WIF SITA

- Status: Proposed
- Tanggal: 18 September 2026
- Pemilik: Muhamad Sari Rizki

## Konteks

Pilot OAuth statis dan WIF dasar berjalan pada tailnet utama. Audit pada 18
September 2026 menunjukkan tailnet tersebut memuat 28 perangkat untuk berbagai
kebutuhan. Target penelitian `sita-docker` sudah diberi `tag:sita-target`, namun
kebijakan lama masih memiliki grant universal dari `*` ke `*`.

Aturan universal membuat rule yang lebih khusus tetap dapat dicatat dan diuji,
namun tidak dapat dijadikan bukti bahwa akses jaringan telah disegmentasi secara
ketat. Menghapus aturan universal tanpa memetakan kebutuhan 27 perangkat lain
berisiko memutus layanan yang tidak terkait penelitian.

## Keputusan

Eksperimen final, terutama skenario penolakan target atau port, dijalankan pada
lingkungan laboratorium dengan kebijakan yang tidak memuat grant universal.
Pilihan yang diprioritaskan adalah tailnet laboratorium terpisah. Bila itu belum
dapat disediakan, kebijakan ketat hanya diterapkan setelah inventaris akses
tailnet utama selesai dan dalam maintenance window terjadwal.

Tidak ada penghapusan grant atau perubahan kebijakan akses dilakukan oleh ADR
ini.

## Batas Akses Laboratorium

| Sumber | Tujuan | Protokol | Aksi yang diizinkan |
| --- | --- | --- | --- |
| `tag:ci-oauth-static` | `tag:sita-target` | TCP/22 | Tailscale SSH sebagai `ServerDeploy` |
| `tag:ci-wif-basic` | `tag:sita-target` | TCP/22 | Tailscale SSH sebagai `ServerDeploy` |
| `tag:ci-wif-multiclaim` | `tag:sita-target` | TCP/22 | Tailscale SSH sebagai `ServerDeploy` |

Node sementara hanya boleh memiliki satu tag sesuai profil eksperimen. Target
SITA tidak diberi akses keluar tambahan melalui kebijakan penelitian. Akses
`root`, port selain 22, target selain `tag:sita-target`, dan tag lain harus
ditolak.

## Template Kebijakan Kandidat

Template ini ditulis untuk Preview rules dan test kebijakan. Template belum boleh
menimpa kebijakan aktif tailnet utama.

```jsonc
{
  "tagOwners": {
    "tag:ci-oauth-static": ["autogroup:admin"],
    "tag:ci-wif-basic": ["autogroup:admin"],
    "tag:ci-wif-multiclaim": ["autogroup:admin"],
    "tag:sita-target": ["autogroup:admin"]
  },
  "grants": [
    {"src": ["tag:ci-oauth-static"], "dst": ["tag:sita-target"], "ip": ["tcp:22"]},
    {"src": ["tag:ci-wif-basic"], "dst": ["tag:sita-target"], "ip": ["tcp:22"]},
    {"src": ["tag:ci-wif-multiclaim"], "dst": ["tag:sita-target"], "ip": ["tcp:22"]}
  ],
  "ssh": [
    {"action": "accept", "src": ["tag:ci-oauth-static"], "dst": ["tag:sita-target"], "users": ["ServerDeploy"]},
    {"action": "accept", "src": ["tag:ci-wif-basic"], "dst": ["tag:sita-target"], "users": ["ServerDeploy"]},
    {"action": "accept", "src": ["tag:ci-wif-multiclaim"], "dst": ["tag:sita-target"], "users": ["ServerDeploy"]}
  ],
  "sshTests": [
    {"src": "tag:ci-oauth-static", "dst": ["tag:sita-target"], "accept": ["ServerDeploy"], "deny": ["root"]},
    {"src": "tag:ci-wif-basic", "dst": ["tag:sita-target"], "accept": ["ServerDeploy"], "deny": ["root"]},
    {"src": "tag:ci-wif-multiclaim", "dst": ["tag:sita-target"], "accept": ["ServerDeploy"], "deny": ["root"]}
  ]
}
```

## Protokol Penerapan

1. Catat daftar perangkat, tag, dan jalur akses yang sedang digunakan.
2. Buat tailnet laboratorium terpisah atau peta seluruh grant perangkat lama.
3. Terapkan template sebagai kandidat dan jalankan Preview rules untuk setiap
   jalur allow dan deny di tabel di atas.
4. Uji satu pilot per profil autentikasi sebelum eksperimen akhir.
5. Rekam versi policy, hasil test, dan waktu penerapan ke Observatory.
6. Baru jalankan S01--S10 minimal lima kali per konfigurasi yang relevan.

## Referensi Teknis\n\n- [Tailscale Tailnets API](https://tailscale.com/docs/features/tailnets-api)\n- [Tailscale Workload Identity Federation](https://tailscale.com/docs/features/workload-identity-federation)\n- [Tailscale GitHub Action](https://tailscale.com/docs/integrations/github/github-action)\n\n## Konsekuensi

Eksperimen final akan memiliki bukti eksplisit untuk sukses dan penolakan akses.
Waktu penyiapan bertambah karena perlu isolasi atau inventaris. Namun hasilnya
lebih valid untuk menyimpulkan pengaruh WIF multi-klaim dibanding sekadar
berhasil terhubung pada jaringan yang terbuka luas.