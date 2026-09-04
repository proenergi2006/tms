# Product Requirements Document (PRD) — TMS v1.2

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
- Modul Verifikasi & Approval berjenjang **dinamis** — default Fleet Operations → Kepala Pool per cabang, dapat dikonfigurasi tanpa perubahan kode (lihat Bagian 10).
- Modul Master Data — cabang, armada/fleet, driver, mekanik, bengkel/vendor, warehouse, sparepart, jenis pekerjaan maintenance, jenis biaya operasional.
- Modul Riwayat & Monitoring Armada — riwayat perbaikan, penggantian sparepart, servis berkala, status legalitas (STNK, KIR, Pajak, Asuransi), fuel consumption, riwayat penggunaan, total biaya, pendapatan, dan profit/loss per armada.
- Modul Integrasi SYOP — sinkronisasi master armada, driver, transportir, realisasi PO, data perjalanan/distribusi, dan pendapatan.
- Modul Asset Registry (IT & GA) — modul ringan untuk pencatatan/registrasi aset IT dan General Affairs (bukan siklus penuh).

### 3.2 Luar Lingkup (Out of Scope) — Fase Ini

- Depresiasi otomatis dan akuntansi penuh untuk aset IT & GA.
- Workflow procurement penuh untuk pengadaan aset non-armada.
- Modul HR/payroll untuk driver dan mekanik.
- Migrasi SYOP native ke SYOP v4 — berada di luar proyek TMS, namun menjadi dependency yang perlu dipantau (lihat Bagian 12).

## 4. Pengguna & Peran (Stakeholders)

> **Catatan:** struktur peran final berbeda dari rancangan awal — Driver, Mekanik (internal), Bengkel/Vendor (sebagai akun login terpisah), Branch Manager (BM), dan Finance **dihapus** dari desain final. SA menggantikan fungsi Driver/Mekanik (membuat pengajuan sekaligus mengerjakannya sendiri); Fleet Operations & Kepala Pool menggantikan seluruh rantai BM/Finance dalam approval. Lihat Riwayat Dokumen.

| Role | Deskripsi | Akses Utama |
|---|---|---|
| **Service Advisor (SA)** | Ujung tombak operasional per cabang — satu-satunya pembuat pengajuan, sekaligus pelaksana Work Order. | Membuat pengajuan + Work Order sekaligus dalam satu langkah, menjalankan pekerjaan (`waiting` → `on_progress` → `finished`), realisasi sparepart, melihat riwayat & detail armada cabangnya. |
| **Fleet Operations** | Verifikator tahap pertama, per cabang. | Verifikasi/tolak pengajuan tahap 1 (dapat mengedit pengajuan SA selama tahap ini berjalan), melihat master data/armada/pengajuan/laporan cabangnya. |
| **Kepala Pool** | Approver tahap akhir, per cabang. | Approval/tolak tahap akhir (tahap 2), melihat master data/armada/pengajuan/laporan cabangnya. |
| **Tim Logistik** | Pengelola data master operasional, per cabang. | CRUD master data (armada, driver, mekanik, vendor, warehouse, jenis pekerjaan, jenis biaya) cabangnya; **tidak** terlibat approval maupun eksekusi Work Order. |
| **Admin Logistik** | Pengelola stok sparepart, per cabang. | Full CRUD **sparepart** cabangnya; view-only untuk master data/armada/pengajuan/laporan lain — tidak terlibat approval. |
| **Logistik HO** | Pemantau operasional lintas cabang dari Head Office. | Melihat (read-only) pengajuan/armada/master data/laporan **seluruh cabang** sekaligus; tanpa wewenang kelola/approval apa pun. |
| **Admin IT & GA** | Mengelola pencatatan aset IT & General Affairs. | CRUD pada modul Asset Registry. |
| **Admin Sistem** | Administrator sistem (super role — otomatis mendapat seluruh permission). | RBAC (role/permission), manajemen pengguna, konfigurasi tahap approval (`approval_steps`), audit log, log aplikasi, CRUD data Cabang. |
| **Manajemen** | Pemangku kepentingan yang memantau kinerja operasional & finansial armada. | Melihat dashboard, laporan profitabilitas armada, riwayat armada, dan master data (read-only) lintas cabang. |

