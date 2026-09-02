# Business Requirements Document (BRD) — TMS v1.1

> Disusun berdasarkan [PRD (Product Requirements Document) v1.1](01-PRD.md). Dokumen ini menambahkan elemen-elemen level bisnis (business case, proses as-is, business rules, RACI, budget, sign-off) yang belum tercakup di PRD. Bagian bertanda **[DIISI TIM BISNIS]** sengaja dikosongkan/berupa kerangka — memuat angka pasti (biaya, target KPI, tanggal) di sini tanpa data riil dari pemilik proses akan menyesatkan, bukan membantu.

## 1. Ringkasan Eksekutif

PT Pro Energi (PE) saat ini menjalankan proses pengajuan, perbaikan, dan monitoring armada logistik menggunakan sistem berbasis Google Apps Script. Sistem ini menimbulkan keterbatasan skalabilitas, minim integrasi dengan data finansial (SYOP), dan tidak menyediakan visibilitas biaya/profitabilitas per armada secara otomatis.

Transport Management System (TMS) diusulkan sebagai pengganti, dibangun di atas Laravel 12 + Vue 3 + MySQL, terintegrasi dengan SYOP (sistem inti finansial & operasional PE) melalui lapisan adapter agar tahan terhadap rencana migrasi SYOP ke versi baru (SYOP v4).

Dokumen ini menjadi dasar persetujuan bisnis (business case) sebelum/di samping spesifikasi produk (PRD) — menjawab **mengapa** proyek ini perlu dijalankan dan **apa nilai bisnisnya**, sebagai pelengkap PRD yang berfokus pada **apa** yang dibangun.

## 2. Latar Belakang & Masalah Bisnis

### 2.1 Kondisi Saat Ini (As-Is)

Proses operasional armada saat ini berjalan melalui Google Apps Script dengan karakteristik berikut:

- Pengajuan kebutuhan (perbaikan, sparepart, restock, pembelian) dicatat secara manual/spreadsheet, rawan salah input dan sulit ditelusuri riwayatnya.
- Tidak ada alur approval berjenjang yang terstruktur dan dapat dikonfigurasi — status pengajuan sulit dipantau lintas pihak (Kepala Pool, BM, Tim Logistik, Finance).
- Data biaya perawatan armada tidak terhubung dengan data pendapatan (realisasi PO) di SYOP, sehingga profitabilitas per armada tidak dapat dihitung tanpa rekonsiliasi manual.
- Riwayat perbaikan, legalitas (STNK/KIR/Pajak/Asuransi), dan konsumsi BBM per armada tidak terpusat — menyulitkan audit dan perencanaan servis berkala.
- Skalabilitas terbatas seiring bertambahnya jumlah armada dan cabang operasional (saat ini 7 cabang).

**[DIISI TIM BISNIS]** Data kuantitatif kondisi saat ini (mis. rata-rata waktu proses pengajuan-ke-selesai dalam hari, jumlah insiden keterlambatan perpanjangan legalitas per tahun, estimasi jam kerja admin untuk rekonsiliasi manual) perlu dilengkapi oleh Tim Logistik/Operasional sebagai baseline pembanding pasca-implementasi (lihat Bagian 10).

### 2.2 Dampak Bisnis dari Masalah Ini

- Waktu approval yang tidak terpantau berpotensi menunda perbaikan armada, berdampak pada ketersediaan unit operasional.
- Ketiadaan visibilitas profitabilitas per armada menyulitkan keputusan strategis (mis. armada mana yang perlu diremajakan atau dilepas).
- Risiko armada beroperasi dengan dokumen legalitas kedaluwarsa (STNK/KIR/Asuransi) karena tidak ada pengingat otomatis.
- Duplikasi/inkonsistensi data master armada & driver antara catatan lapangan dan SYOP.

## 3. Tujuan Bisnis (Business Objectives)

1. Menggantikan proses manual (Google Apps Script) dengan sistem terpusat, auditable, dan berbasis approval berjenjang yang dapat dikonfigurasi.
2. Menyediakan visibilitas penuh atas riwayat, biaya, dan legalitas setiap armada.
3. Menghubungkan biaya operasional armada (TMS) dengan pendapatan armada (SYOP) untuk menghasilkan analisis profitabilitas per armada secara otomatis.
4. Mengurangi risiko armada beroperasi dengan dokumen legalitas kedaluwarsa melalui notifikasi otomatis.
5. Membangun fondasi integrasi data yang tahan terhadap rencana migrasi SYOP ke SYOP v4, tanpa menunggu migrasi tersebut selesai.

