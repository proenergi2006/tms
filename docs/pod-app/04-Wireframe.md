# Wireframe Document — POD ProEnergi

Dokumen ini berisi wireframe (low-fidelity) untuk layar-layar utama aplikasi POD ProEnergi, sebagai visualisasi dari daftar layar yang telah didefinisikan pada [Design Document](03-Design-Document.md) Bagian 5.2. Tujuannya adalah menyelaraskan pemahaman mengenai layout, penempatan komponen, dan alur interaksi sebelum tim mobile membangun tampilan final menggunakan Flutter.

Wireframe ini bersifat skematik — warna, tipografi, dan detail visual akhir mengikuti tema Biru & Oranye final (PRD Bagian 9.1, menunggu konfirmasi brand guideline), bukan mengikuti tampilan pada dokumen ini secara literal.

## 1.1 Panduan Membaca Wireframe

- Warna **biru** menandai elemen primer (header, tombol utama, navigasi aktif).
- Warna **oranye** menandai aksi/status yang perlu perhatian (badge "on-progress", tombol slide-to-confirm saat aktif).
- Warna **hijau** menandai status/aksi selesai (badge "closed", centang aktivitas selesai).
- Warna **abu-abu** menandai elemen nonaktif/disabled (tombol "Selesai" sebelum foto & tanda tangan lengkap).
- Kotak putus-putus dengan ikon kamera merepresentasikan placeholder foto/media yang belum diisi.
- Pita horizontal dengan gagang di ujung merepresentasikan komponen slide-to-confirm (PRD Bagian 9.2).

## 2. Aplikasi Mobile (Flutter) — Driver

### 2.1 Login

Gambar 1. Wireframe Login. _(belum dilampirkan)_

- **Deskripsi:** Satu layar bersih: logo POD ProEnergi, input Username, input PIN (dengan toggle tampilkan/sembunyikan), tombol Login, nomor versi aplikasi di footer (PRD FR-1).
- **Komponen utama:** Logo terpusat, dua text field, tombol Login full-width warna Biru, teks versi kecil di bawah.
- **Interaksi kunci:** Tombol Login menampilkan loading state saat submit; kredensial salah menampilkan pesan error generik di bawah tombol (tidak membedakan username/PIN salah); PIN terkunci menampilkan pesan berbeda mengarahkan ke IT/Admin.
- **Role akses:** Driver (satu-satunya pengguna aplikasi).

### 2.2 Beranda & Kartu Shipment

Gambar 2. Wireframe Beranda. _(belum dilampirkan)_

- **Deskripsi:** Layar utama setelah login — sapaan ke driver, ikon Riwayat di header, daftar kartu shipment yang ditugaskan (PRD FR-2).
- **Komponen utama:** Header dengan avatar + nama driver + ikon jam/riwayat; kartu shipment (nomor shipment, badge status berwarna, plat nomor, nama customer, tanggal, ringkasan titik lokasi); tombol "Mulai" pada kartu shipment yang belum berjalan; bottom navigation (Home, Profil).
- **Interaksi kunci:** Tap kartu membuka Detail Shipment (2.3); tap tombol "Mulai" langsung memicu `POST /shipments/{id}/start` lalu berpindah ke 2.3; ikon Riwayat membuka 2.8.
- **Role akses:** Driver.

### 2.3 Detail Shipment & Navigasi

Gambar 3. Wireframe Detail Shipment. _(belum dilampirkan)_

- **Deskripsi:** Informasi lengkap shipment yang sedang berjalan, indikator progres dua titik (muat/bongkar), ikon petunjuk arah ke Google Maps (PRD FR-3, FR-4).
- **Komponen utama:** Header dengan nomor shipment + ikon petunjuk arah; indikator dua titik (loading — aktif, unloading — belum); info customer, alamat, plat nomor; tombol "Sampai Lokasi" (mengarah ke 2.4) bila belum check-in, atau tombol lanjut ke Report bila sudah check-in.
- **Interaksi kunci:** Tap ikon petunjuk arah memunculkan dialog konfirmasi sebelum membuka Google Maps (deep link); kembali dari Google Maps mengembalikan ke layar ini tanpa reset state.
- **Role akses:** Driver.

### 2.4 Check-in "Sampai Lokasi"

