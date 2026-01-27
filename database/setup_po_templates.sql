-- Setup Template PO Tagging OA dan PO Non OA
-- Jalankan script ini untuk membuat template baru

USE audit_system;

-- ========================================
-- TEMPLATE 8: PO TAGGING OA
-- ========================================
INSERT INTO audit_templates (template_name, template_code, audit_type, description, max_score, created_by, is_active) VALUES
('PO Tagging OA', 'PO_TAGGING_OA', 'process_audit', 'Template untuk audit Purchase Order Tagging OA', 100, 1, 1);

SET @template_po_tagging = LAST_INSERT_ID();

-- Section 1: Informasi PO Tagging OA
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_po_tagging, 1, 'Informasi PO Tagging OA');
SET @po_tagging_s1 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_tagging_s1, 1, 'Pembelian PO tagging OA', 'text', 0, 1),
(@po_tagging_s1, 2, 'Tanggal', 'date', 0, 1),
(@po_tagging_s1, 3, 'Deskripsi', 'textarea', 0, 0),
(@po_tagging_s1, 4, 'Qty', 'number', 0, 1),
(@po_tagging_s1, 5, 'Harga satuan', 'number', 0, 1),
(@po_tagging_s1, 6, 'Total harga', 'text', 0, 0);

-- Section 2: Pengurusan Pembelian
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_po_tagging, 2, 'Pengurusan Pembelian');
SET @po_tagging_s2 = LAST_INSERT_ID();

-- RAP
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_tagging_s2, 1, 'RAP - Ada', 'radio', 5, 0),
(@po_tagging_s2, 2, 'RAP - Tidak ada', 'radio', 0, 0),
(@po_tagging_s2, 3, 'RAP - Tanggal', 'date', 0, 0);

-- Approval RAP
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_tagging_s2, 4, 'Approval RAP - Ada', 'radio', 5, 0),
(@po_tagging_s2, 5, 'Approval RAP - Tidak ada', 'radio', 0, 0),
(@po_tagging_s2, 6, 'Approval RAP - Tanggal', 'date', 0, 0);

-- Drawing / Layout
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_tagging_s2, 7, 'Drawing / Layout - Ada', 'radio', 5, 0),
(@po_tagging_s2, 8, 'Drawing / Layout - Tidak ada', 'radio', 0, 0),
(@po_tagging_s2, 9, 'Drawing / Layout - Tanggal', 'date', 0, 0);

-- PR fully approved
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_tagging_s2, 10, 'PR fully approved - Ada', 'radio', 5, 0),
(@po_tagging_s2, 11, 'PR fully approved - Tidak ada', 'radio', 0, 0),
(@po_tagging_s2, 12, 'PR fully approved - Tanggal', 'date', 0, 0);

-- Section 3: PO
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_po_tagging, 3, 'PO');
SET @po_tagging_s3 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_tagging_s3, 1, 'Input note pembelian PO', 'textarea', 0, 0),
(@po_tagging_s3, 2, 'Cek DD - Sesuai', 'radio', 10, 0),
(@po_tagging_s3, 3, 'Cek DD - Tidak', 'radio', 0, 0),
(@po_tagging_s3, 4, 'Cek kondisi Vendor - Sesuai', 'radio', 10, 0),
(@po_tagging_s3, 5, 'Cek kondisi Vendor - Tidak', 'radio', 0, 0),
(@po_tagging_s3, 6, 'Cek material/item - Sesuai', 'radio', 10, 0),
(@po_tagging_s3, 7, 'Cek material/item - Tidak', 'radio', 0, 0),
(@po_tagging_s3, 8, 'Cek payment term - Sesuai', 'radio', 10, 0),
(@po_tagging_s3, 9, 'Cek payment term - Tidak', 'radio', 0, 0),
(@po_tagging_s3, 10, 'Cek harga - Sesuai', 'radio', 10, 0),
(@po_tagging_s3, 11, 'Cek harga - Tidak', 'radio', 0, 0),
(@po_tagging_s3, 12, 'Cek qty - Sesuai', 'radio', 10, 0),
(@po_tagging_s3, 13, 'Cek qty - Tidak', 'radio', 0, 0),
(@po_tagging_s3, 14, 'Kirim PO', 'textarea', 0, 0);

-- ========================================
-- TEMPLATE 7: PO NON OA
-- ========================================
INSERT INTO audit_templates (template_name, template_code, audit_type, description, max_score, created_by, is_active) VALUES
('PO Non OA', 'PO_NON_OA', 'process_audit', 'Template untuk audit Purchase Order Non OA', 100, 1, 1);

SET @template_po_non_oa = LAST_INSERT_ID();

-- Section 1: Informasi PO Non OA
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_po_non_oa, 1, 'Informasi PO Non OA');
SET @po_non_oa_s1 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s1, 1, 'Pembelian PO Non OA', 'text', 0, 1),
(@po_non_oa_s1, 2, 'Tanggal', 'date', 0, 1),
(@po_non_oa_s1, 3, 'Deskripsi', 'textarea', 0, 0),
(@po_non_oa_s1, 4, 'Qty', 'number', 0, 1),
(@po_non_oa_s1, 5, 'Harga satuan', 'number', 0, 1),
(@po_non_oa_s1, 6, 'Total harga', 'text', 0, 0);