## 5. Alur Bisnis

### 5.1 Alur Pengajuan hingga Selesai (Perbaikan/Sparepart/Restock/Pembelian)

> Alur final — menggantikan rancangan awal (Kepala Pool → BM → Tim Logistik → Finance, 4 tahap berbasis ambang nominal) yang sudah tidak berlaku. Lihat Riwayat Dokumen.

1. **Pengajuan + Work Order** — Service Advisor (SA) cabang membuat pengajuan (perbaikan/sparepart/restock/pembelian/lainnya) **sekaligus** Work Order dalam satu langkah: jenis, jenis perawatan (preventive/corrective untuk perbaikan), diagnosa, prioritas, estimasi lama perbaikan (hari), pelaksana (mekanik internal atau vendor eksternal), rencana sparepart/biaya, dan lampiran foto/keterangan pendukung. No. TAR (Technical Analysis Report) dibuat otomatis oleh sistem. Status awal `submitted` (Work Order: `waiting`).
2. **Verifikasi tahap 1 — Fleet Operations** cabang yang sama: dapat mengedit pengajuan (mis. mengoreksi estimasi/pelaksana) atau menolak dengan alasan.
3. **Approval tahap 2 — Kepala Pool** cabang yang sama: dapat menolak dengan alasan. Urutan & jumlah tahap approval ini adalah **konfigurasi** (tabel `approval_steps`, diatur Admin Sistem), bukan hardcode — default dua tahap seperti di atas, berlaku seragam untuk seluruh cabang.
4. Setelah lolos seluruh tahap aktif → status approval `completed`; SA menjalankan pekerjaan (`waiting` → `on_progress`).
5. **Realisasi sparepart** — sebelum Work Order ditandai selesai, SA wajib menegaskan sparepart yang benar-benar terpakai (boleh berbeda dari rencana awal). Stok gudang berkurang **hanya** pada titik ini dan **hanya** untuk pelaksanaan internal; vendor eksternal memakai sparepart sendiri (dicatat sebagai teks bebas, tidak memotong stok TMS).
6. **Penyelesaian** — status Work Order `finished`. Biaya (termasuk jasa/sparepart dari vendor eksternal) otomatis tercatat sebagai `operational_cost`, riwayat pekerjaan tercatat pada `maintenance_history`, keduanya terhubung ke armada terkait — tanpa gerbang verifikasi tambahan pasca-selesai.

Setiap transisi status pada langkah 2–3 tercatat pada `approval_logs` (pelaku, waktu, aksi, catatan), dan setiap tahap hanya dapat dieksekusi oleh role yang berwenang sesuai `approval_steps` yang aktif untuk cabang terkait.

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
| FR-01 | SA dapat membuat pengajuan kebutuhan (perbaikan, sparepart, restock, pembelian, lainnya) **sekaligus** Work Order dalam satu langkah, termasuk pada kondisi koneksi kurang stabil. | M |
| FR-02 | SA dapat melampirkan foto/dokumentasi pendukung pada pengajuan, diunggah langsung dari kamera atau galeri perangkat. | M |
| FR-03 | Sistem menghasilkan nomor pengajuan (`request_no`) dan, untuk pengajuan perbaikan, No. TAR (Technical Analysis Report) — keduanya otomatis dan unik, tidak diinput manual. | M |
| FR-04 | SA dapat melihat status dan riwayat pengajuan cabangnya sendiri. | S |

### Modul Work Order / SPK & Pelaksanaan

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-05 | Work Order dibuat otomatis 1:1 begitu pengajuan disubmit (bukan diterbitkan terpisah oleh role lain) — pelaksana (mekanik internal atau bengkel/vendor eksternal) ditentukan SA saat pengajuan, dapat diubah selama Work Order belum final. | M |
| FR-06 | Sistem mencatat rincian item/biaya pekerjaan pada Work Order, termasuk sparepart yang digunakan (rencana saat pengajuan, realisasi final sebelum WO ditandai selesai). | M |
| FR-07 | SA dapat memperbarui status pelaksanaan Work Order (Waiting, On Progress, Finished) beserta dokumentasi pendukung (foto, catatan, invoice); vendor eksternal dicatat sebagai referensi teks pada rincian biaya, tidak memiliki akun login sendiri di TMS. | M |