Gambar 4. Wireframe Check-in. _(belum dilampirkan)_

- **Deskripsi:** Konfirmasi kedatangan driver di titik lokasi (muat/bongkar) memakai pola slide-to-confirm, bukan tap tunggal (PRD FR-5, FR-11, Bagian 9.2).
- **Komponen utama:** Ringkasan lokasi tujuan; komponen slide horizontal berlabel "Geser untuk Sampai Lokasi"; indikator field waktu (Masuk/Mulai/Selesai/Keluar) yang masih kosong.
- **Interaksi kunci:** Slide penuh memicu `POST .../check-in`, mengisi Waktu Masuk & Mulai, lalu otomatis berpindah ke Report (2.5).
- **Role akses:** Driver.

### 2.5 Report Aktivitas (Muat/Bongkar)

Gambar 5. Wireframe Report Aktivitas. _(belum dilampirkan)_

- **Deskripsi:** Layar utama pencatatan bukti selama aktivitas berlangsung — field waktu, catatan inline, akses ke Media dan Tanda Tangan, tombol slide "Selesai" (PRD FR-6, FR-9, FR-12).
- **Komponen utama:** Header info shipment + ikon petunjuk arah; card field waktu (Masuk/Mulai/Selesai/Keluar); textarea Catatan inline (autosave); tombol "Ambil Foto Bukti" dengan badge jumlah foto; tombol "Ambil Tanda Tangan" dengan indikator tersimpan/belum; komponen slide "Selesai Muat"/"Selesai Bongkar" (abu-abu/disabled hingga syarat terpenuhi).
- **Interaksi kunci:** Tap tombol Foto membuka 2.6; tap tombol Tanda Tangan membuka 2.7; slide "Selesai" hanya aktif (berwarna Oranye) setelah minimal 1 foto + 1 tanda tangan tersimpan, memicu `POST .../complete`.
- **Role akses:** Driver.

### 2.6 Media — Ambil Foto Bukti

Gambar 6. Wireframe Media/Dokumentasi. _(belum dilampirkan)_

- **Deskripsi:** Layar kamera + galeri thumbnail foto bukti yang sudah diambil untuk aktivitas berjalan (PRD FR-7).
- **Komponen utama:** Preview kamera live dengan tombol shutter bulat; strip thumbnail foto tersimpan di bagian bawah, masing-masing dengan ikon hapus; badge jumlah total foto; tombol "Selesai" kembali ke Report.
- **Interaksi kunci:** Tombol shutter mengambil foto langsung (tanpa akses galeri perangkat — PRD FR-7); tap ikon hapus pada thumbnail memicu `DELETE .../media/{id}` dengan dialog konfirmasi singkat.
- **Role akses:** Driver.

### 2.7 Tanda Tangan Digital

Gambar 7. Wireframe Tanda Tangan. _(belum dilampirkan)_

- **Deskripsi:** Kanvas tanda tangan untuk petugas/penerima di lokasi (PRD FR-8).
- **Komponen utama:** Kanvas gambar bebas mengisi sebagian besar layar; tombol "Atur Ulang" (kiri) dan "Simpan" (kanan, warna Biru, disabled sampai ada coretan).
- **Interaksi kunci:** "Atur Ulang" mengosongkan kanvas; "Simpan" memicu `PUT .../signature` lalu kembali ke Report dengan indikator tanda tangan tersimpan.
- **Role akses:** Driver.

### 2.8 Riwayat Shipment

Gambar 8. Wireframe Riwayat. _(belum dilampirkan)_

- **Deskripsi:** Daftar shipment yang telah selesai (`status = closed`), diakses dari ikon Riwayat di Beranda (PRD FR-14).
- **Komponen utama:** List item per shipment (nomor, customer, tanggal, badge status "Selesai" warna hijau), pencarian/filter tanggal sederhana (opsional).
- **Interaksi kunci:** Tap item membuka Detail Riwayat (2.9).
- **Role akses:** Driver.

### 2.9 Detail Riwayat Shipment

Gambar 9. Wireframe Detail Riwayat. _(belum dilampirkan)_

