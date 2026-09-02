-- ============================================================================
-- TMS Database Schema (tms_db) — DDL MySQL
-- Turunan rinci dari Design Document TMS Bagian 2. Acuan langsung untuk
-- pembuatan migration Laravel.
--
-- Konvensi:
--   - Primary key: BIGINT UNSIGNED AUTO_INCREMENT pada seluruh tabel, kecuali
--     tabel pivot (role_permissions).
--   - Storage engine: InnoDB; character set: utf8mb4 (mendukung emoji & karakter khusus).
--   - Kolom timestamps (created_at, updated_at) memakai DATETIME dengan
--     DEFAULT CURRENT_TIMESTAMP; updated_at menambahkan ON UPDATE CURRENT_TIMESTAMP.
--   - Tabel master utama (fleets, drivers, mechanics, vendors, spareparts, users)
--     memakai soft delete melalui kolom deleted_at.
--   - Nilai uang memakai DECIMAL(15,2) — bukan FLOAT/DOUBLE — untuk menghindari
--     galat pembulatan.
--   - Status disimpan sebagai ENUM eksplisit agar konsisten dengan state machine
--     pada Design Document Bagian 4.2, bukan sebagai kode angka.
--   - Foreign key memakai ON DELETE RESTRICT untuk data transaksional penting,
--     ON DELETE SET NULL untuk relasi opsional, dan ON DELETE CASCADE untuk data
--     anak yang tidak bermakna tanpa induknya (mis. work_order_items, fleet_legal_docs).
--
-- Untuk ERD konseptual (gambaran relasi antar entitas utama), lihat
-- Design Document TMS Bagian 5.2 (Gambar 2).
--
-- Urutan eksekusi migrasi (tabel yang direferensikan harus sudah ada lebih dulu):
--   1. branches
--   2. roles, permissions, role_permissions
--   3. users (referensi ke roles, branches)
--   4. fleets (referensi ke branches)
--   5. drivers, mechanics, warehouses (referensi ke branches/fleets)
--   6. vendors, spareparts (referensi ke warehouses)
--   7. cost_types, job_types
--   8. requests (referensi ke fleets, users)
--   9. work_orders (referensi ke requests, mechanics, vendors)
--   10. work_order_items (referensi ke work_orders, spareparts)
--   11. approval_rules, approval_logs (referensi ke work_orders, users)
--   12. attachments (referensi ke users)
--   13. maintenance_history, fleet_legal_docs, fuel_logs, operational_costs,
--       fleet_revenues (referensi ke fleets, work_orders, job_types, cost_types)
--   14. asset_registry, notifications, audit_logs (referensi ke users)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 2. Tabel Fondasi & Referensi
-- ----------------------------------------------------------------------------

-- 2.1 branches — Master cabang perusahaan.
CREATE TABLE branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Kode cabang',
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.2 roles — Daftar peran pengguna (driver, mekanik, kepala_pool, bm, tim_logistik, finance, admin_it_ga, admin_sistem).
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.3 permissions — Daftar permission granular (mis. request.create, work_order.approve).
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.4 role_permissions — Pivot role <-> permission (RBAC).
CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.5 users — Pengguna TMS, terhubung ke SSO/Identity Provider.
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    sso_id VARCHAR(100) NULL UNIQUE COMMENT 'Identifier dari SSO/Identity Provider',
    role_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 3. Tabel Master Data Operasional
-- ----------------------------------------------------------------------------

-- 3.1 fleets — Master armada/kendaraan.
CREATE TABLE fleets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) NOT NULL UNIQUE COMMENT 'Nomor polisi',
    fleet_type VARCHAR(50) NOT NULL COMMENT 'mis. Tronton, Tangki',
    brand VARCHAR(50) NULL,
    model VARCHAR(50) NULL,
    year SMALLINT UNSIGNED NULL,
    capacity DECIMAL(10,2) NULL COMMENT 'Kapasitas angkut (ton/liter)',
    branch_id BIGINT UNSIGNED NOT NULL,
    status ENUM('aktif','maintenance','nonaktif') NOT NULL DEFAULT 'aktif',
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fleets_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    INDEX idx_fleets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.2 drivers — Master driver.
CREATE TABLE drivers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    license_number VARCHAR(30) NOT NULL UNIQUE COMMENT 'No. SIM',
    license_expiry DATE NULL,
    phone VARCHAR(20) NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    fleet_id BIGINT UNSIGNED NULL COMMENT 'Armada yang sedang ditugaskan',
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_drivers_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_drivers_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.3 mechanics — Mekanik internal.
CREATE TABLE mechanics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mechanics_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.4 vendors — Bengkel/vendor eksternal.
CREATE TABLE vendors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('bengkel','vendor_lain') NOT NULL DEFAULT 'bengkel',
    contact_person VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.5 warehouses — Gudang penyimpanan sparepart.
