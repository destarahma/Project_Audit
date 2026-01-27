-- Add date fields to PO Tagging OA Section 3 (PO)
-- This fixes the missing date column in the PO section

USE audit_system;

-- Get the section ID for PO section of PO Tagging OA template
SET @section_po = (
    SELECT ts.id 
    FROM template_sections ts
    JOIN audit_templates at ON ts.template_id = at.id
    WHERE at.template_code = 'PO_TAGGING_OA' AND ts.section_order = 3
);

-- Add date fields for each Cek item in PO section
-- Cek DD - Tanggal (after item_order 3)
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_po, 4, 'Cek DD - Tanggal', 'date', 0, 0);

-- Cek kondisi Vendor - Tanggal (after item_order 5, becomes 6)
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_po, 7, 'Cek kondisi Vendor - Tanggal', 'date', 0, 0);

-- Cek material/item - Tanggal (after item_order 7, becomes 9)
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_po, 10, 'Cek material/item - Tanggal', 'date', 0, 0);

-- Cek payment term - Tanggal (after item_order 9, becomes 12)
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_po, 13, 'Cek payment term - Tanggal', 'date', 0, 0);

-- Cek harga - Tanggal (after item_order 11, becomes 15)
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_po, 16, 'Cek harga - Tanggal', 'date', 0, 0);

-- Cek qty - Tanggal (after item_order 13, becomes 18)
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_po, 19, 'Cek qty - Tanggal', 'date', 0, 0);

-- Also add date fields for PO Non OA template
SET @section_po_non_oa = (
    SELECT ts.id 
    FROM template_sections ts
    JOIN audit_templates at ON ts.template_id = at.id
    WHERE at.template_code = 'PO_NON_OA' AND ts.section_order = 4
);

-- Add date fields for PO Non OA
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
(@section_po_non_oa, 4, 'Cek DD - Tanggal', 'date', 0, 0),
(@section_po_non_oa, 7, 'Cek kondisi Vendor - Tanggal', 'date', 0, 0),
(@section_po_non_oa, 10, 'Cek material/item - Tanggal', 'date', 0, 0),
(@section_po_non_oa, 13, 'Cek payment term - Tanggal', 'date', 0, 0),
(@section_po_non_oa, 16, 'Cek harga - Tanggal', 'date', 0, 0),
(@section_po_non_oa, 19, 'Cek qty - Tanggal', 'date', 0, 0);

SELECT 'Date fields berhasil ditambahkan ke PO Tagging OA dan PO Non OA!' as Status;
