<?php
/**
 * Render Functions untuk Audit Templates
 * File ini berisi semua fungsi rendering yang digunakan oleh view.php dan download_pdf.php
 */

// Function to render items based on template type
function renderTemplateItems($templateId, $sections, $submission) {
    foreach ($sections as $section):
        // Skip section 1 untuk PO Tagging OA dan PO Non OA (sudah di info table)
        if (($templateId == 9 || $templateId == 10) && $section['section_order'] == 1) {
            continue;
        }
?>
<div class="excel-section">
    <h3 class="excel-section-header"><?php echo htmlspecialchars($section['section_title']); ?></h3>
    
    <?php if ($section['section_order'] == 6): ?>
        <!-- Section 6: Dokumentasi (khusus tanpa tabel) -->
        <div style="padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
            <?php foreach ($section['items'] as $item): ?>
                <p style="margin: 0; font-size: 14px; color: #495057;"><?php echo htmlspecialchars($item['item_text']); ?></p>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <table class="excel-table">
            <thead>
                <tr>
                    <?php if ($templateId == 10 && $section['section_order'] == 4): ?>
                        <!-- Header khusus untuk PO Non OA Section 4 -->
                        <th width="60%">Item</th>
                        <th width="20%">Sesuai</th>
                        <th width="20%">Tidak</th>
                    <?php else: ?>
                        <!-- Header standar -->
                        <th width="50%">Item</th>
                        <th width="15%">Ada</th>
                        <th width="15%">Tidak ada</th>
                        <th width="20%">Tanggal</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $displayOrder = 1;
                
                // Render berdasarkan template
                if ($templateId == 9) {
                    // PO TAGGING OA
                    renderPOTaggingItems($section, $displayOrder);
                } else if ($templateId == 10) {
                    // PO NON OA
                    renderPONonOAItems($section, $displayOrder);
                } else if ($templateId == 1) {
                    // MIX OIL
                    renderMixOilItems($section, $displayOrder);
                } else if ($templateId == 5) {
                    // BARBES
                    renderBarbesItems($section, $displayOrder);
                } else if ($templateId == 6) {
                    // JUAL ASET
                    renderJualAsetItems($section, $displayOrder);
                }
                ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <?php if ($section['section_order'] == 2 && $templateId == 1): ?>
    <div class="excel-note">Note: dikirim ke Kaber jika Mix Oil yg akan dijual masuk area Kaber</div>
    <?php endif; ?>
</div>
<?php 
    endforeach;
}

