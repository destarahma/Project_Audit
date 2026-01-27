-- Reset Audit Numbers to Start from 1 for Each Template
-- This will renumber all existing audits to start from 1 per template

-- Backup current numbers (optional, for safety)
CREATE TEMPORARY TABLE IF NOT EXISTS backup_audit_numbers AS
SELECT id, template_id, audit_number 
FROM audit_submissions;

-- Update audit numbers to start from 1 for each template using ROW_NUMBER
SET @row_num = 0;
SET @current_template = 0;

UPDATE audit_submissions
SET audit_number = (
    SELECT rn FROM (
        SELECT 
            id,
            @row_num := IF(@current_template = template_id, @row_num + 1, 1) AS rn,
            @current_template := template_id
        FROM audit_submissions
        ORDER BY template_id, submission_date, id
    ) AS numbered
    WHERE numbered.id = audit_submissions.id
);

-- Verify the results
SELECT 
    t.template_name,
    COUNT(*) as total_audits,
    MIN(aus.audit_number) as min_number,
    MAX(aus.audit_number) as max_number
FROM audit_submissions aus
JOIN audit_templates t ON aus.template_id = t.id
GROUP BY t.template_name
ORDER BY t.id;

SELECT 'Audit numbers have been reset successfully!' as Status;