### Modul Verifikasi & Approval Berjenjang

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-08 | Sistem menyediakan alur verifikasi & approval berjenjang **dinamis** (default: Fleet Operations → Kepala Pool, per cabang) untuk setiap Work Order. | M |
| FR-09 | Urutan, jumlah tahap, dan role approver tiap tahap dapat dikonfigurasi oleh Admin Sistem lewat tabel `approval_steps` tanpa perubahan kode program — **tidak ada** lagi mekanisme ambang nominal Finance (dihapus dari desain final). | M |
| FR-10 | Approver hanya dapat menyetujui/menolak Work Order sesuai tahap approval, role, dan **cabang** miliknya; penolakan wajib disertai alasan. | M |
| FR-11 | Sistem mengirim notifikasi **in-app dan email** kepada approver saat ada Work Order yang menunggu approval mereka, serta ke pengaju saat pengajuan ditolak/selesai. | S |

### Modul Master Data

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-12 | Sistem menyediakan CRUD data master: cabang, armada/fleet, driver, mekanik, bengkel/vendor, warehouse, sparepart, jenis pekerjaan maintenance, jenis biaya operasional — wewenang CRUD berbeda per entitas (Cabang: Admin Sistem saja; Sparepart: Tim Logistik/Fleet Operations/Admin Logistik; entitas lain: Tim Logistik/Fleet Operations). | M |
| FR-13 | Sistem mencatat stok sparepart per warehouse dan mengurangi stok saat realisasi sparepart pada Work Order (bukan saat pengajuan dibuat), hanya untuk pelaksanaan internal. | M |
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
| FR-20 | Sistem menyinkronkan data master armada & driver dari SYOP native secara **berkala berkelanjutan** (terjadwal tiap jam, bukan sekali saat go-live) melalui lapisan adapter — field yang tidak ada di SYOP tetap dikelola mandiri di TMS. | M |
| FR-21 | Sistem menyinkronkan data realisasi PO & pendapatan armada dari SYOP native melalui lapisan adapter (`SyopDataProvider`), bukan real-time per request. **Status: belum berjalan otomatis** — skema `syop_db` belum punya kolom penghubung PO↔armada yang jelas (perlu konfirmasi tim SYOP); sebagai jalur sementara tersedia input manual pendapatan armada per periode. | M |
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
| FR-26 | Pengguna yang punya akun SYOP dapat masuk ke TMS lewat SSO dari SYOP (v3) tanpa login manual berulang; pengguna tanpa akun SYOP tetap login manual (username + password) — lihat Bagian 8.4. | S |

## 7. Kebutuhan Non-Fungsional

| ID | Kategori | Kebutuhan |
|---|---|---|
| NFR-01 | Performance | Response time API < 2 detik untuk 95% request pada beban normal; dashboard/laporan profitabilitas dibaca dari data tersinkron (cache), bukan query real-time ke `syop_db`. |
| NFR-02 | Availability | Uptime sistem ≥ 99% pada jam operasional. |
| NFR-03 | Scalability | Arsitektur backend modular (per domain) agar tiap modul dapat dipecah menjadi service terpisah bila beban bertambah. |
| NFR-04 | Security | Seluruh komunikasi menggunakan HTTPS; kredensial dan token tidak pernah di-hardcode; akses ke `syop_db` memakai user MySQL read-only dengan least privilege. |
| NFR-05 | Auditability | Seluruh aksi approval dan perubahan data berbiaya tercatat pada audit log (pelaku, waktu, aksi, nilai sebelum/sesudah) dan tidak dapat diubah/dihapus oleh pengguna biasa. |
| NFR-06 | Usability | Panel utama (tempat SA membuat pengajuan sekaligus semua modul lain) tetap responsif di perangkat kelas bawah dan koneksi tidak stabil; konsisten mengikuti satu design system (Vuetify, lihat Bagian 8.5) — tidak ada lagi UI terpisah untuk aplikasi lapangan. |
| NFR-07 | Data Integrity | Nilai uang disimpan sebagai tipe desimal presisi tetap (bukan floating point) untuk menghindari galat pembulatan; constraint foreign key menjaga konsistensi relasi data. |
| NFR-08 | Maintainability | Seluruh akses ke SYOP native dibungkus lapisan adapter agar mudah diganti saat migrasi ke SYOP v4, tanpa mengubah logika bisnis inti (lihat Bagian 8.3). |
| NFR-09 | Compatibility | Panel utama (dipakai SA di lapangan maupun role lain di kantor) dapat diakses melalui browser mobile umum tanpa memerlukan instalasi aplikasi native. |
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

