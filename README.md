# Sistem Self Audit Procurement

Aplikasi digitalisasi form self audit procurement untuk menggantikan proses manual checklist Excel menjadi sistem web yang terstruktur, efisien, dan terintegrasi.

## 🚀 Fitur Utama

### Fitur Inti
- ✅ **Autentikasi & Otorisasi** - Login dengan role-based access control (Admin & Auditor)
- ✅ **Dashboard** - Overview statistik audit dan quick access
- ✅ **Multi-Template Audit** - Support berbagai jenis audit (Mix Oil, Barbes, Jual Aset, PO Tagging, PO Non OA)
- ✅ **Dynamic Forms** - Form audit yang dapat dikustomisasi per template
- ✅ **Auto-calculation** - Perhitungan otomatis (Qty × Harga Satuan, Total, dll)
- ✅ **Penomoran Otomatis** - Nomor audit per template dengan sistem reuse untuk nomor yang dihapus
- ✅ **Draft & Submit** - Simpan sebagai draft atau submit langsung
- ✅ **View & Print** - Tampilan Excel-like dengan fitur cetak
- ✅ **User Management** - Kelola user dan hak akses (Admin)
- ✅ **Template Management** - Kelola template, field, dan approval rules (Admin)

### Fitur Lanjutan
- 🎯 **Conditional Validation** - Validasi dinamis berdasarkan input (contoh: tanggal wajib jika pilih "Ada")
- 📊 **Auto Scoring** - Perhitungan skor audit dan status kelengkapan otomatis
- 🔐 **Multi-level Approval Routing** - Approval otomatis berdasarkan nilai transaksi:
  - Level 1: ≤ Rp 50M
  - Level 2: Rp 50M - 300M
  - Level 3: > Rp 300M
- 💰 **Business Logic Validation** - Validasi otomatis:
  - DP minimal 50% dari total
  - Validasi pelunasan (DP + Sisa ≤ Total)
  - Validasi quantity (Actual ≤ SPK)
- 🎨 **Modern UI** - Interface profesional dengan Font Awesome icons dan Excel-style forms
- 📸 **Photo Upload** - Support upload foto dokumentasi

## 💻 Stack Teknologi

- **Backend:** PHP 7.4+ (Native)
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Server:** Apache (XAMPP/LAMP/WAMP)
- **Icons:** Font Awesome 6.4.0
- **PDF:** TCPDF (untuk export PDF)
- **Architecture:** MVC-inspired structure dengan separation of concerns

## 📦 Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/destarahma/Project_Audit.git
cd Project_Audit
```

### 2. Buat Configuration Files
Buat file `config/config.php`:
```php
<?php
// Base URL
define('BASE_URL', 'http://localhost/Project_Audit/');

// Session configuration
ini_set('session.cookie_httponly', 1);
session_start();
?>
```

Buat file `config/database.php`:
```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your MySQL password
define('DB_NAME', 'audit_system');

// Database connection function
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
```

### 3. Create Database
```bash
# Via command line
mysql -u root -p -e "CREATE DATABASE audit_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# Or via phpMyAdmin
# 1. Open http://localhost/phpmyadmin
# 2. Click "New" to create database
# 3. Name: audit_system
# 4. Collation: utf8mb4_unicode_ci
```

### 4. Import Database Schema
```bash
# Via command line (import berurutan)
mysql -u root -p audit_system < database/schema.sql
mysql -u root -p audit_system < database/add_advanced_features.sql
mysql -u root -p audit_system < database/add_audit_numbering.sql
mysql -u root -p audit_system < database/add_po_date_fields.sql
mysql -u root -p audit_system < database/add_section4_dates.sql
mysql -u root -p audit_system < database/setup_po_templates.sql

