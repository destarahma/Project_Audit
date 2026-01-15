-- Update Templates untuk Project Audit
-- Menambahkan template Jual Barbes, Jual Aset, PO Non OA, dan PO Tagging OA

-- Update template Evaluasi Vendor menjadi tidak aktif (jika ingin disembunyikan)
-- UPDATE audit_templates SET is_active = 0 WHERE template_code = 'VENDOR_EVAL_001';
-- UPDATE audit_templates SET is_active = 0 WHERE template_code = 'COMPLIANCE_001';
-- UPDATE audit_templates SET is_active = 0 WHERE template_code = 'PROCESS_AUDIT_001';

-- Atau hapus template yang tidak digunakan (opsional)
-- DELETE FROM audit_templates WHERE template_code IN ('VENDOR_EVAL_001', 'COMPLIANCE_001', 'PROCESS_AUDIT_001');

-- ========================================
-- Template: Self Audit Jual Barbes
-- ========================================
-- Copy struktur dari Mix Oil untuk Jual Barbes
INSERT INTO audit_templates (template_name, template_code, audit_type, description, created_by) 
SELECT 'Self Audit : Jual Barbes', 'BARBES_001', 'mix_oil', 'Self audit untuk proses penjualan Barbes', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM audit_templates WHERE template_code = 'BARBES_001');

SET @barbes_template_id = LAST_INSERT_ID();

-- Copy sections dari Mix Oil template (ID 1 biasanya Mix Oil)
INSERT INTO template_sections (template_id, section_order, section_title)
SELECT @barbes_template_id, section_order, 
       REPLACE(section_title, 'Mixed Oil', 'Barbes')
FROM template_sections 
WHERE template_id = (SELECT id FROM audit_templates WHERE template_code = 'MIX_OIL_001' LIMIT 1)
AND @barbes_template_id > 0;

-- Copy items untuk setiap section
INSERT INTO template_items (section_id, item_order, item_text, field_type, field_options, score_value, is_required)
SELECT 
    ns.id,
    ti.item_order,
    REPLACE(REPLACE(ti.item_text, 'mixed oil', 'Barbes'), 'Mixed Oil', 'Barbes'),
    ti.field_type,
    ti.field_options,
    ti.score_value,
    ti.is_required
FROM template_items ti
JOIN template_sections os ON ti.section_id = os.id
JOIN template_sections ns ON os.section_order = ns.section_order 
    AND ns.template_id = @barbes_template_id
WHERE os.template_id = (SELECT id FROM audit_templates WHERE template_code = 'MIX_OIL_001' LIMIT 1)
AND @barbes_template_id > 0;

-- ========================================
-- Template: Self Audit Jual Aset
-- ========================================
INSERT INTO audit_templates (template_name, template_code, audit_type, description, created_by)
SELECT 'Self Audit : Jual Aset', 'ASET_001', 'mix_oil', 'Self audit untuk proses penjualan aset', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM audit_templates WHERE template_code = 'ASET_001');

SET @aset_template_id = LAST_INSERT_ID();

-- Copy sections untuk Aset
INSERT INTO template_sections (template_id, section_order, section_title)
SELECT @aset_template_id, section_order,
       REPLACE(section_title, 'Mixed Oil', 'Aset')
FROM template_sections
WHERE template_id = (SELECT id FROM audit_templates WHERE template_code = 'MIX_OIL_001' LIMIT 1)
AND @aset_template_id > 0;

-- Copy items untuk Aset
INSERT INTO template_items (section_id, item_order, item_text, field_type, field_options, score_value, is_required)
SELECT 
    ns.id,
    ti.item_order,
    REPLACE(REPLACE(ti.item_text, 'mixed oil', 'aset'), 'Mixed Oil', 'Aset'),
    ti.field_type,
    ti.field_options,
    ti.score_value,
    ti.is_required
FROM template_items ti
JOIN template_sections os ON ti.section_id = os.id
JOIN template_sections ns ON os.section_order = ns.section_order
    AND ns.template_id = @aset_template_id
WHERE os.template_id = (SELECT id FROM audit_templates WHERE template_code = 'MIX_OIL_001' LIMIT 1)
AND @aset_template_id > 0;

