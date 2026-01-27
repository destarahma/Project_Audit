-- Self Audit System Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS audit_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE audit_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'auditor', 'viewer') DEFAULT 'auditor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Audit templates table
CREATE TABLE IF NOT EXISTS audit_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    template_code VARCHAR(50) UNIQUE NOT NULL,
    audit_type ENUM('mix_oil', 'vendor_evaluation', 'process_audit', 'compliance_check') NOT NULL,
    description TEXT,
    scoring_enabled TINYINT(1) DEFAULT 1,
    max_score INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Audit template sections
CREATE TABLE IF NOT EXISTS template_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    section_order INT NOT NULL,
    section_title VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES audit_templates(id) ON DELETE CASCADE
);

-- Audit template items (checklist items)
CREATE TABLE IF NOT EXISTS template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    item_order INT NOT NULL,
    item_text TEXT NOT NULL,
    field_type ENUM('checkbox', 'date', 'text', 'number', 'radio', 'textarea', 'select') DEFAULT 'checkbox',
    field_options TEXT,
    score_value INT DEFAULT 0,
    is_required TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES template_sections(id) ON DELETE CASCADE
);

-- Audit submissions (main audit records)
CREATE TABLE IF NOT EXISTS audit_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    audit_number INT DEFAULT NULL COMMENT 'Nomor audit per template, dimulai dari 1',
    submitted_by INT NOT NULL,
    submission_date DATE NOT NULL,
    seller_name VARCHAR(100),
    quantity VARCHAR(50),
    unit_price VARCHAR(50),
    total_price VARCHAR(50),
    total_score INT DEFAULT 0,
    percentage_score DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('draft', 'submitted', 'reviewed', 'approved', 'rejected') DEFAULT 'draft',
    auto_status VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES audit_templates(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    INDEX idx_template_audit (template_id, audit_number)
);

-- Audit responses (answers to checklist items)
CREATE TABLE IF NOT EXISTS audit_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    item_id INT NOT NULL,
    response_value TEXT,
    response_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES audit_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES template_items(id)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$vWY6yUeSwdb2F6/AurMtieFBA9m7jF08oKaEEgDIIU/bxMckgCOsu', 'Administrator', 'admin@audit.com', 'admin');

-- Insert Mix Oil template
INSERT INTO audit_templates (template_name, template_code, audit_type, description, created_by) VALUES
('Self Audit Jual Beli Mix Oil', 'MIX_OIL_001', 'mix_oil', 'Template untuk audit penjualan dan pembelian Mix Oil', 1);

-- Get the template ID (assuming it's 1)
SET @template_id = LAST_INSERT_ID();

-- Section 1: Pengajuan Usulan Penjualan
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id, 1, 'Pengajuan Usulan Penjualan');
SET @section1 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@section1, 1, 'ROA (dan QFS)', 'checkbox'),
(@section1, 2, 'Email konfirmasi penjualan ke Trading', 'checkbox'),
(@section1, 3, 'Email usulan penjualan mixed oil (dari User)', 'checkbox');

-- Section 2: Pelaksanaan Penjualan
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id, 2, 'Pelaksanaan Penjualan');
SET @section2 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@section2, 1, 'Penawaran harga 1 (nama Vendor)', 'text'),
(@section2, 2, 'Penawaran harga 2 (nama Vendor)', 'text'),
(@section2, 3, 'Penawaran harga 3 (nama Vendor)', 'text'),
(@section2, 4, 'Approval QCF', 'checkbox'),
(@section2, 5, 'QCF', 'checkbox'),
(@section2, 6, 'Vega', 'checkbox'),
(@section2, 7, 'Periode QCF', 'date'),
(@section2, 8, 'SPK / PJB', 'checkbox'),
(@section2, 9, 'Approval SPK / PJB dari Customer', 'checkbox'),
(@section2, 10, 'Kirim email SPK /PJB full approved ke Refcon dan Kaber', 'checkbox');

-- Section 3: Penerimaan Pembayaran
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id, 3, 'Penerimaan Pembayaran');
SET @section3 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@section3, 1, 'Konfirmasi Qty dan Harga dan Total pembayaran', 'checkbox'),
(@section3, 2, 'Bukti Transfer Pembayaran I (DP minimal 50%)', 'checkbox'),
(@section3, 3, 'Nilai trasfer I', 'number'),
(@section3, 4, 'Info konfirmasi penerimaan pembayaran I', 'radio'),
(@section3, 5, 'Bukti Transfer Pembayaran II (pelunasan sebelum barang keluar)', 'checkbox'),
(@section3, 6, 'Nilai trasfer II', 'number'),
(@section3, 7, 'Info konfirmasi penerimaan pembayaran II', 'radio'),
(@section3, 8, 'Sisa', 'number'),
(@section3, 9, 'Email instruksi pengambilan mixed oil ke Customer, cc: User dan Web', 'checkbox');

-- Section 4: Mengeluarkan Barang
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id, 4, 'Mengeluarkan Barang');
SET @section4 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@section4, 1, 'Surat Tugas instruksi pengambilan mixed Oil', 'checkbox'),
(@section4, 2, 'Surat pernyataan vendor penggunaan mix oil', 'checkbox'),
(@section4, 3, 'Bukti penimbangan keluar', 'checkbox'),
(@section4, 4, 'Berita acara keluar Mix Oil', 'checkbox'),
(@section4, 5, 'Cty Mix oil tidak melebihi SPK/PJB', 'checkbox');

-- Section 5: Pengembalian Dana (jika ada)
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id, 5, 'Pengembalian Dana (jika ada)');
SET @section5 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@section5, 1, 'Surat permohonan pengembalian dana dr Customer', 'checkbox'),
(@section5, 2, 'Bukti transfer dari SIP', 'checkbox'),
(@section5, 3, 'Jumlah', 'number');