-- Section 2: Pengajuan Pembelian
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_po_non_oa, 2, 'Pengajuan Pembelian');
SET @po_non_oa_s2 = LAST_INSERT_ID();

-- Pq PR
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s2, 1, 'Pq PR - Ada', 'radio', 5, 0),
(@po_non_oa_s2, 2, 'Pq PR - Tidak ada', 'radio', 0, 0),
(@po_non_oa_s2, 3, 'Pq PR - Tanggal', 'date', 0, 0);

-- RAP
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s2, 4, 'RAP - Ada', 'radio', 5, 0),
(@po_non_oa_s2, 5, 'RAP - Tidak ada', 'radio', 0, 0),
(@po_non_oa_s2, 6, 'RAP - Tanggal', 'date', 0, 0);

-- Drawing / Gambar
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s2, 7, 'Drawing / Gambar - Ada', 'radio', 5, 0),
(@po_non_oa_s2, 8, 'Drawing / Gambar - Tidak ada', 'radio', 0, 0),
(@po_non_oa_s2, 9, 'Drawing / Gambar - Tanggal', 'date', 0, 0);

-- Approval Spec
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s2, 10, 'Approval Spec - Ada', 'radio', 5, 0),
(@po_non_oa_s2, 11, 'Approval Spec - Tidak ada', 'radio', 0, 0),
(@po_non_oa_s2, 12, 'Approval Spec - Tanggal', 'date', 0, 0);

-- PR fully approved
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s2, 13, 'PR fully approved - Ada', 'radio', 5, 0),
(@po_non_oa_s2, 14, 'PR fully approved - Tidak ada', 'radio', 0, 0),
(@po_non_oa_s2, 15, 'PR fully approved - Tanggal', 'date', 0, 0);

-- Section 3: Pelaksanaan Pembelian
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_po_non_oa, 3, 'Pelaksanaan Pembelian');
SET @po_non_oa_s3 = LAST_INSERT_ID();

-- Penawaran harga 1
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s3, 1, 'Penawaran harga 1 (nama Vendor)', 'text', 0, 0),
(@po_non_oa_s3, 2, 'Penawaran harga 1 - Harga', 'number', 0, 0);

-- Penawaran harga 2
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s3, 3, 'Penawaran harga 2 (nama Vendor)', 'text', 0, 0),
(@po_non_oa_s3, 4, 'Penawaran harga 2 - Harga', 'number', 0, 0);

-- Penawaran harga 3
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s3, 5, 'Penawaran harga 3 (nama Vendor)', 'text', 0, 0),
(@po_non_oa_s3, 6, 'Penawaran harga 3 - Harga', 'number', 0, 0);

-- Approval QCF/Bid
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s3, 7, 'Approval QCF / Bid', 'text', 0, 0);

-- QCF/Bid
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s3, 8, 'QCF / Bid - Ada', 'radio', 10, 0),
(@po_non_oa_s3, 9, 'QCF / Bid - Tidak ada', 'radio', 0, 0);

-- Nego
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s3, 10, 'Nego - Ada', 'radio', 5, 0),
(@po_non_oa_s3, 11, 'Nego - Tidak ada', 'radio', 0, 0),
(@po_non_oa_s3, 12, 'Nego - Tanggal', 'date', 0, 0);

-- Section 4: PO
INSERT INTO template_sections (template_id, section_order, section_title) VALUES
(@template_po_non_oa, 4, 'PO');
SET @po_non_oa_s4 = LAST_INSERT_ID();

INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@po_non_oa_s4, 1, 'Input note pembelian PO', 'textarea', 0, 0),
(@po_non_oa_s4, 2, 'Cek DD - Sesuai', 'radio', 10, 0),
(@po_non_oa_s4, 3, 'Cek DD - Tidak', 'radio', 0, 0),
(@po_non_oa_s4, 4, 'Cek kondisi Vendor - Sesuai', 'radio', 10, 0),
(@po_non_oa_s4, 5, 'Cek kondisi Vendor - Tidak', 'radio', 0, 0),
(@po_non_oa_s4, 6, 'Cek material/item - Sesuai', 'radio', 10, 0),
(@po_non_oa_s4, 7, 'Cek material/item - Tidak', 'radio', 0, 0),
(@po_non_oa_s4, 8, 'Cek payment term - Sesuai', 'radio', 10, 0),
(@po_non_oa_s4, 9, 'Cek payment term - Tidak', 'radio', 0, 0),
(@po_non_oa_s4, 10, 'Cek harga - Sesuai', 'radio', 10, 0),
(@po_non_oa_s4, 11, 'Cek harga - Tidak', 'radio', 0, 0),
(@po_non_oa_s4, 12, 'Cek qty - Sesuai', 'radio', 10, 0),
(@po_non_oa_s4, 13, 'Cek qty - Tidak', 'radio', 0, 0),
(@po_non_oa_s4, 14, 'Kirim PO', 'textarea', 0, 0);

-- ========================================
-- SUCCESS MESSAGE
-- ========================================
SELECT 'Template PO Tagging OA dan PO Non OA berhasil dibuat!' as Status;
SELECT CONCAT('Template PO Tagging OA ID: ', @template_po_tagging) as Info1;
SELECT CONCAT('Template PO Non OA ID: ', @template_po_non_oa) as Info2;