# Or via phpMyAdmin
# 1. Select audit_system database
# 2. Click "Import" tab
# 3. Import semua file SQL dari folder database/ secara berurutan
```

### 5. Access Application
```
http://localhost/Project_Audit/
```

### 6. Default Login
```
Username: admin
Password: admin123
```

**⚠️ IMPORTANT:** Change default password after first login!

## 📁 Project Structure
```
Project_Audit/
├── admin/                           # Admin pages
│   ├── templates.php                # Template management list
│   ├── template_copy.php            # Copy existing template
│   ├── template_create.php          # Create new template
│   ├── template_edit.php            # Edit template & approval rules
│   ├── template_view.php            # View template preview
│   └── users.php                    # User management
├── api/                             # API endpoints
│   └── get_template.php             # Get template data (AJAX)
├── assets/                          # Static assets
│   ├── css/
│   │   ├── style.css                # Main stylesheet
│   │   └── excel-style.css          # Excel-like form styling
│   └── js/
│       └── script.js                # Main JavaScript
├── audit/                           # Audit pages
│   ├── select_type.php              # Select audit template
│   ├── create.php                   # Create new audit
│   ├── edit.php                     # Edit draft audit
│   ├── list.php                     # List all audits
│   ├── view.php                     # View audit detail
│   ├── view_render_functions.php    # Render functions for templates
│   ├── delete.php                   # Delete audit
│   └── download_pdf.php             # Download as PDF
├── config/                          # Configuration files
│   ├── config.php                   # Actual config (don't commit)
│   └── database.php                 # Actual DB config (don't commit)
├── database/                        # Database files
│   ├── schema.sql                   # Main database schema
│   ├── add_advanced_features.sql    # Advanced features migration
│   ├── add_audit_numbering.sql      # Audit numbering system
│   ├── add_po_date_fields.sql       # PO date fields migration
│   ├── add_section4_dates.sql       # Section 4 dates for PO Non OA
│   ├── reset_audit_numbers.sql      # Reset audit numbers utility
│   └── setup_po_templates.sql       # PO templates setup
├── includes/                        # Reusable components
│   ├── header.php                   # Header with sidebar
│   ├── footer.php                   # Footer
│   ├── functions.php                # Helper functions
│   └── business_logic.php           # Business logic & validations
├── uploads/                         # Upload directory (git ignored)
│   └── photos/                      # Uploaded photos
├── .gitignore                       # Git ignore rules
├── index.php                        # Dashboard (home page)
├── login.php                        # Login page
├── logout.php                       # Logout handler
└── README.md                        # This documentation
```

**Note:** 
- File `config.example.php` dan `database.example.php` tidak ada di repository ini
- Folder `backup_cleanup_*/` di-ignore dan tidak masuk repository
- Folder `docs/` tidak ada di repository ini (dokumentasi ada di README)

## Untuk Auditor:
1. **Login** - Masuk menggunakan username dan password
2. **Buat Audit Baru:**
   - Klik "Buat Audit" dari dashboard
   - Pilih jenis template audit (Mix Oil, Barbes, Jual Aset, PO Tagging, PO Non OA)
   - Isi informasi header (tanggal, vendor, lokasi, qty, harga)
   - Isi semua item audit sesuai kondisi aktual
   - System akan auto-calculate total harga dan menentukan approval routing
   - Upload foto dokumentasi (opsional)
3. **Simpan atau Submit:**
   - **Simpan Draft**: Simpan sementara untuk dilanjutkan nanti
   - **Submit Audit**: Submit final dengan validasi dan scoring otomatis
4. **Lihat History:**
   - Akses "Daftar Audit" untuk melihat semua audit yang pernah dibuat
   - Filter berdasarkan template, status, atau tanggal
   - Klik audit untuk melihat detail atau edit draft

### Untuk Admin:
1. **Kelola Template:**
   - Menu "Template Audit" untuk melihat semua template
   - **Edit Template**: Ubah struktur, section, dan item
   - **Copy Template**: Duplikasi template untuk modifikasi
   - **Atur Approval Rules**: Set threshold nilai untuk routing approval
2. **Kelola User:**
   - Menu "Kelola User" untuk manajemen user
   - Tambah user baru dengan role (admin/auditor)
   - Reset password user
   - Nonaktifkan user jika diperlukan
3. **Monitor Audit:**
   - Dashboard menampilkan statistik audit
   - Lihat semua submission dari semua user
   - Filter dan search audit
   - Export atau print hasil audit
   -Tabel Utama
- **users** - Akun user dan autentikasi
- **audit_templates** - Template form audit
- **template_sections** - Section dalam template
- **template_items** - Item/pertanyaan dalam setiap section
- **audit_submissions** - Audit yang sudah disubmit
- **audit_responses** - Jawaban untuk setiap item audit

### Tabel Fitur Lanjutan
- **approval_rules** - Aturan routing approval per template
- **workflow_stages** - Definisi tahapan workflow multi-level
- **audit_workflow_progress** - Tracking progress per submission
- **approval_items** - Item approval dinamis per level
- **field_validation_rules** - Aturan validasi custom per field

###🎨 Template Audit Tersedia

Sistem ini mendukung beberapa jenis template audit:

### 1. Self Audit Jual Beli Mix Oil
- Audit untuk transaksi jual beli mix oil
- Pengurusan pembelian, approval, SPK, PO, pembayaran
- Business logic validation: DP minimal 50%, validasi pelunasan

### 2. Self Audit Barbels (Barang Bekas)
- Audit untuk transaksi barang bekas/scrap
- Pengurusan dokumen, approval, pembayaran

### 3. Self Audit Jual Aset
- Audit untuk penjualan aset perusahaan
- Pengurusan dokumen, approval, pembayaran

### 4. Self Audit PO Tagging OA
- Audit PO dengan tagging ke OA (Order Application)
- Pengurusan pembelian lengkap dengan RAP, Drawing, PR
- Validasi PO: DD, Vendor, Material, Payment Term, Harga, Qty

### 5. Self Audit PO Non OA
- Audit PO tanpa tagging OA
- Pengajuan pembelian: Pre PR, RAP, Drawing, Approval Spec
- Pelaksanaan pembelian: Penawaran harga, Vendor selection
- Validasi PO lengkap

## 🔧 Kustomisasi

### Menambah Template Baru

**Via Admin Panel:**
1. Login sebagai Admin
2. Menu "Template Audit" → "Copy Template"
3. EdiKeamanan

### Fitur Keamanan yang Diimplementasikan:
- ✅ **Password Hashing** - Menggunakan bcrypt untuk hash password
- ✅ **SQL Injection Prevention** - Prepared statements untuk semua query
- ✅ **XSS Prevention** - Sanitasi input dengan htmlspecialchars
- ✅ **Session Management** - Secure session handling
- ✅ **Role-based Access Control** - Pembatasan akses berdasarkan role
- ✅ **File Upload Validation** - Validasi tipe dan ukuran file upload

### Best Practices:
1. ⚠️ **Ganti password default** setelah instalasi pertama kali
2. 🚫 **Jangan commit** file `config/config.php` dan `config/database.php`
3. ✅ **Gunakan file .example** untuk version control
4. 🔒 **Update BASE_URL** di config untuk environment production
5. 🔐 **Enable HTTPS** di production environment
6. 📁 **Set permission folder uploads** agar tidak executable
7. 🔑 **Gunakan password yang kuat** untuk akun admin dan databaseemplate_code, description, max_score, is_active) 
VALUES ('Nama Template', 'CODE_TEMPLATE', 'Deskripsi template', 100, 1);

-- 2. Add sections
INSERT INTO template_sections (template_id, section_title, section_order) 
VALUES (LAST_INSERT_ID(), 'Nama Section', 1);

-- 3. Add items
INSERT INTO template_items (section_id, item_text, field_type, item_order, is_required, score_value) 
VALUES (LAST_INSERT_ID(), 'Item pertanyaan', 'radio', 1, 1, 10);
```