CREATE TABLE warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    address VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_warehouses_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.6 spareparts — Master sparepart & stok.
CREATE TABLE spareparts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    warehouse_id BIGINT UNSIGNED NOT NULL,
    stock_qty INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_spareparts_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.7 cost_types — Jenis biaya operasional.
CREATE TABLE cost_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3.8 job_types — Jenis pekerjaan maintenance.
CREATE TABLE job_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4. Tabel Transaksional
-- ----------------------------------------------------------------------------

-- 4.1 requests — Pengajuan kebutuhan (perbaikan, sparepart, restock, pembelian, lainnya).
CREATE TABLE requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_no VARCHAR(30) NOT NULL UNIQUE,
    type ENUM('perbaikan','sparepart','restock','pembelian','lainnya') NOT NULL,
    fleet_id BIGINT UNSIGNED NULL,
    requested_by BIGINT UNSIGNED NOT NULL COMMENT 'FK ke users.id (driver/mekanik)',
    description TEXT NOT NULL,
    status ENUM('submitted','pool_verified','bm_verified','logistik_verified','finance_approved','completed','rejected') NOT NULL DEFAULT 'submitted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_requests_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE SET NULL,
    CONSTRAINT fk_requests_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_requests_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.2 work_orders — SPK (internal) / Work Order (eksternal).
