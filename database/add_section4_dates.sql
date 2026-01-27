-- Tambahkan field tanggal untuk Section 4: Mengeluarkan Barang (Mix Oil template)
-- Item tanggal untuk item 1-5 dengan pola item_order * 10 + 1

-- Cari section_id untuk Section 4 di template Mix Oil
SET @section4_id = (
    SELECT ts.id 
    FROM template_sections ts
    JOIN audit_templates at ON ts.template_id = at.id
    WHERE at.template_code = 'MIX_OIL_001'
    AND ts.section_order = 4
    LIMIT 1
);

-- Tambahkan item tanggal untuk setiap checklist item
INSERT INTO template_items (section_id, item_order, item_text, field_type, score_value, is_required) VALUES
-- Item 11: Tanggal untuk "Surat Tugas instruksi pengambilan mixed Oil"
(@section4_id, 11, 'Tanggal Surat Tugas', 'date', 0, 0),
-- Item 21: Tanggal untuk "Surat pernyataan vendor penggunaan mix oil"
(@section4_id, 21, 'Tanggal Surat Pernyataan', 'date', 0, 0),
-- Item 31: Tanggal untuk "Bukti penimbangan keluar"
(@section4_id, 31, 'Tanggal Penimbangan', 'date', 0, 0),
-- Item 41: Tanggal untuk "Berita acara keluar Mix Oil"
(@section4_id, 41, 'Tanggal Berita Acara', 'date', 0, 0),
-- Item 42: Oleh (person) untuk "Berita acara keluar Mix Oil"
(@section4_id, 42, 'Oleh', 'text', 0, 0),
-- Item 51: Tanggal untuk "Qty Mix oil tidak melebihi SPK/PJB"
(@section4_id, 51, 'Tanggal Pengecekan Qty', 'date', 0, 0);

SELECT 'Item tanggal Section 4 berhasil ditambahkan!' as status;