**Status: sudah diimplementasikan** (bukan lagi rencana). Bukan OAuth2/Passport seperti rancangan awal — mekanisme yang dipakai:

1. SYOP v3 (sistem lama yang masih dipakai sehari-hari) membuat token berisi payload `{email, wilayah_id, iat, exp, nonce}`, dienkripsi AES-256-GCM dengan key bersama (`SYOP_SSO_KEY`/`SYOP_SSO_AAD`), diformat base64url, valid **60 detik**.
2. Link SSO mengarah ke `https://tms.../sso?token=...` di TMS; backend TMS (`SsoController`) mendekripsi & memverifikasi token (auth tag GCM, exp), lalu mencocokkan `email` ke akun TMS yang sudah terdaftar.
3. TMS **tidak** membuat akun baru otomatis dari SSO — user harus sudah didaftarkan lebih dulu oleh Admin Sistem (lewat User Management) dengan email yang sama.
4. Kalau berhasil, TMS menerbitkan token Bearer (Laravel Sanctum) seperti login manual biasa.

Pengguna tanpa akun SYOP (belum semuanya) tetap memakai login manual — **login manual memakai username, bukan email** (email di setiap akun TMS wajib unik dan sengaja dikhususkan untuk pencocokan SSO di atas; username dipisah supaya satu orang yang memegang lebih dari satu akun, mis. Kepala Pool merangkap beberapa cabang, tidak perlu email berbeda-beda untuk tiap akun login manualnya).

### 8.5 Template UI/UX

**Realisasi berbeda dari rencana awal:** TMS **tidak** memakai template Materio — cakupan lisensi template Materio yang sudah dimiliki perusahaan untuk dipakai di aplikasi kedua (di luar SYOP v4) belum terkonfirmasi (lihat risiko lisensi di Bagian 12), jadi frontend TMS dibangun dari **Vuetify polos** (bukan source template Materio) dengan tema warna biru muda kustom mengikuti nuansa SYOP, supaya tetap terasa senada tanpa bergantung pada kejelasan lisensi tersebut.

Alasan konsistensi platform (Tim Logistik dkk memakai SYOP v4 & TMS bersamaan) dan efisiensi developer (satu ekosistem Vuetify) pada rencana awal tetap relevan dan tercapai lewat pendekatan ini, tanpa risiko lisensi.

**Aplikasi lapangan mobile untuk Driver/Mekanik (rencana awal FR-01/FR-02) sudah dihapus dari desain final** — SA membuat pengajuan langsung di panel utama (bukan lewat UI ringan terpisah), karena Driver/Mekanik tidak lagi punya akun/peran login sendiri di TMS (lihat Bagian 4).

## 9. Integrasi dengan SYOP

Karena TMS dan SYOP native sama-sama menggunakan MySQL, integrasi pada fase awal dapat memanfaatkan query lintas database secara langsung untuk efisiensi. Namun akses ini tetap wajib dibungkus melalui lapisan adapter (Bagian 8.3) agar tidak menimbulkan ketergantungan struktural yang sulit diubah saat SYOP bermigrasi ke v4.

## 10. Alur Approval

