# Product Requirements Document (PRD) — TMS v1.1

## 1. Latar Belakang

Saat ini proses operasional logistik dijalankan menggunakan sistem berbasis Google Apps Script. Sistem ini masih cukup dapat digunakan untuk kebutuhan harian, namun memiliki keterbatasan dalam hal skalabilitas, integrasi data, dan kemampuan menyajikan informasi operasional maupun finansial secara menyeluruh.

Perusahaan sedang dalam proses transisi sistem inti (SYOP) dari PHP native 7 dengan database MySQL menuju arsitektur baru berbasis Laravel 12, Vue.js 3, dan PostgreSQL (selanjutnya disebut SYOP v4). Transisi ini masih berjalan dan belum memiliki linimasa pasti.

Transport Management System (TMS) dibutuhkan untuk menggantikan sistem Google Apps Script dan menyediakan pengelolaan operasional armada yang lebih baik, sekaligus terintegrasi dengan SYOP agar data operasional (armada, maintenance, biaya) dapat dihubungkan dengan data finansial (realisasi PO, pendapatan) untuk analisis profitabilitas per armada.

**Keputusan arsitektur:** TMS akan dibangun menggunakan database MySQL — mengikuti kondisi SYOP yang saat ini masih berjalan pada stack native (PHP7/MySQL), bukan menunggu SYOP v4 selesai. Keputusan ini diambil untuk mempercepat pengembangan TMS dan mempermudah integrasi data selama SYOP v4 belum tersedia. Implikasi teknis dari keputusan ini dijabarkan pada Bagian 8 dan 9.

## 2. Tujuan Sistem

TMS dikembangkan untuk mendukung kebutuhan operasional tim logistik PE, dengan tujuan utama:

- Mengelola proses pengajuan dan pelaksanaan perbaikan armada.
- Melakukan monitoring dan tracking seluruh aktivitas maintenance armada.
- Menyediakan riwayat (history) setiap armada secara lengkap.
- Menyediakan informasi biaya operasional yang dikeluarkan oleh masing-masing armada.
- Menghubungkan biaya operasional armada dengan pendapatan armada (realisasi PO dari SYOP) sehingga dapat dianalisis profitabilitas tiap fleet.
- Terintegrasi dengan data master dan transaksi pada sistem SYOP.

## 3. Ruang Lingkup

### 3.1 Dalam Lingkup (In-Scope)

- Modul Pengajuan Kebutuhan — perbaikan armada, permintaan sparepart, restock warehouse, pembelian kebutuhan operasional, dan kebutuhan lainnya.
- Modul Work Order / SPK — untuk mekanik internal maupun bengkel/vendor eksternal.
- Modul Pelaksanaan & Monitoring — status pekerjaan (Waiting, On Progress, Finished, dll) beserta dokumentasi pendukung (foto, catatan, invoice).
- Modul Verifikasi & Approval berjenjang — Kepala Pool, Branch Manager, Tim Logistik, dan approval Finance untuk transaksi berbiaya.
- Modul Master Data — armada/fleet, driver, mekanik, bengkel/vendor, warehouse, sparepart, jenis pekerjaan maintenance, jenis biaya operasional.
- Modul Riwayat & Monitoring Armada — riwayat perbaikan, penggantian sparepart, servis berkala, status legalitas (STNK, KIR, Pajak, Asuransi), fuel consumption, riwayat penggunaan, total biaya, pendapatan, dan profit/loss per armada.
- Modul Integrasi SYOP — sinkronisasi master armada, driver, transportir, realisasi PO, data perjalanan/distribusi, dan pendapatan.
- Modul Asset Registry (IT & GA) — modul ringan untuk pencatatan/registrasi aset IT dan General Affairs (bukan siklus penuh).

### 3.2 Luar Lingkup (Out of Scope) — Fase Ini

