# Wireframe Document — TMS

Dokumen ini berisi wireframe (low-fidelity) untuk layar-layar utama TMS, sebagai visualisasi dari daftar halaman yang telah didefinisikan pada [Design Document](03-Design-Document.md) Bagian 6. Tujuannya adalah menyelaraskan pemahaman mengenai layout, penempatan komponen, dan alur interaksi sebelum tim frontend membangun tampilan final menggunakan Vue 3 + Materio (Vuetify).

Wireframe ini bersifat skematik — warna, tipografi, dan detail visual akhir mengikuti design system Materio, bukan mengikuti tampilan pada dokumen ini secara literal.

## 1.1 Panduan Membaca Wireframe

- Warna navy pada sidebar menandai menu yang sedang aktif/dipilih.
- Warna biru (accent) menandai aksi utama (tombol utama, tab aktif).
- Warna hijau menandai status positif (Aktif, Completed, Approve, Disetujui).
- Warna merah menandai status negatif (Rejected, Rusak, Reject, Ditolak).
- Warna kuning/oranye menandai status menunggu (Submitted, Approval Tertunda).
- Kotak abu-abu bertanda '+' atau nama file merepresentasikan placeholder gambar/lampiran.

## 2. Web Admin (Vue 3 + Materio)

### 2.1 Dashboard

Gambar 1. Wireframe Dashboard. _(belum dilampirkan)_

- **Deskripsi:** Ringkasan operasional & finansial saat pengguna login. Menampilkan KPI utama, grafik profit/loss per armada, dan daftar legalitas yang mendekati jatuh tempo.
- **Komponen utama:** KPI card (4 kartu ringkasan), grafik batang profit/loss, panel daftar peringatan legalitas.
- **Interaksi kunci:** Klik KPI card mengarahkan ke halaman terkait (mis. klik 'Approval Tertunda' membuka Antrian Approval); klik baris legalitas membuka Detail Armada tab Legalitas.
- **Role akses:** Semua role — konten kartu menyesuaikan data yang relevan dengan role masing-masing.

### 2.2 Daftar Pengajuan

Gambar 2. Wireframe Daftar Pengajuan. _(belum dilampirkan)_

- **Deskripsi:** Menampilkan seluruh pengajuan (perbaikan, sparepart, restock, pembelian, lainnya) dengan filter dan pencarian.
- **Komponen utama:** Filter bar (status, armada, tanggal, pencarian), tabel data dengan status badge berwarna, tombol '+ Buat Pengajuan', pagination.
- **Interaksi kunci:** Klik '+ Buat Pengajuan' membuka form pengajuan baru; klik 'Lihat' pada baris membuka Detail Work Order/SPK terkait; filter memuat ulang tabel tanpa reload halaman penuh.
- **Role akses:** Tim Logistik, Kepala Pool (buat & lihat); role lain — lihat sesuai keterlibatan.

### 2.3 Detail Work Order / SPK

Gambar 3. Wireframe Detail Work Order/SPK. _(belum dilampirkan)_

- **Deskripsi:** Detail satu Work Order/SPK: informasi pekerjaan, rincian biaya, dokumentasi, dan riwayat verifikasi/approval berjenjang dalam bentuk timeline.
- **Komponen utama:** Card informasi pekerjaan, tabel rincian biaya, thumbnail lampiran, timeline vertikal status approval, tombol aksi Setujui/Tolak.
- **Interaksi kunci:** Tombol Setujui/Tolak hanya aktif bagi role yang sesuai dengan tahap approval saat ini (lihat `approval_rules` pada Design Document); menolak memunculkan dialog untuk mengisi alasan.
- **Role akses:** Kepala Pool, Branch Manager, Tim Logistik, Finance (approval); Mekanik/Vendor (update status, tanpa akses approval).

### 2.4 Antrian Approval

Gambar 4. Wireframe Antrian Approval. _(belum dilampirkan)_

