# Sistem Penomoran Audit Per Template

## Perubahan yang Dilakukan

### Sebelum
- Nomor audit menggunakan nomor urut global (1, 2, 3, ...)
- Semua template berbagi urutan nomor yang sama
- Nomor tidak bisa digunakan kembali setelah audit dihapus

### Sesudah
- Setiap template memiliki penomoran audit sendiri yang dimulai dari 1
- PO Tagging OA: 1, 2, 3, ...
- Mix Oil: 1, 2, 3, ...
- Barbes: 1, 2, 3, ...
- Dan seterusnya untuk setiap template
- Nomor audit yang dihapus akan digunakan kembali (fill the gap)

## Contoh Penomoran

### Skenario 1: Penomoran Normal
```
PO Tagging OA:
- Audit #1 (dibuat pertama)
- Audit #2 (dibuat kedua)
- Audit #3 (dibuat ketiga)

Mix Oil:
- Audit #1 (dibuat pertama)
- Audit #2 (dibuat kedua)
```

### Skenario 2: Setelah Penghapusan
```
PO Tagging OA:
- Audit #1
- Audit #2 (DIHAPUS)
- Audit #3

Audit baru PO Tagging OA akan menggunakan nomor #2 (mengisi gap)
```

### Skenario 3: Multiple Templates
```
Template A: 1, 2, 3, 4, 5
Template B: 1, 2, 3
Template C: 1, 2

Setiap template independen, tidak saling mempengaruhi
```

## File yang Dimodifikasi

### 1. Database Schema
**File**: `database/schema.sql`
- Menambahkan kolom `audit_number INT` ke tabel `audit_submissions`
- Menambahkan index `idx_template_audit` untuk performa query

**File**: `database/add_audit_numbering.sql`
- Script untuk menambahkan kolom ke database yang sudah ada
- Otomatis mengupdate penomoran audit yang sudah ada

### 2. Helper Functions
**File**: `includes/functions.php`
- Menambahkan function `getNextAuditNumber($templateId)`
- Logic untuk mencari gap dalam penomoran
- Jika ada gap, gunakan nomor terkecil yang kosong
- Jika tidak ada gap, gunakan max + 1

**Algoritma**:
```php
1. Cari gap dalam penomoran (misal: 1, 2, 4, 5 → gap di nomor 3)
2. Jika ada gap, return nomor gap terkecil
3. Jika tidak ada gap, return MAX(audit_number) + 1
```

### 3. Create Audit
**File**: `audit/create.php`
- Memanggil `getNextAuditNumber($templateId)` sebelum INSERT
- Menyimpan `audit_number` ke database
- INSERT query diupdate dengan kolom `audit_number`

### 4. List Audit
**File**: `audit/list.php`
- Menampilkan `audit_number` di kolom "No" (bukan nomor urut global)
- Sorting berdasarkan `template_name` ASC, `audit_number` ASC
- Mengelompokkan audit per template secara visual

## Cara Kerja Reuse Nomor

### Logic Gap Detection
Query SQL untuk mencari gap:
```sql
SELECT t1.audit_number + 1 AS gap_start
FROM audit_submissions t1
WHERE t1.template_id = ?
AND NOT EXISTS (
    SELECT 1 FROM audit_submissions t2 
    WHERE t2.template_id = ? 
    AND t2.audit_number = t1.audit_number + 1
)
AND t1.audit_number + 1 < (
    SELECT MAX(audit_number) FROM audit_submissions WHERE template_id = ?
)
ORDER BY gap_start ASC
LIMIT 1
```

### Contoh Eksekusi
**Data Awal**:
```
PO Tagging OA: 1, 2, 3, 4, 5
```

**Setelah Hapus #2 dan #4**:
```
PO Tagging OA: 1, 3, 5
Gap: 2, 4
```

**Audit Baru #1**:
- Gap detection menemukan gap #2
- Audit baru mendapat nomor #2
- Data: 1, 2, 3, 5

**Audit Baru #2**:
- Gap detection menemukan gap #4
- Audit baru mendapat nomor #4
- Data: 1, 2, 3, 4, 5

**Audit Baru #3**:
- Tidak ada gap
- MAX(audit_number) = 5
- Audit baru mendapat nomor #6
- Data: 1, 2, 3, 4, 5, 6

## Migrasi Data Lama

Script `add_audit_numbering.sql` akan otomatis:
1. Menambahkan kolom `audit_number`
2. Mengupdate semua data yang sudah ada
3. Mengelompokkan per template
4. Memberikan nomor urut mulai dari 1 per template

## Testing

### Test Case 1: Create New Audit
1. Buat audit baru dengan template PO Tagging OA
2. Verify: audit_number = 1 (jika belum ada audit untuk template ini)

### Test Case 2: Multiple Templates
1. Buat audit PO Tagging OA → nomor #1
2. Buat audit Mix Oil → nomor #1
3. Buat audit PO Tagging OA → nomor #2
4. Verify: Setiap template memiliki penomoran sendiri

### Test Case 3: Delete and Reuse
1. Buat audit #1, #2, #3 untuk PO Tagging OA
2. Hapus audit #2
3. Buat audit baru
4. Verify: Audit baru mendapat nomor #2 (bukan #4)

### Test Case 4: List Display
1. Buka halaman list audit
2. Verify: Kolom "No" menampilkan audit_number
3. Verify: Audit dikelompokkan per template
4. Verify: Sorting benar (template name ASC, audit_number ASC)

## Keuntungan Sistem Baru

1. **Organized**: Setiap template memiliki namespace penomoran sendiri
2. **Scalable**: Tidak ada konflik nomor antar template
3. **Efficient**: Reuse nomor yang dihapus, menghindari nomor yang terlalu besar
4. **Clear**: User lebih mudah mengidentifikasi audit per kategori
5. **Professional**: Sistem penomoran yang lebih terstruktur

## Catatan Penting

- Audit yang sudah ada akan otomatis mendapat nomor saat migrasi
- Penghapusan audit akan menciptakan gap yang bisa diisi audit baru
- Index database memastikan query tetap cepat meskipun data banyak
- Sorting di list.php berdasarkan template dan nomor audit