- **Deskripsi:** Tampilan read-only seluruh data satu shipment selesai — waktu tiap tahap, foto, tanda tangan, catatan untuk aktivitas muat maupun bongkar.
- **Komponen utama:** Dua section (Muat/Bongkar) masing-masing menampilkan field waktu, galeri foto (grid, tap untuk perbesar), gambar tanda tangan, dan catatan.
- **Interaksi kunci:** Tap thumbnail foto membuka tampilan perbesar (full-screen image viewer); seluruh field tanpa tombol edit (read-only).
- **Role akses:** Driver (hanya shipment miliknya sendiri).

### 2.10 Profil

Gambar 10. Wireframe Profil. _(belum dilampirkan)_

- **Deskripsi:** Informasi akun driver yang login dan aksi logout (PRD FR-15).
- **Komponen utama:** Avatar/foto driver, nama, plat nomor kendaraan yang ditugaskan, tombol "Keluar" (warna merah/error, di bagian bawah).
- **Interaksi kunci:** Tombol Keluar memunculkan dialog konfirmasi sebelum memicu `POST /auth/logout` dan redirect ke Login (2.1).
- **Role akses:** Driver.

## 3. Catatan Implementasi — Pemetaan ke Widget Flutter

Tabel berikut memetakan elemen pada wireframe ke widget/package Flutter yang disarankan, agar konsisten dengan keputusan teknis pada Architecture Document Bagian 3.

| Elemen Wireframe | Widget/Package Flutter | Catatan |
|---|---|---|
| Navigasi antar layar | `go_router` atau `Navigator 2.0` | Route guard mengecek token tersimpan (`flutter_secure_storage`) sebelum masuk ke layar selain Login. |
| Bottom navigation (Home, Profil) | `BottomNavigationBar` | Sesuai PRD Bagian 9.2 — hanya dua item, akses Riwayat dipindah ke ikon header. |
| Kartu shipment (Beranda) | `Card` + `ListView.builder` | Data dari provider `shipmentListProvider` (Riverpod), sumber `GET /shipments`. |
| Badge status shipment/aktivitas | `Chip`/`Container` custom dengan warna kondisional | Warna mengikuti Bagian 1.1 (biru=info, oranye=on-progress, hijau=selesai). |
| Komponen slide-to-confirm | Package `slide_to_act` (atau custom `GestureDetector` + `AnimatedContainer`) | Dipakai pada Check-in (2.4) dan Selesai Aktivitas (2.5), sesuai PRD Bagian 9.2. |
| Ikon petunjuk arah → Google Maps | `url_launcher` dengan deep link `google.navigation:q=<lat>,<lng>` atau `maps://` | Fallback ke browser bila Google Maps tidak terpasang (Architecture Document Bagian 3.3). |
| Textarea Catatan inline | `TextField` (multiline) dengan `onChanged` debounce → `PATCH .../notes` | Autosave, bukan tombol simpan terpisah. |
| Kamera & galeri thumbnail (Media) | Package `camera` untuk capture langsung, `GridView` untuk thumbnail | Tidak memakai `image_picker` dari galeri, sesuai FR-7. |
| Kanvas tanda tangan | Package `signature` | Ekspor sebagai PNG, diunggah lewat `PUT .../signature` (multipart). |
| Upload foto/tanda tangan di background | `Isolate`/`compute()` untuk kompresi gambar sebelum upload | Tidak memblokir UI utama (Architecture Document Bagian 3.2). |
| Antrian offline | `sqflite` (tabel lokal `pending_actions`) + `connectivity_plus` untuk deteksi jaringan | Lihat Architecture Document Bagian 3.2 dan Design Document Bagian 4.3. |
| Token & kredensial di perangkat | `flutter_secure_storage` | Hanya menyimpan token API POD, bukan kredensial database apa pun (PRD Bagian 11). |
| HTTP client + interceptor auth/retry | Package `dio` dengan `Interceptor` kustom | Menyisipkan `Authorization: Bearer`, redirect ke Login saat 401. |
| Image viewer full-screen (Riwayat) | Package `photo_view` | Dipakai pada Detail Riwayat (2.9). |
| Dialog konfirmasi (Maps, Logout, hapus foto) | `showDialog` + `AlertDialog` | Konsisten di seluruh layar yang butuh konfirmasi sebelum aksi tidak dapat dibatalkan. |