## 4. Manfaat yang Diharapkan (Expected Benefits)

| Manfaat | Jenis | Penerima Manfaat |
|---|---|---|
| Waktu proses pengajuan-ke-approval lebih cepat & terpantau | Efisiensi operasional | Service Advisor (SA), Fleet Operations, Kepala Pool |
| Pengajuan & Work Order dibuat sekaligus dalam satu langkah oleh SA, tanpa serah-terima manual antar peran | Efisiensi operasional | Service Advisor (SA), Tim Logistik |
| Riwayat & legalitas armada terpusat, tidak lagi tersebar di spreadsheet | Kualitas data & audit | Tim Logistik, Manajemen |
| Profitabilitas per armada tersedia otomatis (tanpa rekonsiliasi manual), termasuk biaya vendor eksternal | Pengambilan keputusan | Manajemen, Logistik HO |
| Pemantauan proses seluruh cabang dari satu titik (Logistik HO), tanpa perlu login per cabang | Pengambilan keputusan | Manajemen, Logistik HO |
| Pengurangan risiko denda/insiden akibat legalitas kedaluwarsa | Mitigasi risiko | Operasional, Manajemen |
| Satu sumber kebenaran (single source of truth) data master armada/driver | Kualitas data | Seluruh pengguna, SYOP |

**[DIISI TIM BISNIS]** Kuantifikasi manfaat (mis. estimasi penghematan jam kerja/bulan, estimasi pengurangan biaya keterlambatan) untuk melengkapi analisis biaya-manfaat pada Bagian 5.

## 5. Analisis Biaya-Manfaat (Cost-Benefit — Kerangka)

Proyek ini dikembangkan secara internal (bukan pengadaan vendor eksternal), sehingga komponen biaya utamanya adalah waktu tim development, infrastruktur, dan pelatihan pengguna — bukan biaya lisensi/pembelian perangkat lunak pihak ketiga (kecuali template UI Materio, lihat catatan lisensi di PRD Bagian 8.5).

| Komponen Biaya | Estimasi | Catatan |
|---|---|---|
| Waktu tim development (backend + frontend) | **[DIISI TIM BISNIS]** | Dihitung dari alokasi resource internal per fase (lihat roadmap PRD Bagian 14) |
| Infrastruktur (server, database, backup) | **[DIISI TIM BISNIS]** | Perlu koordinasi dengan tim infrastruktur — lihat NFR-10 (RPO/RTO) di PRD |
| Lisensi template UI (Materio), bila perlu extended license | **[DIISI TIM BISNIS]** | Lihat risiko lisensi di PRD Bagian 12 |
| Pelatihan & change management pengguna | **[DIISI TIM BISNIS]** | Lihat rencana change management, Bagian 11 |
| Kontingensi (buffer risiko) | **[DIISI TIM BISNIS]** | Rekomendasi umum 10–20% dari estimasi total |

**Manfaat (benefit)** — kualitatif pada tahap ini (lihat Bagian 4); kuantifikasi finansial (mis. estimasi nilai rupiah dari efisiensi waktu, atau nilai kerugian yang dicegah dari keterlambatan legalitas) memerlukan data historis dari Tim Operasional/Finance yang belum tersedia dalam dokumen ini.

## 6. Ruang Lingkup

