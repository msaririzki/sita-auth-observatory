# UI/UX Blueprint — SITA Workload Identity Observatory

## 1. Tujuan Desain

Antarmuka harus membantu mahasiswa, dosen, dan penguji memahami proses
autentikasi tanpa membaca seluruh log GitHub Actions. Tampilan dibuat modern,
tenang, dan padat informasi. Kejelasan bukti lebih penting daripada dekorasi.

Prinsip utama:

- status selalu memakai ikon, teks, dan warna;
- nilai expected dan actual tidak dicampur;
- setiap angka dapat ditelusuri ke run dan artifact sumber;
- istilah teknis memiliki penjelasan singkat;
- identifier panjang dapat disalin tanpa memenuhi layar;
- filter dan tab tersimpan pada URL;
- tabel angka menggunakan tabular numerals;
- animasi singkat dan menghormati `prefers-reduced-motion`;
- seluruh alur utama dapat digunakan dengan keyboard;
- light dan dark theme memiliki kontras yang memadai.

## 2. Arah Visual

### Karakter

- profesional dan akademis;
- netral dengan aksen biru indigo;
- ruang putih cukup, tetapi tabel tetap efisien;
- sudut komponen sedang, bukan berlebihan;
- bayangan tipis dan border yang jelas;
- tidak menggunakan efek kaca, neon, atau gradien mencolok.

### Token Warna Semantik

| Fungsi | Warna | Catatan |
|---|---|---|
| Informasi | Biru | Navigasi aktif dan metadata |
| Lulus/Allow | Hijau | Selalu disertai teks/icon |
| Ditolak/Deny | Merah | Selalu disertai alasan |
| Peringatan | Amber | Data parsial atau faktor pengganggu |
| Tidak tersedia | Abu-abu | Tidak dihitung sebagai gagal |
| Integritas | Indigo | Digest dan provenance |

## 3. Struktur Navigasi

```text
Overview
Experiments
Runs
Policies
Evidence
Reports
Settings
```

Sidebar dapat diciutkan. Pada layar kecil, sidebar berubah menjadi drawer.
Header menampilkan project switcher, waktu sinkronisasi terakhir, theme switch,
dan menu pengguna.

## 4. Halaman Overview

```text
┌──────────────────────────────────────────────────────────────────┐
│ Workload Identity Observatory          Last sync 14:25 WITA      │
├──────────────────────────────────────────────────────────────────┤
│ 120 Runs   98.3% Accuracy   1.42 s Median   0 Static Secrets     │
├───────────────────────────────┬──────────────────────────────────┤
│ Accuracy by Configuration     │ Authentication Latency          │
│ OAuth   ████████ 78%          │ box plot per configuration       │
│ Basic   █████████ 91%         │                                  │
│ Multi   ██████████ 100%       │                                  │
├───────────────────────────────┼──────────────────────────────────┤
│ Scenario Matrix               │ Recent Runs                      │
│ heatmap allow/deny correctness│ status, run, SHA, duration       │
└───────────────────────────────┴──────────────────────────────────┘
```

Kartu ringkasan wajib memiliki periode data dan jumlah sampel. Angka tanpa
konteks tidak boleh ditampilkan.

## 5. Halaman Experiments

Menampilkan batch eksperimen dan progres pengulangan:

| Kolom | Isi |
|---|---|
| Experiment | Nama dan ID |
| SITA commit | Commit yang dikunci |
| Configurations | OAuth, WIF basic, WIF multi-claim |
| Scenarios | Jumlah skenario aktif |
| Repetitions | Selesai / target |
| Data quality | Complete, partial, atau invalid |
| Status | Draft, running, frozen, completed |

Detail eksperimen berisi matriks scenario × configuration × repetition.
Setiap sel dapat dibuka menuju run sumber.

## 6. Halaman Runs

Filter:

- rentang tanggal;
- konfigurasi;
- skenario;
- expected decision;
- actual decision;
- hasil klasifikasi;
- branch;
- jalur direct/DERP;
- kelengkapan bukti.

Kolom tabel:

```text
Status | Run ID | Configuration | Scenario | Expected | Actual |
Class | Auth latency | Cleanup | SHA | Started at
```

Filter, sort, pagination, dan kolom yang dipilih disimpan pada query parameter
agar URL dapat dibagikan kepada dosen.

## 7. Halaman Run Detail

Header menampilkan:

- keputusan akhir;
- expected decision;
- klasifikasi TP/TN/FP/FN;
- configuration dan scenario;
- run ID dan link GitHub;
- commit SHA;
- artifact digest;
- schema, Collector, policy, dan dashboard version.