### Tipe Field Tersedia
- `radio` - Pilihan Ada / Tidak Ada
- `checkbox` - Multiple selection
- `date` - Input tanggal
- `number` - Input angka
- `text` - Input text pendek
- `textarea` - Input text panjang (catatan)

### Approval Rules per Template
Konfigurasi via `admin/template_edit.php`:
- **Level 1:** ≤ Rp 50.000.000
- **Level 2:** Rp 50.000.001 - Rp 300.000.000
- **Level 3:** > Rp 300.000.000

Setiap level memiliki 2 kategori approver:
- 🏢 **Procurement** (Pengadaan): Admin Pengadaan, Manager, etc.
- 💰 **Finance** (Keuangan): Staff Keuangan, Manager Keuangan, etc.
- `textarea` - Long text

### Approval Rules
Configure in `admin/template_edit.php`:
- **Level 1:** <= Rp 50M
- **Level 2:** Rp 50M - 300M
- **Level 3:** > Rp 300M

Each level has 2 categories:
- 🏢 **Procurement** (Pengadaan)
- 💰 **Finance** (Keuangan)

## 🔐 Security Notes

1. **Change default password** after first login
2. **Don't commit** `config/config.php` and `config/database.php`
3. **Use example files** for version control
4. **Update** `BASE_URL` in config for production
5. **Enable HTTPS** in production environment

