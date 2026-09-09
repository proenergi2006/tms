# Business Requirements Document (BRD) — POD ProEnergi v1.0

> Disusun berdasarkan [PRD POD ProEnergi v2.0](01-PRD.md). Dokumen ini menambahkan elemen level bisnis (business case, proses as-is/to-be, business rules, RACI, kerangka budget, sign-off) yang belum tercakup di PRD, mengikuti pola yang sama dengan [BRD TMS](../00-BRD.md). Bagian bertanda **[DIISI TIM BISNIS]** sengaja dikosongkan — memuat angka pasti (biaya, target KPI, tanggal) tanpa data riil dari pemilik proses akan menyesatkan, bukan membantu.

## 1. Ringkasan Eksekutif

PT Pro Energi saat ini menggunakan aplikasi mobile pihak ketiga **JAVAZ** sebagai alat bantu driver armada BBM dan logistik untuk mencatat proses pengiriman (shipment), termasuk bukti serah terima (Proof of Delivery/POD) berupa foto dan tanda tangan digital. Aplikasi ini memiliki keterbatasan tampilan informasi, alur konfirmasi yang kurang efisien, dan ketergantungan pada vendor eksternal untuk pengembangan fitur maupun integrasi ke sistem inti perusahaan (SYOP) dan sistem monitoring internal (TMS).

**POD ProEnergi** diusulkan sebagai pengganti, dibangun in-house menggunakan Flutter dengan backend & database sendiri, membaca data penugasan shipment dari SYOP dan menyinkronkan hasil operasional (status, lokasi, bukti pengiriman) ke TMS untuk dipantau Tim Logistik/Logistik HO/Manajemen — lihat [Architecture Document POD ProEnergi](02-Architecture.md).

Dokumen ini menjadi dasar persetujuan bisnis (business case) sebelum/di samping spesifikasi produk (PRD) — menjawab **mengapa** proyek ini perlu dijalankan dan **apa nilai bisnisnya**, sebagai pelengkap PRD yang berfokus pada **apa** yang dibangun.

## 2. Latar Belakang & Masalah Bisnis

### 2.1 Kondisi Saat Ini (As-Is)

Proses pencatatan shipment saat ini berjalan melalui JAVAZ dengan karakteristik berikut:

- Informasi penting (nama customer, plat nomor kendaraan) tidak langsung terlihat pada kartu shipment — driver perlu langkah tambahan untuk memastikannya.
- Catatan aktivitas dicatat lewat pop-up terpisah setelah tanda tangan disimpan, bukan tampil langsung pada layar utama proses.
- Tidak ada integrasi langsung ke SYOP (sumber penugasan shipment) maupun ke TMS (sistem monitoring internal Pro Energi) — data operasional lapangan tidak terhubung ke sistem back-office perusahaan.
- Pengembangan fitur baru dan perbaikan bergantung sepenuhnya pada vendor pihak ketiga, membatasi kecepatan dan kendali perusahaan atas roadmap produk.
- Data shipment, POD, dan lokasi driver tersimpan di sistem vendor eksternal, bukan di infrastruktur milik perusahaan.

**[DIISI TIM BISNIS]** Data kuantitatif kondisi saat ini (mis. biaya lisensi/langganan JAVAZ per tahun, jumlah keluhan/sengketa terkait bukti pengiriman per bulan, rata-rata waktu penyelesaian satu shipment) perlu dilengkapi oleh Tim Logistik/Operasional sebagai baseline pembanding pasca-implementasi (lihat Bagian 14).

### 2.2 Dampak Bisnis dari Masalah Ini

- Ketergantungan pada vendor eksternal untuk pengembangan fitur memperlambat respons terhadap kebutuhan operasional yang berubah.
- Data operasional lapangan (posisi driver, status shipment) tidak terlihat dari TMS, sehingga Tim Logistik/Manajemen tidak punya satu titik pantau untuk operasional armada secara menyeluruh (perbaikan/legalitas di TMS, pengiriman di JAVAZ, terpisah).
- Sengketa/klaim terkait bukti pengiriman lebih sulit diselesaikan cepat karena data POD berada di sistem pihak ketiga, bukan arsip internal perusahaan.
- Biaya lisensi/langganan berkelanjutan ke vendor pihak ketiga, dibanding investasi satu kali untuk membangun kapabilitas in-house.

## 3. Tujuan Bisnis (Business Objectives)