- Depresiasi otomatis dan akuntansi penuh untuk aset IT & GA.
- Workflow procurement penuh untuk pengadaan aset non-armada.
- Modul HR/payroll untuk driver dan mekanik.
- Migrasi SYOP native ke SYOP v4 — berada di luar proyek TMS, namun menjadi dependency yang perlu dipantau (lihat Bagian 12).

## 4. Pengguna & Peran (Stakeholders)

| Role | Deskripsi | Akses Utama |
|---|---|---|
| **Driver** | Pengemudi armada di lapangan. | Membuat pengajuan kebutuhan (via aplikasi lapangan), melihat riwayat pengajuan miliknya. |
| **Mekanik (Internal)** | Mekanik pool/cabang yang mengerjakan Work Order internal. | Membuat pengajuan, menerima & mengerjakan Work Order/SPK, memperbarui status pekerjaan dan dokumentasi. |
| **Bengkel/Vendor (Eksternal)** | Pihak ketiga yang mengerjakan Work Order eksternal. | Memperbarui status pekerjaan dan dokumentasi pada Work Order yang ditugaskan; tidak memiliki akses approval. |
| **Kepala Pool** | Penanggung jawab pool armada di tingkat operasional harian. | Verifikasi tahap pertama atas pekerjaan yang selesai dilakukan. |
| **Branch Manager (BM)** | Penanggung jawab operasional cabang. | Verifikasi tingkat cabang (tahap kedua approval). |
| **Tim Logistik** | Mengelola proses pengajuan, penerbitan Work Order/SPK, dan master data operasional. | Membuat/menerbitkan Work Order, verifikasi operasional (tahap ketiga approval), mengelola master data, melihat riwayat & laporan armada. |
| **Finance** | Mengelola approval atas transaksi yang menimbulkan biaya. | Approval khusus transaksi berbiaya (pembelian sparepart, jasa bengkel, dll) sesuai ambang nominal yang dikonfigurasi. |
| **Admin IT/GA** | Mengelola pencatatan aset IT & General Affairs. | CRUD pada modul Asset Registry. |
| **Admin Sistem** | Mengelola konfigurasi sistem. | Mengatur alur & ambang batas approval, RBAC (role/permission), master data lintas modul, konfigurasi integrasi SYOP. |
| **Manajemen** | Pemangku kepentingan yang memantau kinerja operasional & finansial armada. | Melihat dashboard, laporan profitabilitas armada, dan riwayat legalitas (read-only). |

## 5. Alur Bisnis

### 5.1 Alur Pengajuan hingga Selesai (Perbaikan/Sparepart/Restock/Pembelian)

1. **Pengajuan** — Driver atau Mekanik membuat pengajuan kebutuhan (perbaikan, sparepart, restock, pembelian, atau lainnya) melalui aplikasi lapangan, melampirkan foto/keterangan pendukung. Status awal: `submitted`.
2. **Verifikasi Kepala Pool** — Kepala Pool memeriksa kewajaran pengajuan di tingkat pool. Status menjadi `pool_verified`, atau `rejected` bila ditolak (disertai alasan).
3. **Verifikasi Branch Manager** — BM memverifikasi di tingkat cabang. Status menjadi `bm_verified`.
4. **Verifikasi Tim Logistik** — Tim Logistik memverifikasi kebutuhan secara operasional dan menerbitkan Work Order/SPK, menunjuk pelaksana (mekanik internal atau bengkel/vendor eksternal). Status menjadi `logistik_verified`.
5. **Approval Finance** (khusus bila pengajuan menimbulkan biaya) — Finance menyetujui berdasarkan ambang nominal yang berlaku pada `approval_rules`. Status menjadi `finance_approved`.
6. **Pelaksanaan** — Mekanik/vendor mengerjakan Work Order dan memperbarui status pelaksanaan (`waiting` → `on_progress` → `finished`), disertai dokumentasi (foto, catatan, invoice).
7. **Penyelesaian** — Setelah pekerjaan selesai dan tervalidasi, status approval menjadi `completed`. Biaya yang timbul tercatat sebagai `operational_cost` dan riwayat pekerjaan tercatat pada `maintenance_history`, keduanya terhubung ke armada terkait.