## 🐛 Troubleshooting

### Database Connection Error
```php
// Check config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Update if needed
define('DB_NAME', 'audit_system');
```
🌐 Browser Support
- ✅ Chrome/Edge (Recommended)
- ✅ Firefox
- ✅ Safari
- ⚠️ Internet Explorer tidak didukung

## 📱 Responsive Design
Aplikasi ini dioptimasi untuk desktop. Untuk mobile access, gunakan landscape mode atau tablet untuk pengalaman terbaik.

## 🔄 Update Log

### Latest Updates (January 2026):
- ✅ Implementasi penomoran otomatis per template dengan reuse system
- ✅ Fix handling Periode QCF di PO Tagging dan PO Non OA templates
- ✅ Perbaikan view render untuk semua template types
- ✅ Enhance conditional date validation
- ✅ Improve business logic validation display
- ✅ Code cleanup dan dokumentasi lengkap
- ✅ Update README dengan struktur folder yang akurat

## 🚀 Roadmap & Future Improvements

### Phase 1 (Completed):
- ✅ Core audit system dengan multi-template
- ✅ User authentication & role management
- ✅ Auto-calculation & scoring
- ✅ Business logic validation
- ✅ Multi-level approval routing
- ✅ Photo upload
- ✅ PDF export

### Phase 2 (Planned):
- [ ] **Email Notifications** - Notifikasi approval dan status audit
- [ ] **Approval Workflow** - Implementasi actual approval process dengan signature
- [ ] **Advanced Reporting** - Dashboard analytics dan insights
- [ ] **Export to Excel** - Download hasil audit dalam format Excel
- [ ] **Batch Operations** - Bulk actions untuk multiple audits
- [ ] **Search & Filter Enhancement** - Advanced search dengan multiple criteria
- [ ] **Mobile App** - Native mobile application (React Native/Flutter)
- [ ] **API Integration** - RESTful API untuk integrasi dengan sistem lain
- [ ] **Audit History Comparison** - Bandingkan audit periode berbeda
- [ ] **Automated Reminders** - Pengingat untuk audit yang pending

## 👨‍💻 Developer & Kontributor

**Developer:** Desta Rahma  
**Project Type:** Internship Project  
**Period:** 2025-2026  
**Institution:** [Nama Institusi/Perusahaan]

### Kontribusi
Kontribusi sangat diterima! Untuk berkontribusi:
1. Fork repository ini
2. Buat feature branch (`git checkout -b feature/FiturBaru`)
3. Commit perubahan (`git commit -m 'Menambahkan fitur baru'`)
4. Push ke branch (`git push origin feature/FiturBaru`)
5. Buat Pull Request

## 📞 Support & Kontak

Untuk pertanyaan, bug report, atau request fitur:
- 📧 Email: [email]
- 📱 GitHub Issues: [Issues Page](https://github.com/destarahma/Project_Audit/issues)
- 💬 Discussion: [GitHub Discussions](https://github.com/destarahma/Project_Audit/discussions)

## 📄 Lisensi

Project ini dibuat untuk keperluan pendidikan dan magang.  
© 2026 - Desta Rahma

## 🙏 Acknowledgments

Terima kasih kepada:
- Supervisor & mentor yang telah membimbing
- Tim IT yang telah memberikan requirement dan feedback
- Semua yang telah berkontribusi dalam project ini

---

**Dikembangkan dengan ❤️ untuk digitalisasi proses procurement**

⭐ Jika project ini membantu, berikan star di GitHub!
- [ ] Advanced reporting
- [ ] Mobile responsive enhancement

## Kontak & Support
Untuk pertanyaan atau bantuan, hubungi developer/supervisor proyek magang.

## Lisensi
Project ini dibuat untuk keperluan magang dan pembelajaran.

---
**Dibuat dengan ❤️ untuk Project Magang**