1. Mengganti ketergantungan pada aplikasi pihak ketiga (JAVAZ) dengan aplikasi milik perusahaan sendiri, memberi kendali penuh atas roadmap fitur dan kualitas data.
2. Menstandarkan dan mendigitalkan seluruh proses pencatatan shipment — dari penugasan (di SYOP), perjalanan, hingga bukti serah terima muat dan bongkar.
3. Menyediakan visibilitas operasional driver/armada secara terpusat di TMS bagi Tim Logistik, Logistik HO, dan Manajemen, tanpa perlu membuka sistem terpisah.
4. Mempercepat integrasi data operasional lapangan dengan sistem inti perusahaan (SYOP untuk penugasan, TMS untuk monitoring) tanpa bergantung pada kesediaan/kecepatan vendor eksternal.
5. Menghadirkan identitas visual yang konsisten dengan citra merek Pro Energi (tema Biru & Oranye).

## 4. Manfaat yang Diharapkan (Expected Benefits)

| Manfaat | Jenis | Penerima Manfaat |
|---|---|---|
| Kendali penuh atas roadmap fitur & kecepatan perbaikan, tanpa bergantung vendor eksternal | Kemandirian teknologi | Tim IT Development, Manajemen |
| Penghentian biaya lisensi/langganan JAVAZ | Efisiensi biaya | Manajemen, Finance |
| Kartu shipment lebih informatif (customer, plat nomor, status) dan alur konfirmasi lebih ringkas | Efisiensi operasional | Driver |
| Bukti serah terima (POD) tersimpan sebagai arsip resmi internal, bukan di sistem pihak ketiga | Kepastian hukum & audit | Tim Logistik, Manajemen |
| Visibilitas status shipment & posisi driver terpusat di TMS, satu titik pantau bersama data armada lain | Pengambilan keputusan | Tim Logistik, Logistik HO, Manajemen |
| Pengurangan sengketa/klaim terkait bukti pengiriman | Mitigasi risiko | Operasional, Manajemen |
| Identitas visual konsisten dengan merek Pro Energi | Citra perusahaan | Manajemen, Driver |

**[DIISI TIM BISNIS]** Kuantifikasi manfaat (mis. estimasi penghematan biaya lisensi JAVAZ per tahun, estimasi pengurangan waktu penyelesaian sengketa POD) untuk melengkapi analisis biaya-manfaat pada Bagian 5.

## 5. Analisis Biaya-Manfaat (Cost-Benefit — Kerangka)

Proyek ini dikembangkan secara internal (in-house, menggantikan pengadaan/langganan vendor eksternal JAVAZ), sehingga komponen biaya utamanya adalah waktu tim development (mobile Flutter + backend POD), infrastruktur server & storage POD yang terpisah dari TMS/SYOP, dan pelatihan pengguna (driver).

| Komponen Biaya | Estimasi | Catatan |
|---|---|---|
| Waktu tim development (mobile Flutter + backend POD) | **[DIISI TIM BISNIS]** | Dihitung dari alokasi resource internal per fase — lihat roadmap PRD Bagian 17 |
| Infrastruktur backend POD (server, database, storage foto/tanda tangan) | **[DIISI TIM BISNIS]** | Server terpisah dari TMS/SYOP (Architecture Document Bagian 8.1) — perlu koordinasi dengan tim infrastruktur |
| Koordinasi akses read-only ke `syop_db` (jaringan, service account) | **[DIISI TIM BISNIS]** | Melibatkan tim infra/DBA SYOP (Architecture Document Bagian 8.1) |
| Pengembangan endpoint `/api/v1/pod-sync/*` di sisi TMS | **[DIISI TIM BISNIS]** | Dependency lintas proyek — bagian dari backlog tim TMS (Design Document Bagian 3.7) |
| Pelatihan & change management driver | **[DIISI TIM BISNIS]** | Lihat rencana change management, Bagian 11 |
| Kontingensi (buffer risiko) | **[DIISI TIM BISNIS]** | Rekomendasi umum 10–20% dari estimasi total |
| ~~Lisensi/langganan JAVAZ~~ | **[DIISI TIM BISNIS] (dihentikan)** | Biaya berkelanjutan yang dihilangkan setelah migrasi penuh ke POD ProEnergi — nilai penghematan tahunan perlu diisi Finance sebagai pembanding investasi awal |