Setiap transisi status pada langkah 2–5 tercatat pada `approval_log` (pelaku, waktu, aksi, catatan), dan setiap tahap hanya dapat dieksekusi oleh role yang berwenang sesuai `approval_rule` yang aktif.

### 5.2 Alur Monitoring Legalitas Armada

Sistem memantau tanggal jatuh tempo dokumen legalitas (STNK, KIR, Pajak, Asuransi) per armada. Menjelang jatuh tempo, sistem mengirim notifikasi kepada Tim Logistik agar dokumen dapat diperpanjang tepat waktu, mencegah armada beroperasi dengan legalitas kedaluwarsa.

### 5.3 Alur Analisis Profitabilitas Armada

1. Data realisasi PO dan pendapatan armada disinkronkan secara berkala (scheduled job) dari SYOP native melalui lapisan adapter, disimpan sebagai cache di `fleet_revenue`.
2. Biaya operasional armada (dari Work Order yang selesai maupun input manual) terakumulasi di `operational_cost`.
3. Sistem menghitung profit/loss per armada per periode sebagai selisih pendapatan (`fleet_revenue`) dan biaya (`operational_cost`), ditampilkan pada Detail Armada dan Laporan Profitabilitas Armada.

### 5.4 Alur Registrasi Aset (IT & GA)

Admin IT/GA mencatat aset baru (kode aset, kategori, nama, PIC, lokasi, tanggal pembelian) langsung sebagai data aktif — tanpa alur approval maupun procurement, sesuai batasan pada Bagian 3.2.

## 6. Kebutuhan Fungsional

Prioritas menggunakan skala MoSCoW: Must have (M), Should have (S), Could have (C).

### Modul Pengajuan Kebutuhan

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-01 | Driver/mekanik dapat membuat pengajuan kebutuhan (perbaikan, sparepart, restock, pembelian, lainnya) melalui aplikasi lapangan pada perangkat mobile, termasuk pada kondisi koneksi kurang stabil. | M |
| FR-02 | Driver/mekanik dapat melampirkan foto/dokumentasi pendukung pada pengajuan, diunggah langsung dari kamera atau galeri perangkat. | M |
| FR-03 | Sistem menghasilkan nomor pengajuan (`request_no`) otomatis dan unik untuk setiap pengajuan. | M |
| FR-04 | Driver/mekanik dapat melihat status dan riwayat pengajuan yang pernah mereka buat ("Pengajuan Saya"). | S |

### Modul Work Order / SPK & Pelaksanaan

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-05 | Tim Logistik/Kepala Pool dapat menerbitkan Work Order/SPK dari suatu pengajuan, untuk pelaksanaan internal (mekanik) maupun eksternal (bengkel/vendor). | M |
| FR-06 | Sistem mencatat rincian item/biaya pekerjaan pada Work Order, termasuk sparepart yang digunakan. | M |
| FR-07 | Mekanik/vendor dapat memperbarui status pelaksanaan Work Order (Waiting, On Progress, Finished) beserta dokumentasi pendukung (foto, catatan, invoice). | M |

### Modul Verifikasi & Approval Berjenjang

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-08 | Sistem menyediakan alur verifikasi & approval berjenjang (Kepala Pool → BM → Tim Logistik → Finance) untuk setiap Work Order. | M |
| FR-09 | Aturan approval (role approver, urutan tahap, ambang nominal untuk Finance) dapat dikonfigurasi oleh Admin Sistem tanpa perubahan kode program. | M |
| FR-10 | Approver hanya dapat menyetujui/menolak Work Order sesuai tahap approval dan role miliknya; penolakan wajib disertai alasan. | M |
| FR-11 | Sistem mengirim notifikasi kepada approver saat ada Work Order yang menunggu approval mereka. | S |