> Struktur final — lihat Riwayat Dokumen untuk rancangan awal (4 tahap berbasis ambang nominal) yang sudah tidak berlaku.

Alur verifikasi dan approval default (berlaku seragam untuk seluruh cabang, dapat dikonfigurasi ulang oleh Admin Sistem lewat tabel `approval_steps` tanpa perubahan kode):

1. **Fleet Operations** (per cabang) — verifikasi tahap pertama, dapat mengedit pengajuan atau menolak.
2. **Kepala Pool** (per cabang) — approval tahap akhir, dapat menolak.

**Tidak ada** lagi tahap Branch Manager, Tim Logistik, maupun Finance dalam rantai approval — Tim Logistik & Admin Logistik murni mengelola master data operasional, tidak punya wewenang approval. Tidak ada mekanisme ambang nominal biaya di dalam sistem TMS.

## 11. Asumsi & Batasan

- SYOP native (MySQL) tetap berjalan selama pengembangan dan operasional awal TMS, dan dapat diakses (minimal read access) untuk keperluan integrasi.
- Rencana migrasi SYOP ke SYOP v4 (Laravel 12/PostgreSQL) belum memiliki linimasa pasti; TMS akan menyesuaikan integrasi ketika migrasi tersebut terjadi.
- Modul Asset Registry (IT & GA) dibatasi pada pencatatan/registrasi aset saja pada fase ini, tanpa depresiasi maupun workflow procurement.
- Pengguna sistem Google Apps Script saat ini akan bermigrasi penuh ke TMS setelah go-live.
- Struktur organisasi approval (Fleet Operations, Kepala Pool per cabang) mengikuti struktur yang berlaku saat dokumen ini disusun.

## 12. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| SYOP v4 belum memiliki linimasa migrasi pasti, sementara TMS sudah terlanjur terintegrasi ke SYOP native (MySQL). | Ketergantungan jangka panjang pada stack lama; adaptasi ke SYOP v4 berpotensi mahal bila tidak diantisipasi sejak awal. | Seluruh akses ke SYOP native dibungkus lapisan adapter (`SyopDataProvider`); saat SYOP v4 live, cukup mengganti implementasi adapter tanpa mengubah logika bisnis inti (lihat Bagian 8.3 & 9). |
| TMS dan SYOP native berbagi instance MySQL yang sama. | Beban query TMS ke `syop_db` dapat mengganggu performa SYOP native yang sedang berjalan produksi. | Query ke `syop_db` dibatasi read-only, dilakukan berkala via scheduled job (bukan real-time per request), dan staging TMS diarahkan ke replika `syop_db`, bukan production langsung. |
| Struktur tabel `syop_db` native berubah tanpa koordinasi. | Sinkronisasi data (master armada, realisasi PO, pendapatan) gagal atau menghasilkan data salah. | Perubahan cukup disesuaikan pada lapisan `SyopNativeAdapter` saja; diperlukan koordinasi rutin dengan tim SYOP dan monitoring pada job sinkronisasi. |
| ~~Lisensi template Materio yang dimiliki belum tentu mencakup penggunaan di lebih dari satu aplikasi/domain.~~ | **SELESAI/dihindari** | TMS akhirnya dibangun dari Vuetify polos (bukan source Materio), lihat Bagian 8.5. |
| Resistensi pengguna terhadap migrasi penuh dari Google Apps Script ke TMS. | Proses berjalan paralel (data ganda, potensi selisih), memperlambat tercapainya metrik keberhasilan. | Pelatihan pengguna, migrasi bertahap per cabang, komunikasi dan dukungan dari manajemen saat go-live. |
| ~~SSO belum siap pada awal proyek.~~ | **SELESAI** | SSO dari SYOP v3 sudah diimplementasikan (lihat Bagian 8.4); pengguna tanpa akun SYOP tetap memakai login manual (username). |
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
| Fase 0 — Fondasi | Setup proyek & infrastruktur dasar. | Setup Laravel 12 + Vue 3 (Vuetify) + MySQL, struktur `tms_db` awal, RBAC dasar (roles, permissions, users), koneksi read-only ke `syop_db`. |
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
| **Work Order (WO) / SPK** | Surat Perintah Kerja — dokumen penugasan pekerjaan perbaikan/maintenance, dibuat otomatis 1:1 begitu SA mengajukan pengajuan. |
| **Service Advisor (SA)** | Peran per cabang yang menggantikan Driver/Mekanik pada rancangan awal — satu-satunya pembuat pengajuan, sekaligus pelaksana Work Order dari awal sampai selesai. |
| **Fleet Operations** | Peran per cabang, verifikator tahap pertama pada rantai approval. |
| **Kepala Pool** | Peran per cabang, approver tahap akhir pada rantai approval. |
| **Admin Logistik** | Peran per cabang, full CRUD data sparepart; view-only untuk modul lain, tidak terlibat approval. |
| **Logistik HO** | Peran lintas-cabang (Head Office), read-only — memantau pengajuan/armada/master data/laporan seluruh cabang tanpa wewenang kelola. |
| **No. TAR (Technical Analysis Report)** | Nomor unik yang dibuat otomatis oleh sistem untuk setiap pengajuan perbaikan, terpisah dari `request_no`. |
| **Adapter Layer** | Lapisan abstraksi (service/repository) yang membungkus seluruh akses data dari/ke SYOP, agar implementasinya dapat diganti tanpa mengubah logika bisnis TMS. |
| **SyopDataProvider** | Interface adapter untuk mengakses data SYOP (mis. `getRealisasiPO()`, `getMasterArmada()`, `getPendapatan()`). |
| **SyopNativeAdapter** | Implementasi `SyopDataProvider` saat ini, mengakses `syop_db` (MySQL) secara langsung. |
| **SyopV4Adapter** | Implementasi `SyopDataProvider` di masa depan, memanggil API SYOP v4 setelah migrasi selesai. |
| **SSO (Single Sign-On)** | Mekanisme login dari SYOP v3 ke TMS lewat token terenkripsi (AES-256-GCM, cocok berdasarkan email) — lihat Bagian 8.4. Pengguna tanpa akun SYOP tetap login manual pakai username. |
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