**Manfaat (benefit)** — kualitatif pada tahap ini (lihat Bagian 4); kuantifikasi finansial (mis. nilai rupiah penghematan lisensi JAVAZ, nilai kerugian yang dicegah dari sengketa POD) memerlukan data historis dari Tim Operasional/Finance yang belum tersedia dalam dokumen ini.

## 6. Ruang Lingkup

Mengikuti [PRD Bagian 3](01-PRD.md#3-ruang-lingkup) — disalin agar dokumen ini tetap mandiri (self-contained) untuk kebutuhan persetujuan bisnis.

### 6.1 Dalam Lingkup

- Aplikasi mobile Flutter untuk Driver: autentikasi, daftar & detail shipment, navigasi, check-in lokasi, laporan (report) muat/bongkar, foto bukti, tanda tangan digital, catatan, riwayat, profil.
- Backend & database POD sendiri, terpisah dari TMS dan SYOP.
- Integrasi baca (read-only) dari SYOP native (`pro_po`/`pro_po_detail`/`pro_po_ds`/`pro_po_ds_detail`) — penugasan shipment dibuat oleh Logistik Cabang di SYOP.
- Integrasi kirim (push) ke TMS — status shipment, lokasi, dan hasil Proof of Delivery, ditampilkan sebagai dashboard monitoring bagi Tim Logistik/Logistik HO/Manajemen.
- Provisioning akun POD driver lewat panel Master Data Driver di TMS, dieksekusi IT.
- Identitas visual & tema Biru & Oranye konsisten dengan merek Pro Energi.

### 6.2 Luar Lingkup (Fase Ini)

- Dashboard/portal back-office terpisah di dalam aplikasi POD sendiri — dashboard monitoring disediakan di TMS.
- Manajemen master data kendaraan, customer, rute (tetap di SYOP) dan master data driver (tetap di TMS).
- Penugasan (assignment) shipment dari dalam aplikasi POD — assignment sepenuhnya di SYOP oleh Logistik Cabang.
- Modul penggajian/insentif driver.
- Mode offline penuh berkepanjangan, dukungan konkurensi multi-trip per driver, dan dukungan iOS pada rilis awal (lihat PRD Bagian 16 untuk rekomendasi fase lanjutan).

## 7. Stakeholder & RACI Matrix

| Aktivitas / Keputusan | Driver | Logistik Cabang (SYOP) | IT / Admin Sistem (TMS) | Tim Logistik / Logistik HO / Manajemen (TMS) | Tim IT Development (POD) |
|---|---|---|---|---|---|
| Membuat & menjadwalkan shipment (PO/PO Detail/PO DS/PO DS Detail) | I | **R/A** | I | I | I |
| Menjalankan shipment: mulai, check-in, report, isi POD | **R/A** | I | I | I | I |
| Provisioning & reset kredensial akun POD driver | I | I | **R/A** | I | C |
| Memantau status shipment, lokasi, dan riwayat POD (read-only) | I | I | I | **R/A** | I |
| Membangun & memelihara aplikasi mobile dan backend POD | I | I | C | I | **R/A** |
| Menyediakan endpoint sinkronisasi (`/api/v1/pod-sync/*`) di sisi TMS | I | I | C | I | **R** (bekerja sama dengan tim TMS) |
| Menyediakan akses read-only ke `syop_db` untuk backend POD | I | I | I | I | **R** (dikoordinasikan dengan tim infra/DBA SYOP) |
| Persetujuan business case (go/no-go) | — | I | I | C | I |

R = Responsible, A = Accountable, C = Consulted, I = Informed. Peran "Logistik Cabang" dan "Tim IT Development (POD)" tidak memakai aplikasi POD ProEnergi maupun TMS untuk aktivitasnya — masing-masing bekerja di SYOP (penugasan) dan di lingkungan pengembangan (build & maintain), sesuai batasan arsitektur pada PRD Bagian 11.

## 8. Proses Bisnis: As-Is vs To-Be

### 8.1 To-Be — Alur Shipment hingga Selesai

Mengikuti [PRD Bagian 6](01-PRD.md#6-alur-proses-bisnis-end-to-end):

1. Logistik Cabang membuat & menjadwalkan shipment (PO/PO Detail/PO DS/PO DS Detail) ke driver & armada tertentu di SYOP.
2. Backend POD membaca penugasan ini secara berkala; shipment muncul di aplikasi driver yang bersangkutan.
3. Driver login, melihat shipment di Beranda, menekan "Mulai".
4. Driver dipandu navigasi ke lokasi muat, check-in kedatangan, mengisi report (foto, tanda tangan, catatan), menyelesaikan aktivitas muat.
5. Driver dipandu navigasi ke lokasi bongkar, check-in, mengisi report, menyelesaikan aktivitas bongkar — shipment tertutup (closed).
6. Data hasil (status, lokasi, POD) disinkronkan ke TMS dan tampil di dashboard monitoring bagi Tim Logistik/Logistik HO/Manajemen.

### 8.2 Perbandingan Ringkas

| Aspek | As-Is (JAVAZ) | To-Be (POD ProEnergi) |
|---|---|---|
| Kepemilikan sistem | Vendor pihak ketiga | In-house, kendali penuh Pro Energi |
| Info customer & plat nomor pada kartu shipment | Tidak langsung terlihat | Tampil langsung (PRD FR-2) |
| Catatan aktivitas | Pop-up terpisah setelah tanda tangan disimpan | Field inline, selalu terlihat (PRD FR-9) |
| Integrasi ke SYOP (penugasan) | Tidak diketahui/terbatas | Baca langsung dari `pro_po`/`pro_po_ds` (PRD FR-16) |
| Integrasi ke TMS (monitoring) | Tidak ada | Sinkronisasi status/lokasi/POD ke dashboard TMS (PRD FR-17) |
| Arsip bukti pengiriman (POD) | Di sistem vendor eksternal | Di infrastruktur milik perusahaan (`pod_db`) |
| Identitas visual | Mengikuti vendor | Tema Biru & Oranye sesuai merek Pro Energi |

## 9. Kebutuhan Bisnis (Business Requirements)

Level bisnis (business capability), sebagai payung dari kebutuhan fungsional detail di [PRD Bagian 7](01-PRD.md#7-kebutuhan-fungsional):

| ID | Kebutuhan Bisnis | Mengacu ke FR (PRD) |
|---|---|---|
| BR-01 | Bisnis membutuhkan autentikasi driver yang aman dan kredensialnya terkendali penuh oleh perusahaan (bukan pendaftaran mandiri). | FR-1, FR-18 |
| BR-02 | Bisnis membutuhkan visibilitas informasi shipment yang lengkap bagi driver sebelum berangkat. | FR-2 |
| BR-03 | Bisnis membutuhkan panduan navigasi terintegrasi ke lokasi muat/bongkar. | FR-4 |
| BR-04 | Bisnis membutuhkan pencatatan bukti serah terima digital (foto, tanda tangan, catatan) yang lengkap dan tidak dapat diubah setelah tersimpan. | FR-5–FR-13 |
| BR-05 | Bisnis membutuhkan arsip riwayat shipment & POD yang dapat diakses kembali sebagai bukti resmi. | FR-14 |
| BR-06 | Bisnis membutuhkan penugasan shipment terbaca otomatis dari SYOP tanpa proses manual/ganda. | FR-16 |
| BR-07 | Bisnis membutuhkan visibilitas operasional driver/armada terpusat di TMS bagi Tim Logistik/Logistik HO/Manajemen. | FR-17 |
| BR-08 | Bisnis membutuhkan mekanisme provisioning akun driver yang terkendali oleh IT, bukan swalayan dari aplikasi. | FR-18 |

## 10. Aturan Bisnis (Business Rules)

- **BRU-01** — Satu driver hanya dapat memiliki **satu shipment aktif** pada satu waktu; tidak ada konkurensi multi-trip pada fase ini (PRD Bagian 9/14).
- **BRU-02** — Penugasan (assignment) shipment ke driver **sepenuhnya terjadi di SYOP** oleh Logistik Cabang (tabel `pro_po`/`pro_po_detail`/`pro_po_ds`/`pro_po_ds_detail`); aplikasi POD tidak memiliki fitur dispatcher/assignment sendiri.
- **BRU-03** — Provisioning dan reset kredensial akun POD driver **hanya dapat dilakukan lewat TMS** oleh IT; aplikasi POD tidak memiliki alur pendaftaran mandiri (self-registration).
- **BRU-04** — Aktivitas muat maupun bongkar **tidak dapat ditandai selesai** sebelum minimal satu foto bukti dan satu tanda tangan digital tersimpan.
- **BRU-05** — Data hasil lapangan (waktu aktivitas, foto, tanda tangan, catatan) bersifat arsip resmi — **write-once**, tidak dapat diubah driver setelah aktivitas ditutup (PRD NFR-09).
- **BRU-06** — Akun driver terkunci sementara otomatis setelah 5 kali percobaan PIN salah berturut-turut, hanya dapat dibuka kembali lewat IT di TMS.
- **BRU-07** — Backend POD **tidak pernah menulis balik** ke database SYOP (akses read-only); sinkronisasi ke TMS bersifat **satu arah** (POD → TMS), TMS tidak pernah menulis balik ke sistem POD.
- **BRU-08** — Aplikasi mobile **tidak pernah** memiliki akses langsung ke database SYOP, TMS, maupun `pod_db` — seluruh interaksi wajib lewat API backend POD (PRD Bagian 11), sebagai kontrol keamanan yang tidak dapat dikecualikan untuk alasan kecepatan pengembangan.

## 11. Rencana Change Management

| Tahapan | Aktivitas | Penanggung Jawab |
|---|---|---|
| Sosialisasi | Komunikasi rencana penggantian JAVAZ ke seluruh driver di 7 cabang sebelum go-live. | Manajemen, Tim Logistik |
| Pelatihan | Pelatihan penggunaan aplikasi POD ProEnergi per driver — fokus pada perbedaan alur dari JAVAZ (mis. catatan inline, navigasi Maps terintegrasi). | Tim Logistik, Tim IT Development |
| Provisioning akun | Pembuatan akun POD (username + PIN awal) untuk seluruh driver aktif lewat TMS sebelum migrasi, dikerjakan IT. | IT / Admin Sistem |
| Migrasi bertahap | Migrasi dari JAVAZ ke POD ProEnergi dilakukan bertahap per cabang, bukan serentak, untuk mengurangi risiko gangguan operasional pengiriman BBM/logistik. | Tim Logistik |
| Masa transisi paralel | Periode berjalan berdampingan dengan JAVAZ (bila diperlukan) untuk validasi kestabilan aplikasi sebelum pelepasan penuh. | Tim Logistik, Manajemen |
| Dukungan pasca go-live | Kanal dukungan untuk pertanyaan/kendala driver pada minggu-minggu awal penggunaan. | Tim IT Development |

**[DIISI TIM BISNIS]** Jadwal rinci migrasi per cabang dan penanggung jawab pelatihan di lapangan.

## 12. Asumsi & Batasan

Mengikuti [PRD Bagian 14](01-PRD.md#14-asumsi--ketergantungan):

- SYOP native tetap berjalan dan dapat diakses secara read-only oleh backend POD selama pengembangan & operasional.
- Tim SYOP bersedia menyediakan akses read-only ke tabel `pro_po`/`pro_po_detail`/`pro_po_ds`/`pro_po_ds_detail` beserta dokumentasi skemanya.
- Tim TMS mengalokasikan kapasitas pengembangan untuk endpoint `/api/v1/pod-sync/*` (dependency lintas proyek, lihat Design Document POD Bagian 3.7).
- Driver menggunakan satu perangkat Android dengan GPS dan kamera yang berfungsi baik.
- Pengguna JAVAZ saat ini akan bermigrasi penuh ke POD ProEnergi setelah go-live, mengakhiri langganan JAVAZ.

## 13. Risiko Bisnis & Mitigasi

Risiko teknis dijabarkan lengkap di [PRD Bagian 15](01-PRD.md#15-risiko--mitigasi) dan [Architecture Document Bagian 10](02-Architecture.md#10-asumsi--batasan-teknis). Risiko level bisnis yang menjadi perhatian tambahan:

| Risiko | Dampak Bisnis | Mitigasi |
|---|---|---|
| Resistensi driver terhadap aplikasi baru setelah terbiasa dengan JAVAZ bertahun-tahun | Adopsi rendah, driver kembali memakai kebiasaan lama secara informal, data operasional tidak lengkap | Pelatihan, migrasi bertahap per cabang, masa transisi paralel (Bagian 11) |
| Ketergantungan pada kesediaan tim SYOP menyediakan akses & dokumentasi skema `pro_po*` | Pengembangan integrasi penugasan (BR-06) tertunda | Koordinasi rutin dengan tim SYOP sejak awal proyek, sebelum development adapter dimulai |
| Ketergantungan pada tim TMS untuk endpoint `/api/v1/pod-sync/*` | Fitur monitoring di TMS (BR-07) tertunda meski aplikasi mobile sudah siap | Rencanakan sebagai item backlog bersama di awal proyek, bukan menunggu POD selesai lebih dulu |
| Penghentian langganan JAVAZ sebelum POD ProEnergi benar-benar stabil di seluruh cabang | Gangguan operasional pengiriman BBM/logistik | Migrasi bertahap per cabang dan masa transisi paralel (Bagian 11), bukan cutover serentak |
| Estimasi waktu/biaya development tidak disepakati sejak awal | Ekspektasi timeline tidak realistis | Lengkapi Bagian 5 & 15 bersama tim development sebelum proyek dimulai penuh |

## 14. Metrik Keberhasilan (KPI)

Mengikuti arah [PRD Bagian 11 (Metrik Keberhasilan, versi lama) dan Bagian 13 (Kriteria Penerimaan Umum)](01-PRD.md), dengan kolom target yang perlu disepakati bersama pemilik proses:

| Metrik | Baseline (As-Is, JAVAZ) | Target (To-Be, POD ProEnergi) | Pemilik |
|---|---|---|---|
| % driver aktif yang memakai POD ProEnergi (dari total driver terdaftar) | 0% | **[DIISI TIM BISNIS]** (rekomendasi: 100% pasca migrasi penuh per cabang) | Tim Logistik |
| % shipment selesai dengan Proof of Delivery lengkap (foto + tanda tangan) | **[DIISI TIM BISNIS]** | **[DIISI TIM BISNIS]** | Tim Logistik |
| Biaya lisensi/langganan JAVAZ | **[DIISI TIM BISNIS]** | Rp 0 (dihentikan) | Finance |
| Ketersediaan dashboard monitoring driver/armada di TMS | Tidak tersedia | Tersedia, data tersinkron dari POD | Tim Logistik, Logistik HO, Manajemen |
| Pengurangan keluhan/sengketa terkait bukti pengiriman | **[DIISI TIM BISNIS]** | **[DIISI TIM BISNIS]** | Tim Logistik, Manajemen |

## 15. Estimasi Timeline (Ringkas)

Mengikuti roadmap fase pada [PRD Bagian 17](01-PRD.md#17-roadmap--milestone-usulan) — linimasa pasti (tanggal/durasi) belum ditetapkan dan perlu disepakati bersama tim development sebelum dokumen ini dianggap final untuk persetujuan.

| Fase | Fokus |
|---|---|
| Fase 1 | Autentikasi driver (provisioning via TMS) + Beranda & daftar shipment dibaca dari SYOP. |
| Fase 2 | Alur lengkap muat/bongkar: navigasi Maps, check-in, report, foto, tanda tangan, penyelesaian shipment. |
| Fase 3 | Tracking lokasi GPS + sinkronisasi ke TMS + dashboard monitoring + Riwayat & Profil. |
| Fase 4 | Hardening non-fungsional (offline queue, retensi data, audit) + evaluasi fitur tambahan sesuai prioritas bisnis. |

## 16. Persetujuan (Sign-off)

Dokumen ini memerlukan persetujuan pemangku kepentingan berikut sebelum proyek dianggap disetujui secara bisnis (business approved):

| Nama | Jabatan | Peran Persetujuan | Tanda Tangan | Tanggal |
|---|---|---|---|---|
| | Sponsor Proyek / Manajemen | Accountable (go/no-go) | | |
| | Kepala Divisi Operasional/Logistik | Consulted | | |
| | Kepala Divisi Finance | Consulted (dampak anggaran — penghentian biaya JAVAZ vs investasi pengembangan in-house, lihat Bagian 5) | | |
| | Tim IT Development Lead | Informed → pelaksana teknis | | |
| | Perwakilan Tim SYOP (DBA/Infra) | Informed → penyedia akses `syop_db` (Bagian 12) | | |

## 17. Glosarium

Mengikuti [PRD Bagian 4](01-PRD.md#4-definisi--istilah) — tidak diduplikasi di sini agar tidak ada dua sumber kebenaran istilah; rujuk langsung ke PRD.

---

**Riwayat Dokumen**

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 2026-09-10 | Draft awal, disusun berdasarkan PRD POD ProEnergi v2.0 dan Architecture/Design Document v1.0. Elemen bisnis (business case, RACI, business rules, kerangka budget, sign-off) dilengkapi mengikuti pola BRD TMS. Bagian bertanda [DIISI TIM BISNIS] masih menunggu data dari pemilik proses (Finance & Tim Logistik). |