### Modul Master Data

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-12 | Sistem menyediakan CRUD data master: armada/fleet, driver, mekanik, bengkel/vendor, warehouse, sparepart, jenis pekerjaan maintenance, jenis biaya operasional. | M |
| FR-13 | Sistem mencatat stok sparepart per warehouse dan memperbarui stok saat digunakan pada Work Order. | M |
| FR-14 | Sistem memberi peringatan saat stok sparepart berada di bawah ambang minimum (`min_stock`). | S |

### Modul Riwayat & Monitoring Armada

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-15 | Sistem menyediakan riwayat perbaikan/servis lengkap per armada. | M |
| FR-16 | Sistem mencatat dan menampilkan status legalitas armada (STNK, KIR, Pajak, Asuransi) beserta tanggal jatuh tempo. | M |
| FR-17 | Sistem mengirim notifikasi otomatis saat legalitas armada mendekati jatuh tempo. | S |
| FR-18 | Sistem mencatat riwayat konsumsi BBM (fuel log) per armada. | S |
| FR-19 | Sistem menghitung dan menampilkan total biaya operasional per armada per periode. | M |

### Modul Integrasi SYOP & Profitabilitas

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-20 | Sistem menyinkronkan data master armada & driver dari SYOP native saat go-live (import satu kali); setelahnya dikelola mandiri di TMS. | M |
| FR-21 | Sistem menyinkronkan data realisasi PO & pendapatan armada dari SYOP native secara berkala melalui lapisan adapter (`SyopDataProvider`), bukan real-time per request. | M |
| FR-22 | Sistem menghitung dan menampilkan profit/loss per armada dari selisih pendapatan (sinkron SYOP) dan biaya operasional (TMS). | M |
| FR-23 | Sistem menyediakan laporan profitabilitas armada lintas cabang/periode, dapat diekspor ke Excel. | S |

### Modul Asset Registry (IT & GA)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-24 | Sistem menyediakan registrasi aset IT & GA (kode aset, kategori, nama, PIC, lokasi, tanggal pembelian, status) — CRUD sederhana tanpa depresiasi/procurement. | C |

### Lintas Modul

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-25 | Seluruh perubahan approval dan data terkait biaya dicatat pada audit log (pelaku, waktu, aksi, nilai sebelum/sesudah). | M |
| FR-26 | Pengguna login satu kali melalui SSO dan dapat mengakses TMS maupun SYOP v4 tanpa login berulang. | S |

## 7. Kebutuhan Non-Fungsional

| ID | Kategori | Kebutuhan |
|---|---|---|
| NFR-01 | Performance | Response time API < 2 detik untuk 95% request pada beban normal; dashboard/laporan profitabilitas dibaca dari data tersinkron (cache), bukan query real-time ke `syop_db`. |
| NFR-02 | Availability | Uptime sistem ≥ 99% pada jam operasional. |
| NFR-03 | Scalability | Arsitektur backend modular (per domain) agar tiap modul dapat dipecah menjadi service terpisah bila beban bertambah. |
| NFR-04 | Security | Seluruh komunikasi menggunakan HTTPS; kredensial dan token tidak pernah di-hardcode; akses ke `syop_db` memakai user MySQL read-only dengan least privilege. |
| NFR-05 | Auditability | Seluruh aksi approval dan perubahan data berbiaya tercatat pada audit log (pelaku, waktu, aksi, nilai sebelum/sesudah) dan tidak dapat diubah/dihapus oleh pengguna biasa. |
| NFR-06 | Usability | Form pengajuan lapangan tetap responsif pada perangkat kelas bawah dan koneksi tidak stabil; panel admin konsisten mengikuti design system Materio (lihat Bagian 8.5). |
| NFR-07 | Data Integrity | Nilai uang disimpan sebagai tipe desimal presisi tetap (bukan floating point) untuk menghindari galat pembulatan; constraint foreign key menjaga konsistensi relasi data. |
| NFR-08 | Maintainability | Seluruh akses ke SYOP native dibungkus lapisan adapter agar mudah diganti saat migrasi ke SYOP v4, tanpa mengubah logika bisnis inti (lihat Bagian 8.3). |
| NFR-09 | Compatibility | Aplikasi lapangan dapat diakses melalui browser mobile umum tanpa memerlukan instalasi aplikasi native. |
| NFR-10 | Backup & Recovery | Target RPO dan RTO disepakati bersama tim infrastruktur/DBA sebelum go-live; backup `tms_db` dijadwalkan terpisah dari backup `syop_db`. |