-- ========================================
-- Template: PO Non OA
-- ========================================
INSERT INTO audit_templates (template_name, template_code, audit_type, description, max_score, created_by)
SELECT 'PO Non OA', 'PO_NON_OA_001', 'process_audit', 'Template untuk Purchase Order Non OA', 100, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM audit_templates WHERE template_code = 'PO_NON_OA_001');

SET @po_non_oa_id = LAST_INSERT_ID();

-- Sections untuk PO Non OA
INSERT INTO template_sections (template_id, section_order, section_title) 
SELECT @po_non_oa_id, 1, 'Informasi PO' FROM DUAL WHERE @po_non_oa_id > 0;
SET @po_section1 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@po_section1, 1, 'Nomor PO', 'text'),
(@po_section1, 2, 'Tanggal PO', 'date'),
(@po_section1, 3, 'Nama Vendor', 'text'),
(@po_section1, 4, 'Deskripsi Item', 'textarea'),
(@po_section1, 5, 'Jumlah', 'number'),
(@po_section1, 6, 'Harga Satuan', 'number');

INSERT INTO template_sections (template_id, section_order, section_title)
SELECT @po_non_oa_id, 2, 'Dokumen Pendukung' FROM DUAL WHERE @po_non_oa_id > 0;
SET @po_section2 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@po_section2, 1, 'PR (Purchase Request)', 'checkbox'),
(@po_section2, 2, 'Quotation/Penawaran', 'checkbox'),
(@po_section2, 3, 'Approval Manager', 'checkbox'),
(@po_section2, 4, 'Approval Direktur', 'checkbox');

-- ========================================
-- Template: PO Tagging OA
-- ========================================
INSERT INTO audit_templates (template_name, template_code, audit_type, description, max_score, created_by)
SELECT 'PO Tagging OA', 'PO_TAGGING_OA_001', 'process_audit', 'Template untuk Purchase Order dengan Tagging OA', 100, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM audit_templates WHERE template_code = 'PO_TAGGING_OA_001');

SET @po_tagging_id = LAST_INSERT_ID();

-- Sections untuk PO Tagging OA
INSERT INTO template_sections (template_id, section_order, section_title)
SELECT @po_tagging_id, 1, 'Informasi PO & OA' FROM DUAL WHERE @po_tagging_id > 0;
SET @tag_section1 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@tag_section1, 1, 'Nomor PO', 'text'),
(@tag_section1, 2, 'Nomor OA', 'text'),
(@tag_section1, 3, 'Tanggal PO', 'date'),
(@tag_section1, 4, 'Nama Vendor', 'text'),
(@tag_section1, 5, 'Deskripsi Item', 'textarea'),
(@tag_section1, 6, 'Jumlah', 'number'),
(@tag_section1, 7, 'Harga Satuan', 'number');

INSERT INTO template_sections (template_id, section_order, section_title)
SELECT @po_tagging_id, 2, 'Tagging OA' FROM DUAL WHERE @po_tagging_id > 0;
SET @tag_section2 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@tag_section2, 1, 'OA sudah di-tag ke PO', 'checkbox'),
(@tag_section2, 2, 'Nilai OA sesuai dengan PO', 'checkbox'),
(@tag_section2, 3, 'Budget tersedia di OA', 'checkbox'),
(@tag_section2, 4, 'Approval Budget Controller', 'checkbox');

INSERT INTO template_sections (template_id, section_order, section_title)
SELECT @po_tagging_id, 3, 'Dokumen Pendukung' FROM DUAL WHERE @po_tagging_id > 0;
SET @tag_section3 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type) VALUES
(@tag_section3, 1, 'PR (Purchase Request)', 'checkbox'),
(@tag_section3, 2, 'Quotation/Penawaran', 'checkbox'),
(@tag_section3, 3, 'Approval Manager', 'checkbox'),
(@tag_section3, 4, 'Approval Direktur', 'checkbox');

-- Tampilkan hasil
SELECT 'Templates berhasil ditambahkan!' as status;
SELECT template_name, template_code, is_active FROM audit_templates ORDER BY id;
