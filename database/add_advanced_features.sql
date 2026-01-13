-- Advanced Features untuk Self Audit System
-- Menambahkan fitur approval otomatis, validasi bertahap, dan conditional logic

USE audit_system;

-- ========================================
-- 1. TABEL APPROVAL RULES
-- ========================================
CREATE TABLE IF NOT EXISTS approval_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    rule_name VARCHAR(100) NOT NULL,
    condition_operator VARCHAR(10) NOT NULL, -- '<', '>', '>=', '<=', 'between'
    condition_value VARCHAR(50) NOT NULL, -- '50000000' atau '50000000-300000000'
    required_approval VARCHAR(200) NOT NULL, -- 'Local DS Mgr + Ref Con'
    approval_level INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES audit_templates(id) ON DELETE CASCADE
);

-- Insert approval rules untuk Mix Oil template
-- Rule 1: Nilai < 50 juta
INSERT INTO approval_rules (template_id, rule_name, condition_operator, condition_value, required_approval, approval_level) VALUES
(1, 'Approval untuk nilai < 50 juta', '<', '50000000', 'Local DS Mgr + Ref Con', 1);

-- Rule 2: Nilai 50-300 juta
INSERT INTO approval_rules (template_id, rule_name, condition_operator, condition_value, required_approval, approval_level) VALUES
(1, 'Approval untuk nilai 50-300 juta', 'between', '50000000-300000000', 'Local DS Mgr + Ref Con + FM Pur + FM Acc', 2);

-- Rule 3: Nilai > 300 juta
INSERT INTO approval_rules (template_id, rule_name, condition_operator, condition_value, required_approval, approval_level) VALUES
(1, 'Approval untuk nilai > 300 juta', '>', '300000000', 'Local DS Mgr + Ref Con + FM Pur + FM Acc + VP', 3);

-- ========================================
-- 2. UPDATE AUDIT_SUBMISSIONS TABLE
-- ========================================
-- Tambah field unit/lokasi dan approval info
ALTER TABLE audit_submissions 
ADD COLUMN IF NOT EXISTS unit_location VARCHAR(200) AFTER seller_name,
ADD COLUMN IF NOT EXISTS required_approvals TEXT AFTER auto_status,
ADD COLUMN IF NOT EXISTS approval_level INT DEFAULT 1 AFTER required_approvals,
ADD COLUMN IF NOT EXISTS has_refund TINYINT(1) DEFAULT 0 AFTER approval_level;

-- ========================================
-- 3. TABEL WORKFLOW STAGES (VALIDASI BERTAHAP)
-- ========================================
CREATE TABLE IF NOT EXISTS workflow_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    stage_number INT NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    is_required TINYINT(1) DEFAULT 1,
    is_conditional TINYINT(1) DEFAULT 0, -- TRUE untuk stage seperti "Pengembalian Dana"
    condition_field VARCHAR(100), -- Field yang menentukan apakah stage ini muncul
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES audit_templates(id) ON DELETE CASCADE
);

-- Insert workflow stages untuk Mix Oil
INSERT INTO workflow_stages (template_id, stage_number, stage_name, is_required, is_conditional) VALUES
(1, 1, 'Pengajuan Usulan Penjualan', 1, 0),
(1, 2, 'Pelaksanaan Penjualan', 1, 0),
(1, 3, 'Penerimaan Pembayaran', 1, 0),
(1, 4, 'Pengeluaran Barang', 1, 0),
(1, 5, 'Pengembalian Dana', 0, 1), -- Conditional stage
(1, 6, 'Dokumentasi', 1, 0);

-- ========================================
-- 4. TABEL AUDIT WORKFLOW PROGRESS
-- ========================================
CREATE TABLE IF NOT EXISTS audit_workflow_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    stage_id INT NOT NULL,
    completed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES audit_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES workflow_stages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission_stage (submission_id, stage_id)
);

-- ========================================
-- 5. TABEL APPROVAL ITEMS (DYNAMIC APPROVAL CHECKLIST)
-- ========================================
CREATE TABLE IF NOT EXISTS approval_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    required_for_level INT NOT NULL, -- 1, 2, atau 3
    item_order INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES audit_templates(id) ON DELETE CASCADE
);

-- Insert approval items untuk Mix Oil
-- Level 1: < 50 juta
INSERT INTO approval_items (template_id, item_name, required_for_level, item_order) VALUES
(1, 'Approval Local DS Manager', 1, 1),
(1, 'Approval Refcon', 1, 2);

-- Level 2: 50-300 juta (cumulative - include level 1)
INSERT INTO approval_items (template_id, item_name, required_for_level, item_order) VALUES
(1, 'Approval Local DS Manager', 2, 1),
(1, 'Approval Refcon', 2, 2),
(1, 'Approval FM Purchasing', 2, 3),
(1, 'Approval FM Accounting', 2, 4);

-- Level 3: > 300 juta (cumulative - include level 1 & 2)
INSERT INTO approval_items (template_id, item_name, required_for_level, item_order) VALUES
(1, 'Approval Local DS Manager', 3, 1),
(1, 'Approval Refcon', 3, 2),
(1, 'Approval FM Purchasing', 3, 3),
(1, 'Approval FM Accounting', 3, 4),
(1, 'Approval VP', 3, 5);

-- ========================================
-- 6. UPDATE TEMPLATE ITEMS (TAMBAH VALIDASI)
-- ========================================
-- Tandai item yang memerlukan tanggal jika "Ada"
UPDATE template_items SET is_required = 1 WHERE item_text LIKE '%ROA%' AND field_type = 'checkbox';
UPDATE template_items SET is_required = 1 WHERE item_text LIKE '%Email%' AND field_type = 'checkbox';
UPDATE template_items SET is_required = 1 WHERE item_text LIKE '%SPK%' AND field_type = 'checkbox';
UPDATE template_items SET is_required = 1 WHERE item_text LIKE '%pembayaran%' AND field_type = 'checkbox';

-- ========================================
-- 7. ADD FIELD VALIDATION RULES
-- ========================================
CREATE TABLE IF NOT EXISTS field_validation_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    validation_type VARCHAR(50) NOT NULL, -- 'required_if_yes', 'min_value', 'max_value', 'date_required'
    validation_value VARCHAR(100),
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES template_items(id) ON DELETE CASCADE
);

-- Tambah validasi: Jika checklist "Ada", maka tanggal wajib
INSERT INTO field_validation_rules (item_id, validation_type, validation_value, error_message)
SELECT id, 'date_required_if_checked', 'ada', 'Tanggal wajib diisi jika dokumen ada'
FROM template_items 
WHERE field_type = 'checkbox' 
AND (item_text LIKE '%ROA%' OR item_text LIKE '%Email%' OR item_text LIKE '%QCF%');

-- ========================================
-- SELESAI
-- ========================================
-- Untuk menjalankan SQL ini:
-- 1. Buka phpMyAdmin
-- 2. Pilih database audit_system
-- 3. Klik tab SQL
-- 4. Copy paste semua isi file ini
-- 5. Klik Go

SELECT 'Advanced features berhasil ditambahkan!' as status;
