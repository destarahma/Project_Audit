# Perbaikan Kolom Tanggal di Template PO Tagging OA

## Masalah
Kolom "Tanggal" di bagian PO (Section 3) pada template PO Tagging OA dan PO Non OA tidak muncul di hasil audit (view.php).

## Penyebab
1. Database tidak memiliki field tanggal terpisah untuk setiap item di Section PO
2. Form input (create.php) tidak menampilkan field tanggal
3. View result (view.php) tidak merender kolom tanggal dengan benar

## Solusi yang Diterapkan

### 1. Database - Menambahkan Field Tanggal
**File**: `database/add_po_date_fields.sql`

Menambahkan field tanggal untuk setiap item "Cek" di Section PO:
- Cek DD - Tanggal
- Cek kondisi Vendor - Tanggal
- Cek material/item - Tanggal
- Cek payment term - Tanggal
- Cek harga - Tanggal
- Cek qty - Tanggal

Script SQL otomatis menambahkan untuk:
- Template PO Tagging OA (template_id 9, section 3)
- Template PO Non OA (template_id 10, section 4)

### 2. Form Input - Update create.php
**File**: `audit/create.php`

**Perubahan di fungsi `renderPOTaggingOATemplate`** (Section 3):
- Menambahkan loop untuk 6 items (bukan 5)
- Mencari field tanggal dengan pattern `[Label] - Tanggal`
- Menambahkan input date field: `<input type="date" name="responses[${dateItem.id}]">`

**Perubahan di fungsi `renderPONonOATemplate`** (Section 4):
- Mengubah header tabel dari "Sesuai/Tidak" menjadi "Ada/Tidak ada/Tanggal"
- Menambahkan loop untuk 6 items dengan field tanggal
- Struktur sama seperti PO Tagging OA

### 3. View Result - Update view.php
**File**: `audit/view.php`

**Perubahan di fungsi `renderPOTaggingItems`** (Section 3):
- Menambahkan pencarian `$dateItem` untuk setiap label
- Menambahkan kolom tanggal di tabel hasil
- Format: `formatDate($dateItem['response_value'])`

**Perubahan di fungsi `renderPONonOAItems`** (Section 4):
- Struktur yang sama dengan PO Tagging OA
- Menambahkan 6 items dengan kolom tanggal
- Menambahkan textarea items (Input note pembelian PO, Kirim PO)

**Perubahan di Header Tabel**:
- Menghapus kondisi khusus untuk PO Non OA Section 4
- Semua section menggunakan header: "Ada | Tidak ada | Tanggal"

## Cara Menjalankan Perbaikan

1. **Jalankan SQL Script** (sudah dilakukan):
```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "source c:/xampp/htdocs/Project_Audit/database/add_po_date_fields.sql"
```

2. **File yang Sudah Diupdate**:
   - ✅ `database/add_po_date_fields.sql` (baru)
   - ✅ `audit/create.php` (diupdate)
   - ✅ `audit/view.php` (diupdate)

3. **Testing**:
   - Buat audit baru dengan template PO Tagging OA
   - Pastikan kolom Tanggal muncul di form
   - Isi data dan submit
   - Lihat hasil audit - kolom Tanggal harus terisi

## Hasil Akhir

Sekarang untuk setiap item di bagian PO, terdapat 3 kolom:
1. **Ada** - Radio button
2. **Tidak ada** - Radio button
3. **Tanggal** - Date input field

Struktur ini konsisten dengan template lainnya dan sesuai dengan gambar referensi yang diberikan.

## Catatan Tambahan

- Field tanggal bersifat opsional (is_required = 0)
- User dapat mengisi tanggal tanpa harus memilih Ada/Tidak ada
- Format tanggal otomatis di-format sebagai DD-MM-YYYY saat ditampilkan
- Database menyimpan dalam format YYYY-MM-DD (standard MySQL)
