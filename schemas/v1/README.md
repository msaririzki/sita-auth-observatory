# Trial Evidence Schema v1

`trial-evidence.schema.json` adalah kontrak bukti untuk satu pengulangan
eksperimen. Berkas bukti hanya memuat metadata dan klaim yang telah disanitasi.
JWT, OAuth secret, auth key, cookie, dan header otorisasi dilarang masuk.

Aturan interpretasi keputusan:

- `allow`: autentikasi diterima, node berhasil bergabung, dan target yang
  diizinkan dapat dijangkau;
- `deny`: autentikasi ditolak, node gagal bergabung, atau kebijakan menolak
  target;
- `TP`: expected allow dan actual allow;
- `TN`: expected deny dan actual deny;
- `FP`: expected deny tetapi actual allow; dan
- `FN`: expected allow tetapi actual deny.

Schema version dibekukan selama satu batch eksperimen. Perubahan field atau
semantik membutuhkan versi schema baru agar hasil lama tetap dapat direproduksi.