Mengikuti [PRD Bagian 3](01-PRD.md#3-ruang-lingkup) — disalin agar dokumen ini tetap mandiri (self-contained) untuk kebutuhan persetujuan bisnis.

### 6.1 Dalam Lingkup

- Modul Pengajuan Kebutuhan (perbaikan, sparepart, restock, pembelian, lainnya) — dibuat Service Advisor (SA) bersamaan dengan Work Order dalam satu langkah.
- Modul Work Order/SPK (internal & eksternal), termasuk realisasi sparepart terpisah dari rencana awal.
- Modul Pelaksanaan & Monitoring status pekerjaan — dijalankan langsung oleh SA (start s/d selesai).
- Modul Verifikasi & Approval berjenjang **dinamis dan dapat dikonfigurasi** (default: Fleet Operations → Kepala Pool per cabang, tanpa ambang nominal Finance — lihat Bagian 10 BRU-02).
- Modul Master Data (armada, driver, mekanik, vendor, warehouse, sparepart, jenis pekerjaan, jenis biaya).
- Modul Riwayat & Monitoring Armada (perbaikan, legalitas, BBM, biaya, pendapatan, profit/loss).
- Modul Integrasi SYOP (sinkronisasi master armada/driver, realisasi PO, pendapatan).
- Modul Asset Registry (IT & GA) — pencatatan ringan, bukan siklus penuh.

### 6.2 Luar Lingkup (Fase Ini)

- Depresiasi otomatis & akuntansi penuh aset IT/GA.
- Workflow procurement penuh untuk aset non-armada.
- Modul HR/payroll driver & mekanik.
- Migrasi SYOP native ke SYOP v4 (dependency eksternal, dipantau — bukan bagian dari proyek TMS).

## 7. Stakeholder & RACI Matrix

| Aktivitas / Keputusan | SA | Fleet Operations | Kepala Pool | Tim Logistik | Logistik HO | Admin Sistem | Manajemen |
|---|---|---|---|---|---|---|---|
| Membuat pengajuan + Work Order (satu langkah) | **R/A** | I | I | I | I | I | I |
| Verifikasi tahap 1 (bisa edit pengajuan/tolak) | I | **R/A** | I | I | I | I | I |
| Approval akhir tahap 2 (bisa tolak) | I | I | **R/A** | I | I | I | I |
| Melaksanakan pekerjaan & update status (start s/d selesai) | **R** | I | I | I | I | I | I |
| Realisasi sparepart & penutupan Work Order | **R/A** | I | I | I | I | I | I |
| Konfigurasi urutan tahap approval (dinamis) & RBAC | I | I | I | I | I | **R/A** | I |
| Mengelola master data operasional | I | R | I | **R/A** | I | C | I |
| Memantau proses & profitabilitas lintas cabang (read-only) | I | I | I | I | **R/A** | I | C |
| Persetujuan business case (go/no-go) | — | I | I | C | I | I | **A** |

R = Responsible, A = Accountable, C = Consulted, I = Informed. Peran Driver, Mekanik, BM, dan Finance yang ada pada rancangan awal telah dihapus dari struktur final — SA menggantikan fungsi Driver/Mekanik (pengajuan sekaligus eksekusi), sementara Fleet Operations & Kepala Pool menggantikan seluruh rantai BM/Finance dalam approval (lihat Bagian 10 BRU-01/02 dan Riwayat Dokumen).

## 8. Proses Bisnis: As-Is vs To-Be

### 8.1 To-Be — Alur Pengajuan hingga Selesai

Alur final (menggantikan rancangan awal di PRD Bagian 5.1 yang sudah tidak berlaku — lihat Riwayat Dokumen):

1. Service Advisor (SA) cabang membuat pengajuan **sekaligus** Work Order dalam satu langkah — mengisi jenis (perbaikan/sparepart/restock/pembelian/lainnya), jenis perawatan (preventive/corrective untuk perbaikan), diagnosa, prioritas, estimasi lama perbaikan (hari), pelaksana (mekanik internal atau vendor eksternal), dan rencana sparepart/biaya. No. TAR (Technical Analysis Report) dibuat otomatis oleh sistem, tidak diketik manual. Status awal `submitted`.
2. Verifikasi tahap 1 oleh Fleet Operations cabang yang sama — dapat mengedit pengajuan (mis. mengoreksi estimasi/pelaksana) atau menolak dengan alasan.
3. Approval akhir tahap 2 oleh Kepala Pool cabang yang sama — dapat menolak dengan alasan. Urutan & jumlah tahap approval ini bersifat **konfigurasi** (tabel `approval_steps`, diatur Admin Sistem), bukan hardcode — defaultnya dua tahap seperti di atas, berlaku seragam untuk seluruh cabang.
4. Setelah lolos seluruh tahap aktif → `completed`; SA menjalankan pekerjaan (`waiting` → `on_progress`).
5. Sebelum Work Order ditandai selesai, SA wajib melakukan **realisasi sparepart** — menegaskan sparepart yang benar-benar terpakai (boleh berbeda dari rencana awal) dan dapat memperbarui diagnosa akhir. Stok gudang hanya berkurang pada titik ini, dan **hanya** untuk pelaksanaan **internal**; vendor eksternal memakai sparepart sendiri (dicatat sebagai teks bebas, tidak memotong stok TMS).
6. Status `finished`; biaya (termasuk biaya jasa/sparepart dari vendor eksternal) otomatis tercatat ke `operational_cost` dan riwayat ke `maintenance_history`, langsung berkontribusi ke laporan profit/loss armada tanpa gerbang verifikasi tambahan.

### 8.2 Perbandingan Ringkas

| Aspek | As-Is (Google Apps Script) | To-Be (TMS) |
|---|---|---|
| Pencatatan pengajuan | Manual/spreadsheet | Terstruktur, bernomor otomatis, dengan lampiran foto |
| Alur approval | Tidak terstruktur/sulit dipantau | Berjenjang, dikonfigurasi, tercatat di audit log |
| Riwayat armada | Tersebar, tidak terpusat | Terpusat per armada (perbaikan, legalitas, BBM, biaya) |
| Profitabilitas armada | Rekonsiliasi manual dengan SYOP | Otomatis dari sinkronisasi `fleet_revenue` + `operational_cost` |
| Peringatan legalitas kedaluwarsa | Tidak ada/manual | Notifikasi otomatis menjelang jatuh tempo |
| Kontrol akses | Terbatas (akses spreadsheet) | RBAC granular per role & per cabang |

## 9. Kebutuhan Bisnis (Business Requirements)

Level bisnis (business capability), sebagai payung dari kebutuhan fungsional detail di [PRD Bagian 6](01-PRD.md#6-kebutuhan-fungsional):

| ID | Kebutuhan Bisnis | Mengacu ke FR (PRD) |
|---|---|---|
| BR-01 | Bisnis membutuhkan jalur pengajuan kebutuhan armada yang dapat diakses dari lapangan, termasuk pada koneksi tidak stabil. | FR-01–FR-04 |
| BR-02 | Bisnis membutuhkan penerbitan & pelacakan Work Order/SPK untuk pelaksana internal maupun eksternal. | FR-05–FR-07 |
| BR-03 | Bisnis membutuhkan kontrol approval berjenjang yang dapat diubah tanpa perubahan kode, mengikuti kebijakan yang berlaku. | FR-08–FR-11 |
| BR-04 | Bisnis membutuhkan data master operasional yang konsisten & dikelola terpusat. | FR-12–FR-14 |
| BR-05 | Bisnis membutuhkan riwayat & status legalitas armada yang lengkap dan termonitor otomatis. | FR-15–FR-19 |
| BR-06 | Bisnis membutuhkan integrasi data dengan SYOP agar profitabilitas armada dapat dianalisis tanpa rekonsiliasi manual. | FR-20–FR-23 |
| BR-07 | Bisnis membutuhkan pencatatan aset IT & GA yang ringan sebagai referensi inventaris. | FR-24 |
| BR-08 | Bisnis membutuhkan jejak audit atas seluruh perubahan data berbiaya & approval. | FR-25 |
| BR-09 | Bisnis membutuhkan pengalaman login yang efisien lintas sistem (TMS & SYOP). | FR-26 |

## 10. Aturan Bisnis (Business Rules)

- **BRU-01** — Setiap pengajuan wajib melalui seluruh tahap verifikasi **aktif** pada rantai approval sebelum dapat dikerjakan (default: Fleet Operations → Kepala Pool); tidak ada jalur pintas. Urutan, jumlah, dan role penanggung jawab tiap tahap bersifat konfigurasi (lihat BRU-02), bukan hardcode di kode program.
- **BRU-02** — Rantai approval bersifat **dinamis dan dapat dikonfigurasi** oleh Admin Sistem (tabel `approval_steps`) tanpa perubahan kode, dan berlaku **seragam untuk seluruh cabang** — setiap cabang memiliki Fleet Operations dan Kepala Pool sendiri. Mekanisme approval berjenjang berbasis ambang nominal biaya (BM & Finance) pada rancangan awal telah dihapus dari desain final.
- **BRU-03** — Penolakan pada tahap manapun wajib disertai alasan tertulis dan menghentikan alur (tidak lanjut ke tahap berikutnya).
- **BRU-04** — Setiap approver hanya berwenang bertindak pada tahap yang menjadi wewenangnya di cabangnya sendiri; sistem menolak aksi approval di luar tahap/role/cabang yang sesuai.
- **BRU-05** — Pengguna dengan role bercabang (SA, Fleet Operations, Kepala Pool, Tim Logistik) hanya dapat mengakses & mengelola data cabangnya sendiri; role Head Office (Admin IT/GA, Admin Sistem, Manajemen, **Logistik HO**) beroperasi lintas cabang. Logistik HO khusus bersifat pemantauan (hanya melihat pengajuan/armada/master data/laporan seluruh cabang), tanpa wewenang membuat, mengelola, atau approval.
- **BRU-06** — Stok sparepart tidak boleh menjadi negatif dan **hanya berkurang untuk pelaksanaan internal**, tepat pada saat realisasi sparepart (bukan saat pengajuan dibuat); permintaan pemakaian yang melebihi stok tersedia ditolak sistem. Pelaksanaan oleh vendor eksternal tidak pernah memotong stok gudang TMS — item dicatat sebagai teks bebas, bukan tertaut ke katalog sparepart.
- **BRU-07** — Biaya operasional & riwayat perbaikan dicatat final ke armada begitu pekerjaan selesai secara fisik **dan** lolos seluruh tahap approval — termasuk biaya jasa/sparepart dari vendor eksternal, tanpa gerbang verifikasi tambahan pasca-selesai.
- **BRU-08** — Seluruh perubahan pada data approval dan data berbiaya wajib tercatat pada audit log dan tidak dapat diubah/dihapus oleh pengguna biasa.
- **BRU-09** — No. TAR (Technical Analysis Report) dibuat otomatis oleh sistem untuk setiap pengajuan perbaikan, tidak diinput manual, untuk menjamin keunikan & konsistensi penomoran.

## 11. Rencana Change Management

| Tahapan | Aktivitas | Penanggung Jawab |
|---|---|---|
| Sosialisasi | Komunikasi rencana migrasi ke seluruh pengguna (SA, Fleet Operations, Kepala Pool, Tim Logistik, Logistik HO) sebelum go-live. | Manajemen, Tim Logistik |
| Pelatihan | Pelatihan penggunaan aplikasi per role — SA sebagai titik masuk utama (pengajuan sekaligus eksekusi Work Order), Fleet Operations & Kepala Pool untuk alur approval. | Tim Logistik, Admin Sistem |
| Migrasi bertahap | Migrasi dilakukan bertahap per cabang (bukan serentak) untuk mengurangi risiko gangguan operasional. | Tim Logistik |
| Masa transisi paralel | Periode berjalan berdampingan dengan Google Apps Script (bila diperlukan) untuk validasi data sebelum pelepasan penuh. | Tim Logistik, Manajemen |
| Dukungan pasca go-live | Kanal dukungan untuk pertanyaan/kendala pengguna pada minggu-minggu awal. | Admin Sistem |

**[DIISI TIM BISNIS]** Jadwal rinci per cabang & penanggung jawab pelatihan di lapangan.

## 12. Asumsi & Batasan

Mengikuti [PRD Bagian 11](01-PRD.md#11-asumsi--batasan):

- SYOP native tetap berjalan dan dapat diakses (minimal read-only) selama pengembangan & operasional awal TMS.
- Migrasi SYOP ke SYOP v4 belum memiliki linimasa pasti; integrasi TMS akan menyesuaikan saat migrasi terjadi.
- Modul Asset Registry dibatasi pencatatan saja pada fase ini.
- Pengguna Google Apps Script saat ini akan bermigrasi penuh ke TMS setelah go-live.
- Struktur organisasi approval mengikuti struktur yang berlaku saat dokumen ini disusun; perubahan struktur organisasi di kemudian hari memerlukan penyesuaian konfigurasi (bukan perubahan kode).

## 13. Risiko Bisnis & Mitigasi

Risiko teknis dijabarkan lengkap di [PRD Bagian 12](01-PRD.md#12-risiko--mitigasi). Risiko level bisnis yang menjadi perhatian tambahan:

| Risiko | Dampak Bisnis | Mitigasi |
|---|---|---|
| Resistensi pengguna terhadap sistem baru | Adopsi rendah, data ganda antara TMS & proses lama, metrik keberhasilan tidak tercapai | Pelatihan, migrasi bertahap per cabang, dukungan aktif dari Manajemen saat go-live (lihat Bagian 11) |
| Ketergantungan pada ketersediaan tim SYOP untuk konfirmasi struktur data (mis. mapping PO↔armada) | Fitur profitabilitas otomatis (BR-06) tertunda | Koordinasi rutin dengan tim SYOP; jalur input manual sementara sebagai stopgap |
| Estimasi waktu/biaya development tidak disepakati sejak awal | Ekspektasi timeline tidak realistis, proyek berpotensi molor tanpa terdeteksi | Lengkapi Bagian 5 & 14 bersama tim development sebelum proyek dimulai penuh |
| Perubahan struktur organisasi approval di tengah proyek | Aturan approval perlu dikonfigurasi ulang | Aturan approval dirancang sebagai konfigurasi (bukan hardcode), lihat BRU-02 |

## 14. Metrik Keberhasilan (KPI)

Mengikuti arah [PRD Bagian 13](01-PRD.md#13-metrik-keberhasilan), dengan kolom target yang perlu disepakati bersama pemilik proses:

| Metrik | Baseline (As-Is) | Target (To-Be) | Pemilik |
|---|---|---|---|
| % pengajuan yang diproses melalui TMS (bukan manual/spreadsheet) | 0% | **[DIISI TIM BISNIS]** (rekomendasi: 100% pasca migrasi penuh) | Tim Logistik |
| Rata-rata waktu proses pengajuan hingga approval selesai (Fleet Operations → Kepala Pool) | **[DIISI TIM BISNIS]** | **[DIISI TIM BISNIS]** | Tim Logistik, Fleet Operations |
| % armada dengan dokumen legalitas terpantau tanpa keterlambatan | **[DIISI TIM BISNIS]** | **[DIISI TIM BISNIS]** | Tim Logistik |
| Ketersediaan laporan profit/loss otomatis per armada | Tidak tersedia | Tersedia real-time dari data tersinkron | Manajemen, Logistik HO |
| Duplikasi data master armada/driver antara TMS & SYOP | **[DIISI TIM BISNIS]** | 0 (single source of truth) | Admin Sistem |

## 15. Estimasi Timeline (Ringkas)

Mengikuti roadmap fase pada [PRD Bagian 14](01-PRD.md#14-roadmap-pengembangan-ringkas) — linimasa pasti (tanggal/durasi) belum ditetapkan dan perlu disepakati bersama tim development sebelum dokumen ini dianggap final untuk persetujuan.

| Fase | Fokus |
|---|---|
| Fase 0 | Fondasi (setup proyek, RBAC dasar) |
| Fase 1 | Master Data & Pengajuan |
| Fase 2 | Work Order & Approval |
| Fase 3 | Riwayat & Legalitas Armada |
| Fase 4 | Integrasi SYOP & Profitabilitas |
| Fase 5 | Asset Registry, SSO, migrasi penuh & go-live |

## 16. Persetujuan (Sign-off)

Dokumen ini memerlukan persetujuan pemangku kepentingan berikut sebelum proyek dianggap disetujui secara bisnis (business approved):

| Nama | Jabatan | Peran Persetujuan | Tanda Tangan | Tanggal |
|---|---|---|---|---|
| | Sponsor Proyek / Manajemen | Accountable (go/no-go) | | |
| | Kepala Divisi Operasional/Logistik | Consulted | | |
| | Kepala Divisi Finance | Consulted (dampak anggaran proyek — approval biaya berbasis ambang nominal di dalam sistem TMS sendiri sudah dihapus dari desain final, lihat BRU-02) | | |
| | Admin Sistem / IT Lead | Informed → pelaksana teknis | | |

## 17. Glosarium

Mengikuti [PRD Bagian 15](01-PRD.md#15-lampiran--glosarium) — tidak diduplikasi di sini agar tidak ada dua sumber kebenaran istilah; rujuk langsung ke PRD.

---

**Riwayat Dokumen**

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | Draft awal | Disusun dari PRD v1.1 + elemen BRD (business case, RACI, business rules, budget framework, sign-off). Bagian bertanda [DIISI TIM BISNIS] masih menunggu data dari pemilik proses. |
| 1.1 | 2026-08-13 | Restrukturisasi peran & alur approval hasil implementasi — menggantikan rancangan awal (Driver/Mekanik/Kepala Pool/BM/Tim Logistik/Finance, 4 tahap approval berbasis ambang nominal) dengan struktur final: **Service Advisor (SA)** membuat pengajuan sekaligus Work Order dan menjalankannya sendiri hingga selesai (Driver/Mekanik dihapus); approval jadi **2 tahap dinamis & dapat dikonfigurasi** (Fleet Operations → Kepala Pool per cabang, BM & Finance dihapus dari rantai approval); No. TAR dibuat otomatis sistem; realisasi sparepart terpisah dari rencana awal dan **hanya memotong stok untuk pelaksana internal** (vendor eksternal tidak menyentuh stok, tapi biayanya tetap masuk profit/loss armada); ditambahkan role **Logistik HO** (pemantauan lintas cabang, read-only). Bagian yang diperbarui: 4, 6.1, 7 (RACI), 8.1, 10 (BRU-01/02/05/06/07, tambahan BRU-09), 11, 14, 16. |