Tab halaman:

1. Summary;
2. Timeline;
3. Claims;
4. Network;
5. Deployment;
6. Cleanup; dan
7. Raw Evidence.

### Timeline

Timeline menggunakan waterfall horizontal agar tahap yang lambat terlihat.
Setiap event menampilkan start, end, duration, status, dan reason code.

### Claims

| Claim | Expected | Actual | Result | Source |
|---|---|---|---|---|
| `iss` | GitHub issuer | GitHub issuer | Pass | OIDC |
| `aud` | Tailscale audience | Tailscale audience | Pass | OIDC |
| `ref` | `refs/heads/main` | `refs/heads/test` | Fail | Policy |

Identifier panjang dipotong secara visual dengan tombol copy. Nilai lengkap
tersedia melalui tooltip dan panel detail yang dapat diakses keyboard.

## 8. Halaman Policies

Menampilkan policy version sebagai data read-only:

```text
WIF Basic v1
  issuer       exact
  audience     exact
  subject      repository scope

WIF Multi-Claim v1
  issuer               exact
  audience             exact
  subject              exact
  repository_id        exact
  repository_owner_id  exact
  ref                   refs/heads/main
  job_workflow_ref      exact
```

Halaman ini tidak mengubah konfigurasi Tailscale. Tujuannya menampilkan policy
yang direkam pada saat eksperimen sehingga hasil lama tetap dapat dijelaskan.

## 9. Halaman Comparison

Visualisasi utama:

1. grouped bar chart Accuracy, FAR, dan FRR;
2. box plot total authentication latency;
3. heatmap scenario × configuration;
4. cleanup latency distribution;
5. valid authentication success rate;
6. long-lived confidential secret count; dan
7. direct versus DERP latency sebagai variabel pengganggu.

Grafik selalu disertai tabel data dan tombol ekspor CSV. Tooltip tidak menjadi
satu-satunya cara membaca nilai.

## 10. Halaman Evidence

Menampilkan:

- Artifact ID dan nama;
- GitHub run;
- SHA-256 digest;
- hasil validasi digest;
- schema version;
- ukuran;
- waktu impor;
- retention/expiry;
- status sanitasi;
- trace completeness; dan
- alasan karantina bila invalid.

Tindakan yang tersedia:

- Download sanitized JSON;
- Download CSV;
- Print Report;
- Open GitHub Run;
- Revalidate Evidence; dan
- View Import Audit.

## 11. Empty, Loading, dan Error State

### Empty

```text
Belum ada bukti eksperimen
Impor GitHub Actions Artifact atau jalankan eksperimen pertama.
[Import Artifact] [Open Setup Guide]
```

### Error

Pesan harus menyebutkan penyebab dan tindakan:

```text
Artifact tidak dapat diimpor
Digest hasil unduhan berbeda dari digest GitHub. Artifact ditempatkan dalam
karantina dan tidak masuk perhitungan.
[Lihat Detail Integritas]
```

### Stale Data

Dashboard menampilkan waktu sinkronisasi terakhir dan peringatan jika importer
tidak berhasil berjalan dalam batas waktu yang ditetapkan.

## 12. Responsive dan Aksesibilitas

- desktop menjadi target utama untuk analisis;
- tablet dan mobile tetap dapat membuka ringkasan serta detail run;
- tabel lebar memakai scroll container dengan kolom status tetap terlihat;
- setiap input memiliki label;
- icon-only button memiliki `aria-label`;
- focus ring selalu terlihat;
- status async menggunakan `aria-live="polite"`;
- warna tidak menjadi satu-satunya penanda;
- heading mengikuti urutan semantik;
- halaman menyediakan skip link;
- grafik memiliki tabel data alternatif;
- tanggal memakai `Intl.DateTimeFormat`;
- angka memakai `Intl.NumberFormat`; dan
- animasi dinonaktifkan atau dikurangi pada `prefers-reduced-motion`.

## 13. Prioritas Implementasi UI

### P0 — Wajib untuk Pilot

- layout dan navigasi;
- login;
- artifact upload;
- runs table;
- run detail;
- claim matrix;
- timeline;
- integrity status;
- JSON/CSV export.

### P1 — Wajib untuk Eksperimen Final

- experiments matrix;
- configuration comparison;
- Accuracy/FAR/FRR;
- latency distribution;
- cleanup analysis;
- print-friendly report;
- GitHub App synchronization.

### P2 — Setelah Data Final Stabil

- saved views;
- annotations/catatan peneliti;
- shareable read-only report;
- dark theme refinements;
- guided product tour.

P2 tidak boleh menghambat eksperimen atau pengolahan data.
