<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Template ID required']);
    exit;
}

$templateId = (int)$_GET['id'];

$conn = getConnection();

// Get template info
$stmt = $conn->prepare("SELECT * FROM audit_templates WHERE id = ?");
$stmt->bind_param("i", $templateId);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();

if (!$template) {
    echo json_encode(['error' => 'Template not found']);
    exit;
}

// Get sections and items
$stmt = $conn->prepare("
    SELECT 
        ts.id as section_id, 
        ts.section_order, 
        ts.section_title,
        ti.id as item_id,
        ti.item_order,
        ti.item_text,
        ti.field_type,
        ti.is_required
    FROM template_sections ts
    LEFT JOIN template_items ti ON ts.id = ti.section_id
    WHERE ts.template_id = ?
    ORDER BY ts.section_order, ti.item_order
");
$stmt->bind_param("i", $templateId);
$stmt->execute();
$result = $stmt->get_result();

$sections = [];
while ($row = $result->fetch_assoc()) {
    $sectionId = $row['section_id'];
    if (!isset($sections[$sectionId])) {
        $sections[$sectionId] = [
            'section_id' => $sectionId,
            'section_order' => $row['section_order'],
            'section_title' => $row['section_title'],
            'items' => []
        ];
    }
    
    if ($row['item_id']) {
        $sections[$sectionId]['items'][] = [
            'id' => $row['item_id'],
            'item_order' => $row['item_order'],
            'item_text' => $row['item_text'],
            'field_type' => $row['field_type'],
            'is_required' => $row['is_required']
        ];
    }
}

$conn->close();

$response = [
    'template_id' => $template['id'],
    'template_name' => $template['template_name'],
    'template_code' => $template['template_code'],
    'sections' => array_values($sections)
];

echo json_encode($response);
?>