## 8. Arsitektur & Teknologi

### 8.1 Stack Teknologi

- **Backend:** Laravel 12
- **Frontend:** Vue.js 3
- **Database:** MySQL — mengikuti kondisi SYOP native saat ini.

**Catatan keputusan:** Rencana jangka panjang perusahaan adalah PostgreSQL untuk sistem baru (sejalan dengan SYOP v4). Namun karena SYOP v4 belum tersedia dan SYOP native masih berjalan di MySQL, TMS dibangun di atas MySQL terlebih dahulu agar integrasi data dengan SYOP lebih sederhana pada fase awal. Keputusan ini perlu ditinjau ulang saat SYOP v4 sudah live (lihat Bagian 12 — Risiko).

### 8.2 Penempatan Database

Direkomendasikan TMS menggunakan database MySQL terpisah dari database SYOP native (bukan satu database yang sama), meskipun berada pada instance/server MySQL yang sama jika memungkinkan. Ini menjaga batas kepemilikan data (armada, maintenance, dan biaya menjadi milik TMS) sambil tetap memungkinkan akses baca lintas database secara efisien selama masih di engine yang sama.

### 8.3 Lapisan Integrasi (Adapter Layer)

Seluruh akses data dari/ke SYOP wajib melalui satu lapisan abstraksi (service/repository), bukan query tersebar di banyak tempat pada kode TMS. Tujuannya agar ketika SYOP bermigrasi ke SYOP v4 (Laravel/PostgreSQL), TMS cukup mengganti implementasi lapisan ini tanpa mengubah logika bisnis inti.

- Contoh interface: `SyopDataProvider` dengan method seperti `getRealisasiPO()`, `getMasterArmada()`, `getPendapatan()`.
- Implementasi saat ini: `SyopNativeAdapter` (akses ke MySQL SYOP native).
- Implementasi masa depan: `SyopV4Adapter` (akses ke API/schema SYOP v4), diaktifkan melalui konfigurasi tanpa refactor besar.

### 8.4 Single Sign-On (SSO)

Disarankan menyiapkan mekanisme SSO sejak awal (contoh: Laravel Passport sebagai OAuth2 provider, atau menyesuaikan dengan mekanisme login SYOP jika penggunanya sama), sehingga pengguna (driver, mekanik, Tim Logistik, Finance) tidak perlu login berulang di TMS dan SYOP.

### 8.5 Template UI/UX

**Keputusan:** TMS menggunakan template UI yang sama dengan SYOP v4, yaitu **Materio** (berbasis Vuetify untuk Vue), bukan template lain seperti TailAdmin.

**Alasan pemilihan:**

- **Konsistensi pengalaman pengguna** — Tim Logistik dan Finance kemungkinan besar menggunakan SYOP v4 dan TMS secara bersamaan, apalagi setelah SSO diterapkan. Tampilan yang konsisten membuat kedua sistem terasa sebagai satu platform terpadu.
- **Efisiensi tim developer** — menghindari kebutuhan menguasai dua design system berbeda (Vuetify/Materio vs Tailwind/TailAdmin) sekaligus, sehingga mengurangi beban maintenance jangka panjang.
- **Reuse komponen** — komponen siap pakai dari Materio (data table, form, dashboard) sesuai kebutuhan TMS (master data, approval, riwayat armada); berpotensi diekstrak menjadi shared component library antara SYOP v4 dan TMS.