- **Deskripsi:** Daftar item yang menunggu approval dari pengguna yang sedang login, terpisah dari tab riwayat approval yang sudah selesai.
- **Komponen utama:** Tab 'Perlu Approval Saya' / 'Riwayat', tabel daftar WO dengan nilai biaya, tombol Approve/Reject langsung pada baris.
- **Interaksi kunci:** Approve/Reject dari tabel memunculkan dialog konfirmasi; setelah aksi, baris otomatis hilang dari antrian dan pindah ke tab Riwayat.
- **Role akses:** Kepala Pool, Branch Manager, Tim Logistik, Finance — daftar menyesuaikan tahap approval milik masing-masing role.

### 2.5 Detail Armada — Tab Profit-Loss

Gambar 5. Wireframe Detail Armada (tab Profit-Loss). _(belum dilampirkan)_

- **Deskripsi:** Halaman satu armada dengan beberapa tab (Riwayat, Legalitas, Fuel Log, Biaya, Profit-Loss). Tab Profit-Loss menampilkan ringkasan biaya vs pendapatan (hasil sinkronisasi SYOP) dan grafik bulanan.
- **Komponen utama:** Tab navigasi, summary card (Total Biaya, Total Pendapatan, Profit/Loss), grafik batang berpasangan (pendapatan vs biaya per bulan) dengan legenda.
- **Interaksi kunci:** Berpindah tab memuat konten berbeda tanpa reload; grafik dapat di-hover untuk menampilkan nilai per bulan (tooltip).
- **Role akses:** Tim Logistik, Manajemen (lihat); tab Legalitas dapat diedit oleh Tim Logistik.

### 2.6 Asset Registry (IT & GA)

Gambar 6. Wireframe Asset Registry. _(belum dilampirkan)_

- **Deskripsi:** Daftar aset IT & GA yang teregistrasi, dengan filter kategori dan pencarian. Sesuai keputusan PRD, modul ini hanya mencakup registrasi — tanpa depresiasi/procurement.
- **Komponen utama:** Filter kategori, kotak pencarian, tabel data aset dengan status badge, tombol '+ Registrasi Aset'.
- **Interaksi kunci:** Klik baris membuka form edit aset; tombol '+ Registrasi Aset' membuka form kosong untuk pendaftaran baru.
- **Role akses:** Admin IT/GA.

### 2.7 Laporan Profitabilitas Armada

Gambar 7. Wireframe Laporan Profitabilitas Armada. _(belum dilampirkan)_

- **Deskripsi:** Laporan lintas armada/cabang/periode, menampilkan ringkasan total dan rincian profit/loss per armada — sumber data gabungan dari `operational_cost` (TMS) dan `fleet_revenue` (sinkron SYOP).
- **Komponen utama:** Filter cabang & periode, tombol Export Excel, summary card, grafik batang profit/loss (positif hijau, negatif merah), tabel rincian per armada.
- **Interaksi kunci:** Ubah filter memuat ulang grafik & tabel; tombol Export Excel mengunduh data sesuai filter yang aktif.
- **Role akses:** Tim Logistik, Finance, Manajemen.

## 3. Aplikasi Lapangan (Driver/Mekanik)

### 3.1 Buat Pengajuan

Gambar 8. Wireframe Form Pengajuan Mobile. _(belum dilampirkan)_

- **Deskripsi:** Form ringkas untuk driver/mekanik mengajukan kebutuhan (perbaikan, sparepart, dll) langsung dari lapangan. Sesuai PRD Bagian 8.5, tampilan ini dibuat lebih ringan dari panel admin.
- **Komponen utama:** Dropdown jenis pengajuan, dropdown armada, textarea deskripsi, area unggah foto (multi), tombol kirim full-width.
- **Interaksi kunci:** Tombol kirim menonaktifkan diri sesaat setelah ditekan (mencegah submit ganda) dan menampilkan indikator terkirim; unggah foto mendukung kamera langsung atau galeri.
- **Role akses:** Driver, Mekanik.

Halaman lain pada aplikasi lapangan (Pengajuan Saya, Update Status SPK — lihat Design Document Bagian 6.3) mengikuti pola layout yang sama: top bar dengan tombol kembali, konten dalam satu kolom, dan tombol aksi utama full-width di bagian bawah.