// RENDER FUNCTION UNTUK PO TAGGING OA
function renderPOTaggingItems($section, &$displayOrder) {
    $items = $section['items'];
    
    // Section 2: Pengurusan Pembelian
    if ($section['section_order'] == 2) {
        $labels = ['RAP', 'Approval RAP', 'Drawing / Layout', 'PR fully approved'];
        $baseOrders = [1, 4, 7, 10];
        
        foreach ($labels as $idx => $label) {
            $baseOrder = $baseOrders[$idx];
            $adaItem = null;
            $tidakItem = null;
            $dateItem = null;
            
            foreach ($items as $item) {
                if ($item['item_order'] == $baseOrder) $adaItem = $item;
                if ($item['item_order'] == $baseOrder + 1) $tidakItem = $item;
                if ($item['item_order'] == $baseOrder + 2) $dateItem = $item;
            }
            
            echo '<tr>';
            echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($label) . '</td>';
            echo '<td class="excel-cell-center">';
            echo ($adaItem && isset($adaItem['response_value']) && $adaItem['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
            echo '</td>';
            echo '<td class="excel-cell-center">';
            echo ($tidakItem && isset($tidakItem['response_value']) && $tidakItem['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
            echo '</td>';
            echo '<td class="excel-cell-center">';
            echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
            echo '</td>';
            echo '</tr>';
            $displayOrder++;
        }
    }
    // Section 3: PO
    else if ($section['section_order'] == 3) {
        $labels = ['Cek DD', 'Cek kondisi Vendor', 'Cek material/item', 'Cek payment term', 'Cek harga', 'Cek qty'];
        
        foreach ($labels as $idx => $label) {
            $sesuaiItem = null;
            $tidakItem = null;
            $dateItem = null;
            
            foreach ($items as $item) {
                if (stripos($item['item_text'], $label . ' - Sesuai') !== false) {
                    $sesuaiItem = $item;
                }
                if (stripos($item['item_text'], $label . ' - Tidak') !== false) {
                    $tidakItem = $item;
                }
                if (stripos($item['item_text'], $label . ' - Tanggal') !== false) {
                    $dateItem = $item;
                }
            }
            
            echo '<tr>';
            echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($label) . '</td>';
            echo '<td class="excel-cell-center">';
            if ($sesuaiItem && isset($sesuaiItem['response_value']) && ($sesuaiItem['response_value'] == 'ada' || $sesuaiItem['response_value'] == 'sesuai')) {
                echo '<span class="excel-result-check yes">✓</span>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td class="excel-cell-center">';
            if ($tidakItem && isset($tidakItem['response_value']) && ($tidakItem['response_value'] == 'tidak_ada' || $tidakItem['response_value'] == 'tidak')) {
                echo '<span class="excel-result-check no">✗</span>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td class="excel-cell-center">';
            echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
            echo '</td>';
            echo '</tr>';
            $displayOrder++;
        }
        
        // Items 7-8: Textarea fields
        $textareaLabels = ['Input note pembelian PO', 'Kirim PO'];
        
        foreach ($textareaLabels as $textLabel) {
            $textItem = null;
            foreach ($items as $item) {
                if ($item['field_type'] == 'textarea' && stripos($item['item_text'], $textLabel) !== false) {
                    $textItem = $item;
                    break;
                }
            }
            
            if ($textItem) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($textLabel) . '</td>';
                echo '<td colspan="3" class="excel-result-text">';
                echo (isset($textItem['response_value']) && $textItem['response_value']) ? nl2br(htmlspecialchars($textItem['response_value'])) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
        }
    }
}

// RENDER FUNCTION UNTUK PO NON OA
function renderPONonOAItems($section, &$displayOrder) {
    $items = $section['items'];
    
    // Section 2: Pengajuan Pembelian
    if ($section['section_order'] == 2) {
        $labels = ['Pre PR', 'RAP', 'Drawing / Gambar', 'Approval Spec', 'PR fully approved'];
        $baseOrders = [1, 4, 7, 10, 13];
        
        foreach ($labels as $idx => $label) {
            $baseOrder = $baseOrders[$idx];
            $adaItem = null;
            $tidakItem = null;
            $dateItem = null;
            
            foreach ($items as $item) {
                if ($item['item_order'] == $baseOrder) $adaItem = $item;
                if ($item['item_order'] == $baseOrder + 1) $tidakItem = $item;
                if ($item['item_order'] == $baseOrder + 2) $dateItem = $item;
            }
            
            echo '<tr>';
            echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($label) . '</td>';
            echo '<td class="excel-cell-center">';
            echo ($adaItem && isset($adaItem['response_value']) && $adaItem['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
            echo '</td>';
            echo '<td class="excel-cell-center">';
            echo ($tidakItem && isset($tidakItem['response_value']) && $tidakItem['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
            echo '</td>';
            echo '<td class="excel-cell-center">';
            echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
            echo '</td>';
            echo '</tr>';
            $displayOrder++;
        }
    }
    // Section 3: Pelaksanaan Pembelian
    else if ($section['section_order'] == 3) {
        // Items 1-3: Penawaran harga
        for ($i = 1; $i <= 3; $i++) {
            $namaItem = null;
            $hargaItem = null;
            
            foreach ($items as $item) {
                if ($item['item_order'] == ($i * 2 - 1) && $item['field_type'] == 'text') {
                    $namaItem = $item;
                }
                if ($item['item_order'] == ($i * 2) && $item['field_type'] == 'number') {
                    $hargaItem = $item;
                }
            }
            
            echo '<tr>';
            echo '<td><span class="item-number">' . $displayOrder . '.</span> Penawaran harga ' . $i . ' (nama Vendor)</td>';
            echo '<td colspan="3" class="excel-result-text">';
            if (isset($namaItem['response_value']) && $namaItem['response_value']) {
                echo htmlspecialchars($namaItem['response_value']);
                if ($hargaItem && isset($hargaItem['response_value']) && $hargaItem['response_value']) {
                    echo '<br><strong>' . formatHarga($hargaItem['response_value']) . '</strong>';
                }
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
            $displayOrder++;
        }
        
        // Items 7-9: Alasan penunjukkan Vendor, PO Purchase, Memo
        $additionalItems = [
            ['order' => 7, 'label' => 'Alasan penunjukkan Vendor'],
            ['order' => 8, 'label' => 'PO Purchase'],
            ['order' => 9, 'label' => 'Memo']
        ];
        
        foreach ($additionalItems as $addItem) {
            $foundItem = null;
            foreach ($items as $item) {
                if ($item['item_order'] == $addItem['order']) {
                    $foundItem = $item;
                    break;
                }
            }
            
            if ($foundItem) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($addItem['label']) . '</td>';
                echo '<td colspan="3" class="excel-result-text">';
                if (isset($foundItem['response_value']) && $foundItem['response_value']) {
                    if ($foundItem['field_type'] == 'text' || $foundItem['field_type'] == 'textarea') {
                        echo nl2br(htmlspecialchars($foundItem['response_value']));
                    } else if ($foundItem['field_type'] == 'radio') {
                        echo ($foundItem['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓ Ada</span>' : '<span class="excel-result-check no">✗ Tidak ada</span>';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
        }
    }
    // Section 4: PO
    else if ($section['section_order'] == 4) {
        $labels = [
            'Cek Nama Vendor',
            'Cek Kembali ke RAP/Spec yang disetujui User',
            'Cek Kembali ke Penawaran',
            'Cek Tax Code',
            'Cek TOP',
            'Cek DD',
            'Input Note Tambahan PO',
            'Kirim PO ke Vendor'
        ];
        
        foreach ($labels as $idx => $label) {
            $sesuaiItem = null;
            $tidakItem = null;
            
            foreach ($items as $item) {
                if (stripos($item['item_text'], $label . ' - Sesuai') !== false) {
                    $sesuaiItem = $item;
                }
                if (stripos($item['item_text'], $label . ' - Tidak') !== false) {
                    $tidakItem = $item;
                }
            }
            
            if ($sesuaiItem && $tidakItem) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($label) . '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($sesuaiItem['response_value']) && $sesuaiItem['response_value'] == 'sesuai') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($tidakItem['response_value']) && $tidakItem['response_value'] == 'tidak') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
        }
    }
}

// Dummy render functions untuk template lain (untuk sementara)
function renderMixOilItems($section, &$displayOrder) {
    // Simplified rendering
    foreach ($section['items'] as $item) {
        if ($item['field_type'] == 'radio' || $item['field_type'] == 'checkbox') {
            echo '<tr>';
            echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
            echo '<td class="excel-cell-center">';
            echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
            echo '</td>';
            echo '<td class="excel-cell-center">';
            echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
            echo '</td>';
            echo '<td class="excel-cell-center">-</td>';
            echo '</tr>';
            $displayOrder++;
        } else if ($item['field_type'] == 'text' || $item['field_type'] == 'textarea') {
            echo '<tr>';
            echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
            echo '<td colspan="3" class="excel-result-text">';
            echo (isset($item['response_value']) && $item['response_value']) ? nl2br(htmlspecialchars($item['response_value'])) : '-';
            echo '</td>';
            echo '</tr>';
            $displayOrder++;
        }
    }
}

function renderBarbesItems($section, &$displayOrder) {
    renderMixOilItems($section, $displayOrder); // Use same simplified rendering
}

function renderJualAsetItems($section, &$displayOrder) {
    renderMixOilItems($section, $displayOrder); // Use same simplified rendering
}
?>