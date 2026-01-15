-- Fix item_order untuk semua template yang baru dibuat
-- Reset item_order agar berurutan 1, 2, 3, dst

-- Fix template BARBES_001
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
    WHERE t.template_code = 'BARBES_001'
    ORDER BY ts.section_order, ti2.item_order
) as ordered ON ti.id = ordered.id
SET ti.item_order = ordered.new_order;

-- Fix template ASET_001
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
    WHERE t.template_code = 'ASET_001'
    ORDER BY ts.section_order, ti2.item_order
) as ordered ON ti.id = ordered.id
SET ti.item_order = ordered.new_order;

-- Verify hasil
SELECT 'Template BARBES_001 - Items per Section' as info;
SELECT 
    s.section_order,
    s.section_title,
    COUNT(i.id) as total_items,
    GROUP_CONCAT(i.item_order ORDER BY i.item_order) as item_orders
FROM audit_templates t
JOIN template_sections s ON t.id = s.template_id
JOIN template_items i ON s.id = i.section_id
WHERE t.template_code = 'BARBES_001'
GROUP BY s.id
ORDER BY s.section_order;

SELECT 'Template ASET_001 - Items per Section' as info;
SELECT 
    s.section_order,
    s.section_title,
    COUNT(i.id) as total_items,
    GROUP_CONCAT(i.item_order ORDER BY i.item_order) as item_orders
FROM audit_templates t
JOIN template_sections s ON t.id = s.template_id
JOIN template_items i ON s.id = i.section_id
WHERE t.template_code = 'ASET_001'
GROUP BY s.id
ORDER BY s.section_order;