-- Section 6: Dokumentasi
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id, 6, 'Dokumentasi');
SET @section6 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value) VALUES
(@section6, 1, 'File pendukung disimpan di File.. Purchasing/Data/Departemen/Operational/Mix Oil/', 'radio', 5);

-- ========================================
-- Template 2: Vendor Evaluation
-- ========================================
INSERT INTO audit_templates (template_name, template_code, audit_type, description, max_score, created_by) VALUES
('Self Audit Evaluasi Vendor', 'VENDOR_EVAL_001', 'vendor_evaluation', 'Template untuk evaluasi performa vendor', 100, 1);

SET @template_id2 = LAST_INSERT_ID();

-- Section 1: Kualitas Produk
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id2, 1, 'Kualitas Produk');
SET @section_v1 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_v1, 1, 'Produk sesuai spesifikasi', 'radio', 15, 1),
(@section_v1, 2, 'Sertifikat kualitas lengkap', 'checkbox', 10, 1),
(@section_v1, 3, 'Tidak ada komplain kualitas', 'checkbox', 10, 1),
(@section_v1, 4, 'Catatan kualitas', 'textarea', 0, 0);

-- Section 2: Ketepatan Waktu
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id2, 2, 'Ketepatan Waktu');
SET @section_v2 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_v2, 1, 'Pengiriman tepat waktu', 'radio', 15, 1),
(@section_v2, 2, 'Respon komunikasi cepat', 'radio', 10, 1),
(@section_v2, 3, 'Tanggal pengiriman', 'date', 0, 1);

-- Section 3: Harga dan Pembayaran
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id2, 3, 'Harga dan Pembayaran');
SET @section_v3 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_v3, 1, 'Harga kompetitif', 'radio', 15, 1),
(@section_v3, 2, 'Fleksibilitas pembayaran', 'radio', 10, 1),
(@section_v3, 3, 'Invoice sesuai PO', 'checkbox', 5, 1);

-- Section 4: Pelayanan
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id2, 4, 'Pelayanan');
SET @section_v4 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_v4, 1, 'Sikap profesional', 'radio', 10, 1);

-- ========================================
-- Template 3: Process Audit
-- ========================================
INSERT INTO audit_templates (template_name, template_code, audit_type, description, max_score, created_by) VALUES
('Self Audit Proses Operasional', 'PROCESS_AUDIT_001', 'process_audit', 'Template untuk audit proses operasional internal', 100, 1);

SET @template_id3 = LAST_INSERT_ID();

-- Section 1: Persiapan
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id3, 1, 'Persiapan');
SET @section_p1 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_p1, 1, 'SOP tersedia dan terkini', 'checkbox', 10, 1),
(@section_p1, 2, 'Tim sudah briefing', 'checkbox', 5, 1),
(@section_p1, 3, 'Peralatan siap digunakan', 'checkbox', 5, 1),
(@section_p1, 4, 'Material tersedia', 'checkbox', 5, 1);

-- Section 2: Pelaksanaan
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id3, 2, 'Pelaksanaan');
SET @section_p2 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_p2, 1, 'Proses sesuai SOP', 'radio', 20, 1),
(@section_p2, 2, 'Dokumentasi lengkap', 'checkbox', 10, 1),
(@section_p2, 3, 'Tidak ada deviasi proses', 'checkbox', 10, 1),
(@section_p2, 4, 'Safety protocol diikuti', 'checkbox', 10, 1);

-- Section 3: Quality Check
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id3, 3, 'Quality Check');
SET @section_p3 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_p3, 1, 'Inspeksi dilakukan', 'checkbox', 10, 1),
(@section_p3, 2, 'Hasil sesuai standar', 'radio', 10, 1),
(@section_p3, 3, 'Catatan QC lengkap', 'checkbox', 5, 1);

-- ========================================
-- Template 4: Compliance Check
-- ========================================
INSERT INTO audit_templates (template_name, template_code, audit_type, description, max_score, created_by) VALUES
('Self Audit Kepatuhan Regulasi', 'COMPLIANCE_001', 'compliance_check', 'Template untuk audit kepatuhan terhadap regulasi', 100, 1);

SET @template_id4 = LAST_INSERT_ID();

-- Section 1: Dokumen Legalitas
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id4, 1, 'Dokumen Legalitas');
SET @section_c1 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_c1, 1, 'Izin usaha masih berlaku', 'checkbox', 15, 1),
(@section_c1, 2, 'NPWP aktif', 'checkbox', 10, 1),
(@section_c1, 3, 'Sertifikat ISO (jika ada)', 'checkbox', 5, 0),
(@section_c1, 4, 'Tanggal expired izin', 'date', 0, 1);

-- Section 2: Kepatuhan Lingkungan
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id4, 2, 'Kepatuhan Lingkungan');
SET @section_c2 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_c2, 1, 'Dokumen UKL/UPL lengkap', 'checkbox', 15, 1),
(@section_c2, 2, 'Pengelolaan limbah sesuai aturan', 'radio', 15, 1),
(@section_c2, 3, 'Tidak ada pelanggaran lingkungan', 'checkbox', 10, 1);

-- Section 3: Keselamatan Kerja
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_id4, 3, 'Keselamatan Kerja');
SET @section_c3 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_c3, 1, 'APD tersedia dan digunakan', 'checkbox', 10, 1),
(@section_c3, 2, 'APAR tersedia dan layak', 'checkbox', 10, 1),
(@section_c3, 3, 'Jalur evakuasi jelas', 'checkbox', 5, 1),
(@section_c3, 4, 'P3K tersedia', 'checkbox', 5, 1);