---

**Riwayat Dokumen**

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | Draft awal | Rancangan awal: role Driver/Mekanik/Bengkel-Vendor/Kepala Pool/Branch Manager/Tim Logistik/Finance, approval 4 tahap berbasis ambang nominal Finance, aplikasi lapangan mobile untuk Driver/Mekanik, SSO direncanakan via Laravel Passport/OAuth2, UI direncanakan pakai template Materio. |
| 1.1 | — | (Selaras dengan BRD v1.1 — restrukturisasi peran & alur approval hasil implementasi, belum disinkronkan penuh ke seluruh bagian dokumen ini saat itu.) |
| 1.2 | 2026-09-04 | Sinkronisasi menyeluruh dengan implementasi sebenarnya, dokumen ini sebelumnya banyak tidak sesuai kondisi sistem nyata: Bagian 4 (peran final: SA, Fleet Operations, Kepala Pool, Tim Logistik, **Admin Logistik** [baru], Logistik HO, Admin IT/GA, Admin Sistem, Manajemen — Driver/Mekanik/BM/Finance/Bengkel-Vendor-sebagai-akun dihapus), 5.1 & 10 (approval 2 tahap Fleet Operations → Kepala Pool, bukan 4 tahap), 6 (FR-01–11 disesuaikan alur SA-sentris; FR-12/13 pemisahan permission Cabang/Sparepart; FR-20 sync SYOP berkala per jam bukan sekali saat go-live; FR-21 status implementasi realisasi PO/pendapatan belum otomatis; FR-26 SSO dari SYOP v3, bukan "satu SSO untuk semua"), 8.4 (mekanisme SSO nyata: token AES-256-GCM dari SYOP v3, bukan OAuth2/Passport; login manual pakai username terpisah dari email), 8.5 (UI Vuetify polos, bukan Materio; aplikasi lapangan mobile dihapus), 15 (glosarium: istilah BM dihapus, ditambah SA/Fleet Operations/Admin Logistik/Logistik HO/No. TAR/SSO). |
