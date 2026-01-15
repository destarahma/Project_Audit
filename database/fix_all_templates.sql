-- Fix item_order untuk SEMUA template
-- Reset item_order agar berurutan 1, 2, 3, dst

-- Fix template MIX_OIL_001
SET @row_number = 0;
SET @current_section = 0;

UPDATE template_items ti
JOIN (
    SELECT 
        ti2.id,
        @row_number := IF(@current_section = ti2.section_id, @row_number + 1, 1) as new_order,
        @current_section := ti2.section_id as section_id
    FROM template_items ti2
    JOIN template_sections ts ON ti2.section_id = ts.id
    JOIN audit_templates t ON ts.template_id = t.id
    WHERE t.template_code = 'MIX_OIL_001'
    ORDER BY ts.section_order, ti2.item_order
) as ordered ON ti.id = ordered.id
SET ti.item_order = ordered.new_order;

-- Verify semua template
SELECT 'Summary Semua Template' as info;
SELECT 
    t.template_name,
    t.template_code,
    COUNT(DISTINCT s.id) as total_sections,
    COUNT(i.id) as total_items
FROM audit_templates t
LEFT JOIN template_sections s ON t.id = s.template_id
LEFT JOIN template_items i ON s.id = i.section_id
GROUP BY t.id
ORDER BY t.id;