**Pengecualian:** khusus alur pengajuan dari perangkat mobile untuk driver/mekanik (FR-01, FR-02), dapat digunakan UI yang lebih ringan dan sederhana (tidak harus Materio/Vuetify) mengingat potensi penggunaan pada perangkat kelas bawah dan koneksi internet yang kurang stabil di lapangan. Panel admin/approval (Tim Logistik, Kepala Pool, BM, Finance) tetap konsisten menggunakan Materio.

**Catatan:** perlu dicek terlebih dahulu apakah lisensi template Materio yang sudah dimiliki (mis. dari ThemeForest/ThemeSelection) mencakup penggunaan di lebih dari satu aplikasi/domain, atau memerlukan extended license untuk dipakai juga di TMS.

## 9. Integrasi dengan SYOP

Karena TMS dan SYOP native sama-sama menggunakan MySQL, integrasi pada fase awal dapat memanfaatkan query lintas database secara langsung untuk efisiensi. Namun akses ini tetap wajib dibungkus melalui lapisan adapter (Bagian 8.3) agar tidak menimbulkan ketergantungan struktural yang sulit diubah saat SYOP bermigrasi ke v4.

## 10. Alur Approval

Alur verifikasi dan approval mengikuti struktur berikut, dengan ambang batas nominal untuk approval Finance yang dapat dikonfigurasi oleh Admin Sistem:

1. **Kepala Pool** — verifikasi awal atas pekerjaan yang selesai dilakukan.
2. **Branch Manager (BM)** — verifikasi tingkat cabang.
3. **Tim Logistik** — verifikasi operasional.
4. **Finance** — approval khusus untuk transaksi yang menimbulkan biaya (pembelian sparepart, jasa bengkel, dll).

Alur ini harus dibangun sebagai konfigurasi (bukan hardcode), mengingat kebijakan approval (jumlah approver, urutan, ambang nominal) berpotensi berubah seiring waktu.

## 11. Asumsi & Batasan

- SYOP native (MySQL) tetap berjalan selama pengembangan dan operasional awal TMS, dan dapat diakses (minimal read access) untuk keperluan integrasi.
- Rencana migrasi SYOP ke SYOP v4 (Laravel 12/PostgreSQL) belum memiliki linimasa pasti; TMS akan menyesuaikan integrasi ketika migrasi tersebut terjadi.
- Modul Asset Registry (IT & GA) dibatasi pada pencatatan/registrasi aset saja pada fase ini, tanpa depresiasi maupun workflow procurement.
- Pengguna sistem Google Apps Script saat ini akan bermigrasi penuh ke TMS setelah go-live.
- Struktur organisasi approval (Kepala Pool, BM, Tim Logistik, Finance) mengikuti struktur yang berlaku saat dokumen ini disusun.