-- Status pelaksanaan dan status approval dipisah agar mudah dilacak masing-masing.
CREATE TABLE work_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wo_no VARCHAR(30) NOT NULL UNIQUE,
    request_id BIGINT UNSIGNED NOT NULL,
    execution_type ENUM('internal','eksternal') NULL COMMENT 'Diisi Tim Logistik saat menetapkan pelaksana; WO dibuat otomatis saat requests disubmit, lihat Design Document 3.3',
    mechanic_id BIGINT UNSIGNED NULL,
    vendor_id BIGINT UNSIGNED NULL,
    status ENUM('waiting','on_progress','finished') NOT NULL DEFAULT 'waiting' COMMENT 'Status pelaksanaan pekerjaan',
    approval_status ENUM('submitted','pool_verified','bm_verified','logistik_verified','finance_approved','completed','rejected') NOT NULL DEFAULT 'submitted' COMMENT 'State machine approval, lihat Design Document 4.2',
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wo_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE RESTRICT,
    CONSTRAINT fk_wo_mechanic FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) ON DELETE SET NULL,
    CONSTRAINT fk_wo_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
    INDEX idx_wo_status (status),
    INDEX idx_wo_approval_status (approval_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.3 work_order_items — Rincian item/biaya dalam satu Work Order.
CREATE TABLE work_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_order_id BIGINT UNSIGNED NOT NULL,
    sparepart_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_woi_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_woi_sparepart FOREIGN KEY (sparepart_id) REFERENCES spareparts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.4 approval_rules — Konfigurasi alur & ambang batas approval — data-driven, bukan hardcode (lihat PRD FR-09).
CREATE TABLE approval_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL COMMENT 'Role yang wajib approve, mis. finance',
    min_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    max_amount DECIMAL(15,2) NULL COMMENT 'NULL = tanpa batas atas',
    sequence_order SMALLINT UNSIGNED NOT NULL COMMENT 'Urutan tahap approval',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.5 approval_logs — Jejak verifikasi & approval berjenjang per Work Order.
CREATE TABLE approval_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_order_id BIGINT UNSIGNED NOT NULL,
    approver_role VARCHAR(50) NOT NULL,
    approver_user_id BIGINT UNSIGNED NOT NULL,
    action ENUM('approve','reject') NOT NULL,
    notes VARCHAR(255) NULL,
    approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_al_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_al_user FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.6 attachments — Lampiran foto/catatan/invoice, polymorphic terhadap request/work_order.
CREATE TABLE attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attachable_type VARCHAR(50) NOT NULL COMMENT 'mis. request, work_order',
    attachable_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_att_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_attachable (attachable_type, attachable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5. Tabel Riwayat & Analisis Armada
-- ----------------------------------------------------------------------------

-- 5.1 maintenance_history — Riwayat perbaikan/servis per armada.
CREATE TABLE maintenance_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fleet_id BIGINT UNSIGNED NOT NULL,
    work_order_id BIGINT UNSIGNED NULL,
    job_type_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    cost DECIMAL(15,2) NOT NULL DEFAULT 0,
    performed_at DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mh_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mh_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_mh_jobtype FOREIGN KEY (job_type_id) REFERENCES job_types(id) ON DELETE SET NULL,
    INDEX idx_mh_fleet (fleet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5.2 fleet_legal_docs — Status legalitas armada (STNK/KIR/Pajak/Asuransi) & pengingat jatuh tempo.
CREATE TABLE fleet_legal_docs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fleet_id BIGINT UNSIGNED NOT NULL,
    doc_type ENUM('STNK','KIR','PAJAK','ASURANSI') NOT NULL,
    doc_number VARCHAR(50) NULL,
    issued_date DATE NULL,
    expiry_date DATE NOT NULL,
    file_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fld_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE CASCADE,
    INDEX idx_fld_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5.3 fuel_logs — Riwayat konsumsi BBM per armada.
CREATE TABLE fuel_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fleet_id BIGINT UNSIGNED NOT NULL,
    log_date DATE NOT NULL,
    liters DECIMAL(10,2) NOT NULL,
    cost DECIMAL(15,2) NOT NULL,
    odometer INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fl_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5.4 operational_costs — Agregat biaya operasional per armada, dapat berasal dari Work Order atau input manual.
CREATE TABLE operational_costs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fleet_id BIGINT UNSIGNED NOT NULL,
    cost_type_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    source_type ENUM('work_order','manual') NOT NULL DEFAULT 'manual',
    source_id BIGINT UNSIGNED NULL COMMENT 'FK ke work_orders.id bila source_type=work_order',
    incurred_at DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_oc_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE RESTRICT,
    CONSTRAINT fk_oc_costtype FOREIGN KEY (cost_type_id) REFERENCES cost_types(id) ON DELETE RESTRICT,
    INDEX idx_oc_fleet_date (fleet_id, incurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5.5 fleet_revenues — Cache pendapatan armada, hasil sinkronisasi berkala dari syop_db
-- (lihat Architecture Document Bagian 6).
CREATE TABLE fleet_revenues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fleet_id BIGINT UNSIGNED NOT NULL,
    period CHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
    source_po_number VARCHAR(50) NULL COMMENT 'No. PO dari SYOP',
    amount DECIMAL(15,2) NOT NULL,
    synced_at DATETIME NOT NULL COMMENT 'Waktu sinkronisasi terakhir dari syop_db',
    CONSTRAINT fk_fr_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_fleet_period_po (fleet_id, period, source_po_number),
    INDEX idx_fr_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 6. Tabel Pendukung
-- ----------------------------------------------------------------------------

-- 6.1 asset_registry — Registrasi aset IT & GA — sebatas pencatatan, tanpa
-- depresiasi/procurement (sesuai PRD Bagian 3.1).
CREATE TABLE asset_registry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(30) NOT NULL UNIQUE,
    category ENUM('IT','GA') NOT NULL,
    name VARCHAR(150) NOT NULL,
    pic BIGINT UNSIGNED NULL COMMENT 'FK ke users.id, penanggung jawab aset',
    location VARCHAR(150) NULL,
    purchase_date DATE NULL,
    status ENUM('aktif','rusak','dihapuskan') NOT NULL DEFAULT 'aktif',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_asset_pic FOREIGN KEY (pic) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6.2 notifications — Notifikasi approval tertunda & legalitas jatuh tempo per pengguna.
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'mis. approval_pending, legal_expiry',
    message VARCHAR(255) NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6.3 audit_logs — Audit trail seluruh perubahan approval & biaya
-- (lihat Architecture Document Bagian 7.3).
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    before_data JSON NULL,
    after_data JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 7. Ringkasan Tabel — (belum diisi)
-- ----------------------------------------------------------------------------