## 4. Catatan Implementasi — Pemetaan ke Komponen Materio/Vuetify

Tabel berikut memetakan elemen pada wireframe ke komponen Vuetify/Materio yang disarankan digunakan saat implementasi, agar konsisten dengan design system yang sudah diputuskan pada PRD Bagian 8.5.

| Elemen Wireframe | Komponen Vuetify/Materio | Catatan |
|---|---|---|
| Sidebar menu | `VNavigationDrawer` (Materio vertical nav) | Item menu dirender sesuai role (RBAC — Architecture Doc 7.2). |
| Top bar | `VAppBar` | Berisi search, notifikasi, avatar/profil pengguna. |
| KPI card (Dashboard) | `VCard` + `VCardText` (Materio "Statistics Card") | 4 kartu ringkasan pada Dashboard (2.1). |
| Grafik batang profit/loss | `vue3-apexcharts` (ApexCharts) dibungkus komponen chart Materio | Dipakai pada Dashboard, Detail Armada tab Profit-Loss, dan Laporan Profitabilitas. |
| Filter bar (status, armada, tanggal, pencarian) | `VRow`/`VCol` + `VSelect`/`VAutocomplete` + `VTextField` (search) + `VMenu` + date picker | Dipakai pada Daftar Pengajuan, Antrian Approval, Laporan. |
| Tabel data dengan status badge | `VDataTable` + `VChip` (warna sesuai Bagian 1.1) | Server-side pagination via endpoint `?page=&per_page=` (Design Document 3.1). |
| Tombol aksi utama ('+ Buat Pengajuan', '+ Registrasi Aset', dll) | `VBtn` (`color="primary"`) | Konsisten warna accent (biru) sesuai panduan wireframe. |
| Pagination | `VDataTable` built-in footer / `VPagination` | — |
| Card informasi pekerjaan (Detail WO) | `VCard` | — |
| Tabel rincian biaya | `VDataTable` (tanpa pagination bila item sedikit) | Sumber data: `work_order_items`. |
| Thumbnail lampiran | `VImg` dalam grid `VRow`/`VCol`, dengan `VDialog` untuk preview penuh | — |
| Timeline vertikal status approval | `VTimeline` | Sumber data: `approval_logs`. |
| Tombol Setujui/Tolak | `VBtn` (`color="success"`/`color="error"`) + `VDialog` konfirmasi | Dialog Tolak menyertakan `VTextarea` untuk alasan (wajib, FR-10). |
| Tab 'Perlu Approval Saya' / 'Riwayat' | `VTabs` + `VWindow` | — |
| Tab navigasi Detail Armada (Riwayat, Legalitas, Fuel Log, Biaya, Profit-Loss) | `VTabs` + `VWindow` | — |
| Summary card (Total Biaya, Pendapatan, Profit/Loss) | `VCard` dengan `VCardText` + ikon indikator | Warna mengikuti nilai positif/negatif (hijau/merah). |
| Tombol Export Excel | `VBtn` dengan ikon `mdi-file-excel` | Memanggil `GET /reports/fleet-profitability/export`. |
| Filter kategori (Asset Registry) | `VSelect`/`VChipGroup` | — |
| Dropdown jenis pengajuan/armada (form mobile) | `VSelect`/`VAutocomplete` | Bundle terpisah/ringan (Architecture Doc 3.2), tetap pakai komponen Vuetify dasar tanpa aset berat Materio. |
| Textarea deskripsi (form mobile) | `VTextarea` | — |
| Area unggah foto (form mobile) | `VFileInput` dengan `capture` attribute untuk kamera langsung | Mendukung multi-file. |
| Tombol kirim full-width (form mobile) | `VBtn block` dengan `:loading` & `:disabled` saat submit | Mencegah submit ganda (lihat 3.1). |
| Badge notifikasi | `VBadge` pada ikon lonceng di `VAppBar` | Sumber data: `GET /notifications`. |
| Search box | `VTextField` dengan `prepend-inner-icon="mdi-magnify"` | — |
