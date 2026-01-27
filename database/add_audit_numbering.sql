-- Script untuk menambahkan kolom audit_number dan mengatur penomoran per template
-- Penomoran akan direset untuk setiap template yang berbeda

USE audit_system;

-- Tambahkan kolom audit_number ke tabel audit_submissions
ALTER TABLE audit_submissions 
ADD COLUMN audit_number INT DEFAULT NULL AFTER template_id,
ADD INDEX idx_template_audit (template_id, audit_number);

-- Update audit_number untuk data yang sudah ada
-- Menggunakan ROW_NUMBER untuk generate nomor unik per template
SET @row_num = 0;
SET @prev_template = NULL;

UPDATE audit_submissions AS s
JOIN (
    SELECT 
        id,
        template_id,
        @row_num := IF(@prev_template = template_id, @row_num + 1, 1) AS new_audit_number,
        @prev_template := template_id
    FROM audit_submissions
    ORDER BY template_id, created_at ASC
) AS numbered ON s.id = numbered.id
SET s.audit_number = numbered.new_audit_number;

SELECT 'Kolom audit_number berhasil ditambahkan dan penomoran diupdate!' as Status;

-- Tampilkan hasil penomoran
SELECT 
    s.id,
    s.audit_number,
    t.template_name,
    s.submission_date,
    s.status
FROM audit_submissions s
JOIN audit_templates t ON s.template_id = t.id
ORDER BY t.template_name, s.audit_number;