## 12. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| SYOP v4 belum memiliki linimasa migrasi pasti, sementara TMS sudah terlanjur terintegrasi ke SYOP native (MySQL). | Ketergantungan jangka panjang pada stack lama; adaptasi ke SYOP v4 berpotensi mahal bila tidak diantisipasi sejak awal. | Seluruh akses ke SYOP native dibungkus lapisan adapter (`SyopDataProvider`); saat SYOP v4 live, cukup mengganti implementasi adapter tanpa mengubah logika bisnis inti (lihat Bagian 8.3 & 9). |
| TMS dan SYOP native berbagi instance MySQL yang sama. | Beban query TMS ke `syop_db` dapat mengganggu performa SYOP native yang sedang berjalan produksi. | Query ke `syop_db` dibatasi read-only, dilakukan berkala via scheduled job (bukan real-time per request), dan staging TMS diarahkan ke replika `syop_db`, bukan production langsung. |
| Struktur tabel `syop_db` native berubah tanpa koordinasi. | Sinkronisasi data (master armada, realisasi PO, pendapatan) gagal atau menghasilkan data salah. | Perubahan cukup disesuaikan pada lapisan `SyopNativeAdapter` saja; diperlukan koordinasi rutin dengan tim SYOP dan monitoring pada job sinkronisasi. |
| Lisensi template Materio yang dimiliki belum tentu mencakup penggunaan di lebih dari satu aplikasi/domain. | Pengembangan UI TMS tertunda atau berisiko pelanggaran lisensi. | Verifikasi cakupan lisensi Materio sebelum development dimulai; siapkan anggaran extended license bila diperlukan (lihat Bagian 8.5). |
| Resistensi pengguna terhadap migrasi penuh dari Google Apps Script ke TMS. | Proses berjalan paralel (data ganda, potensi selisih), memperlambat tercapainya metrik keberhasilan. | Pelatihan pengguna, migrasi bertahap per cabang, komunikasi dan dukungan dari manajemen saat go-live. |
| SSO belum siap pada awal proyek. | Pengguna harus login berulang di TMS dan SYOP, mengurangi kenyamanan penggunaan. | Gunakan mekanisme login sementara (lihat Bagian 11) dengan rencana migrasi ke SSO begitu tersedia. |
| Kualitas jaringan di lapangan (driver/mekanik) rendah. | Pengajuan gagal terkirim atau submit ganda. | Form lapangan dibuat ringan dan lazy-loaded, tombol kirim dinonaktifkan sementara setelah ditekan, dengan indikator status pengiriman (lihat Bagian 8.5). |
| Data master armada/driver terduplikasi antara TMS dan SYOP bila proses import awal tidak bersih. | Data tidak konsisten, mengganggu perhitungan profitabilitas dan pelaporan. | One-time import script tervalidasi saat go-live; setelahnya TMS menjadi source of truth tunggal untuk data master armada & driver (lihat Bagian 13). |

## 13. Metrik Keberhasilan

- Seluruh pengajuan kebutuhan logistik (perbaikan, sparepart, restock, dll) dilakukan melalui TMS, bukan lagi manual/spreadsheet/Google Apps Script.
- Waktu proses pengajuan hingga approval berkurang dibandingkan proses saat ini.
- Riwayat dan status legalitas setiap armada tersedia secara real-time dan lengkap.
- Profit/loss per armada dapat dihasilkan otomatis dari integrasi biaya (TMS) dan pendapatan (SYOP).
- Tidak ada duplikasi input data master armada/driver antara TMS dan SYOP.

## 14. Roadmap Pengembangan (Ringkas)

Linimasa pasti (tanggal/durasi per fase) belum ditetapkan dan perlu disepakati bersama tim development. Urutan fase berikut disusun berdasarkan ketergantungan antar modul:

| Fase | Fokus | Cakupan Utama |
|---|---|---|
| Fase 0 — Fondasi | Setup proyek & infrastruktur dasar. | Setup Laravel 12 + Vue 3 (Materio) + MySQL, struktur `tms_db` awal, RBAC dasar (roles, permissions, users), koneksi read-only ke `syop_db`. |
| Fase 1 — Master Data & Pengajuan | Modul dasar operasional. | CRUD master data (armada, driver, mekanik, vendor, warehouse, sparepart), Modul Pengajuan Kebutuhan (termasuk form lapangan mobile — FR-01, FR-02). |
| Fase 2 — Work Order & Approval | Alur kerja inti. | Modul Work Order/SPK, alur verifikasi & approval berjenjang berbasis konfigurasi (FR-08, FR-09), audit log. |
| Fase 3 — Riwayat & Legalitas Armada | Monitoring armada. | Riwayat maintenance, legalitas (STNK/KIR/Pajak/Asuransi) & notifikasi jatuh tempo, fuel log, biaya operasional. |
| Fase 4 — Integrasi SYOP & Profitabilitas | Analisis finansial. | Lapisan adapter (`SyopDataProvider`/`SyopNativeAdapter`), sinkronisasi realisasi PO & pendapatan, perhitungan dan laporan profit/loss per armada. |
| Fase 5 — Asset Registry & SSO | Pelengkap & go-live. | Modul Asset Registry (IT & GA), integrasi SSO, migrasi penuh pengguna dari Google Apps Script, UAT dan go-live. |

