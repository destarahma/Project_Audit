# Update Template PO Tagging OA

## Perubahan yang Dilakukan

### 1. Database Structure (Template ID: 8)
Template PO Tagging OA telah diupdate dengan struktur baru yang sesuai dengan gambar:

#### Section 1: Informasi PO Tagging OA
- Pembelian PO tagging OA (text)
- Tanggal (date)
- Deskripsi (textarea)
- Qty (number)
- Harga satuan (number)
- Total harga (auto-calculated, readonly)

#### Section 2: 1. Pengurusan Pembelian
Format: Ada | Tidak ada | Tanggal
- RAP
- Approval RAP
- Drawing / Layout
- PR fully approved

#### Section 3: 2. PO
Format: Sesuai | Tidak
- Cek Nama Vendor
- Cek kontrak ke QA dgn yg ditunjuk
- Hias
- Cek kembali ke OA Tagging
- Cek Tax code
- Cek TOP
- Cek OO
- Input note pembelian PO
- Kirim PO ke Vendor

#### Section 4: 3. Dokumentasi
- File pendukung disimpan di (text input)
- File: Purchasing/Data/Pembelian/PO OA (checkbox)

### 2. Frontend Changes

#### File: audit/create.php
- Menambahkan custom rendering function `renderPOTaggingOATemplate()` untuk template ID 8
- Menyembunyikan header table default untuk template PO Tagging OA
- Menambahkan auto-calculation untuk Qty × Harga Satuan = Total Harga
- Membuat layout yang sesuai dengan tampilan Excel di gambar

#### File: assets/css/excel-style.css
- Menambahkan styling khusus untuk input number
- Memperbaiki border dan styling untuk form yang lebih rapi

### 3. Testing
Untuk test template:
1. Buka: http://localhost/Project_Audit/verify_po_tagging.php
2. Atau langsung: http://localhost/Project_Audit/audit/create.php?template_id=8

### 4. Fitur Khusus PO Tagging OA
- ✅ Auto-calculation: Total Harga = Qty × Harga Satuan
- ✅ Layout Excel-style dengan kolom Ada/Tidak ada/Tanggal
- ✅ Kolom Sesuai/Tidak untuk bagian PO
- ✅ Field dokumentasi dengan path folder
- ✅ Responsive design

### Database Query yang Dijalankan
Lihat file: `update_po_tagging_template.php` (sudah dihapus setelah eksekusi)
- Menghapus section dan item lama
- Membuat 4 section baru
- Menambahkan total 38 items

## Cara Penggunaan

1. Login ke sistem
2. Pilih menu "Buat Audit Baru"
3. Pilih "PO Tagging OA"
4. Isi form sesuai dengan checklist
5. Sistem akan otomatis menghitung Total Harga
6. Submit untuk menyimpan audit

## Notes
- Template ini mengikuti layout yang sama dengan gambar yang diberikan
- Semua field disesuaikan dengan kebutuhan proses Purchase Order dengan Tagging OA
- Format Excel-style dipertahankan untuk konsistensi dengan template lain