Setelah SYOP v4 live dan linimasa migrasinya jelas, perlu ditambahkan fase lanjutan untuk beralih dari `SyopNativeAdapter` ke `SyopV4Adapter` (lihat PRD Bagian 8.3 dan Architecture Document Bagian 6.3).

## 15. Lampiran — Glosarium

| Istilah | Penjelasan |
|---|---|
| **TMS** | Transport Management System — sistem yang dijelaskan pada dokumen ini. |
| **SYOP** | Sistem inti perusahaan (finansial & operasional distribusi), sumber data realisasi PO dan pendapatan. |
| **SYOP native** | Versi SYOP yang berjalan saat ini: PHP native 7 dengan database MySQL. |
| **SYOP v4** | Versi baru SYOP yang sedang dikembangkan: Laravel 12, Vue.js 3, PostgreSQL. |
| **PE** | PT Pro Energi — perusahaan pengguna sistem ini. |
| **Armada / Fleet** | Kendaraan operasional (mis. truk tronton, tangki) yang dikelola melalui TMS. |
| **Work Order (WO) / SPK** | Surat Perintah Kerja — dokumen penugasan pekerjaan perbaikan/maintenance kepada mekanik internal atau bengkel/vendor eksternal. |
| **Kepala Pool** | Penanggung jawab pool armada; melakukan verifikasi tahap pertama atas pekerjaan yang selesai. |
| **BM (Branch Manager)** | Penanggung jawab operasional cabang; melakukan verifikasi tingkat cabang. |
| **Adapter Layer** | Lapisan abstraksi (service/repository) yang membungkus seluruh akses data dari/ke SYOP, agar implementasinya dapat diganti tanpa mengubah logika bisnis TMS. |
| **SyopDataProvider** | Interface adapter untuk mengakses data SYOP (mis. `getRealisasiPO()`, `getMasterArmada()`, `getPendapatan()`). |
| **SyopNativeAdapter** | Implementasi `SyopDataProvider` saat ini, mengakses `syop_db` (MySQL) secara langsung. |
| **SyopV4Adapter** | Implementasi `SyopDataProvider` di masa depan, memanggil API SYOP v4 setelah migrasi selesai. |
| **SSO (Single Sign-On)** | Mekanisme login terpusat agar satu akun berlaku untuk TMS maupun SYOP v4. |
| **RBAC (Role-Based Access Control)** | Model otorisasi berbasis peran/permission granular per modul. |
| **MoSCoW** | Skala prioritas kebutuhan: Must have, Should have, Could have (dan Won't have). |
| **Realisasi PO** | Data pendapatan aktual dari Purchase Order yang tercatat di SYOP, digunakan untuk menghitung profitabilitas armada. |
| **Legalitas Armada** | Dokumen legal kendaraan: STNK, KIR, Pajak, dan Asuransi, beserta tanggal jatuh temponya. |
| **Profitabilitas Armada** | Selisih antara pendapatan (dari SYOP) dan biaya operasional (dari TMS) untuk satu armada pada suatu periode. |
| **Asset Registry** | Modul pencatatan/registrasi aset IT & General Affairs, tanpa depresiasi maupun workflow procurement penuh. |
| **RPO (Recovery Point Objective)** | Batas maksimum data yang boleh hilang saat terjadi gangguan, diukur dalam waktu. |
| **RTO (Recovery Time Objective)** | Batas maksimum waktu pemulihan sistem setelah terjadi gangguan. |
| **tms_db** | Database MySQL milik TMS, terpisah dari `syop_db`. |
| **syop_db** | Database MySQL milik SYOP native, diakses TMS secara read-only melalui adapter. |
