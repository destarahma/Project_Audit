# Self Audit System

Sistem digitalisasi form self audit untuk menggantikan proses manual checklist Excel menjadi aplikasi web yang terstruktur dan efisien.

## 🚀 Fitur Utama

### Core Features
- ✅ **Authentication** - Login & session management
- ✅ **Dashboard** - Statistik dan overview audit
- ✅ **Dynamic Forms** - Template audit yang dapat dikustomisasi
- ✅ **Multi-field Types** - Checkbox, radio, text, date, textarea
- ✅ **Auto-calculation** - Total Harga = Qty × Harga Satuan
- ✅ **Approval Routing** - Otomatis berdasarkan nilai transaksi
- ✅ **View & Export** - Lihat hasil audit dengan format rapi
- ✅ **User Management** - Kelola user dan hak akses (Admin)
- ✅ **Template Management** - Kelola template dan approval rules (Admin)

### Advanced Features
- 🎯 **Conditional Validation** - Tanggal wajib diisi jika pilih "Ada"
- 📊 **Auto Scoring** - Perhitungan skor dan status otomatis
- 🔐 **Multi-level Approval** - 3 level approval (Procurement + Finance)
- 💰 **Business Logic** - Validasi DP minimal 50%, pelunasan, qty vs SPK
- 🎨 **Modern UI** - Professional interface with Font Awesome icons

## 💻 Teknologi

- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript
- **Server:** XAMPP (recommended)
- **Icons:** Font Awesome 6.4.0

## 📦 Instalasi

### 1. Clone Repository
```bash
git clone https://github.com/YOUR_USERNAME/Project_Audit.git
cd Project_Audit
```

### 2. Copy Configuration Files
```bash
cp config/config.example.php config/config.php
cp config/database.example.php config/database.php
```

### 3. Update Configuration
Edit `config/config.php`:
```php
define('BASE_URL', 'http://localhost/Project_Audit/');
```

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your password
define('DB_NAME', 'audit_system');
```

### 4. Create Database
```bash
# Via command line
mysql -u root -p -e "CREATE DATABASE audit_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# Or via phpMyAdmin
# 1. Open http://localhost/phpmyadmin
# 2. Click "New" to create database
# 3. Name: audit_system
# 4. Collation: utf8mb4_unicode_ci
```

### 5. Import Database Schema
```bash
# Via command line
mysql -u root -p audit_system < database/schema.sql
mysql -u root -p audit_system < database/add_advanced_features.sql

# Or via phpMyAdmin
# 1. Select audit_system database
# 2. Click "Import" tab
# 3. Choose database/schema.sql
# 4. Click "Go"
# 5. Repeat for database/add_advanced_features.sql
```

### 6. Access Application
```
http://localhost/Project_Audit/
```

### 7. Default Login
```
Username: admin
Password: admin123
```

**⚠️ IMPORTANT:** Change default password after first login!

## 📁 Project Structure
```
Project_Audit/
├── admin/                      # Admin pages
│   ├── templates.php           # Template management list
│   ├── template_edit.php       # Edit template & approval rules
│   ├── template_view.php       # View template preview
│   └── users.php               # User management
├── api/                        # API endpoints
│   └── get_template.php        # Get template data (AJAX)
├── assets/                     # Static assets
│   ├── css/
│   │   ├── style.css           # Main stylesheet
│   │   └── excel-style.css     # Excel-like form styling
│   └── js/
│       └── script.js           # Main JavaScript
├── audit/                      # Audit pages
│   ├── select_type.php         # Select audit template
│   ├── create.php              # Create new audit
│   ├── list.php                # List all audits
│   ├── view.php                # View audit detail
│   ├── delete.php              # Delete audit
│   └── download_pdf.php        # Download as PDF
├── config/                     # Configuration files
│   ├── config.example.php      # Config template (commit this)
│   ├── config.php              # Actual config (don't commit)
│   ├── database.example.php    # DB config template
│   └── database.php            # Actual DB config (don't commit)
├── database/                   # Database files
│   ├── schema.sql              # Main database schema
│   └── add_advanced_features.sql  # Advanced features migration
├── includes/                   # Reusable components
│   ├── header.php              # Header with sidebar
│   ├── footer.php              # Footer
│   ├── functions.php           # Helper functions
│   └── business_logic.php      # Business logic & validations
├── .gitignore                  # Git ignore file
├── generate_password.php       # Password generator utility
├── index.php                   # Dashboard
├── login.php                   # Login page
├── logout.php                  # Logout handler
└── README.md                   # This file
```

## 📖 Usage Guide

### For Auditors:
1. **Login** with your credentials
2. **Create New Audit:**
   - Click "Buat Audit" from dashboard
   - Select template (e.g., Self Audit Jual Beli Mix Oil)
   - Fill in header information (date, seller, location, qty, price)
   - Check all audit items based on actual conditions
   - System will auto-calculate total price and determine approval routing
3. **Submit Audit:**
   - Click "Simpan Audit" button
   - System will validate and calculate score
   - View result with approval requirements
4. **View History:**
   - Access "Daftar Audit" to see all submissions
   - Click any audit to view details

### For Admins:
1. **Template Management:**
   - Access "Template Audit" menu
   - Click "Edit" to modify template structure
   - Update approval rules per template
   - Set value thresholds for multi-level approvals
2. **User Management:**
   - Access "Kelola User" menu
   - Add new users with roles (admin/auditor)
   - Reset user passwords if needed
3. **Monitor Audits:**
   - View all audit submissions
   - Check approval status
   - Export or print audit results

## 🗄️ Database Schema

### Core Tables
- **users** - User accounts and authentication
- **audit_templates** - Audit form templates
- **template_sections** - Sections within templates
- **template_items** - Items/questions in each section
- **audit_submissions** - Submitted audit forms
- **audit_responses** - Answers to audit items

### Advanced Tables
- **approval_rules** - Approval routing rules per template
- **workflow_stages** - Multi-stage workflow definitions
- **audit_workflow_progress** - Progress tracking per submission
- **approval_items** - Dynamic approval items per level
- **field_validation_rules** - Custom validation rules

## 🔧 Customization

### Add New Template
```sql
-- 1. Insert template
INSERT INTO audit_templates (template_name, description, is_active) 
VALUES ('Template Name', 'Description', 1);

-- 2. Add sections
INSERT INTO template_sections (template_id, section_name, section_order) 
VALUES (1, 'Section Name', 1);

-- 3. Add items
INSERT INTO template_items (section_id, item_text, field_type, item_order, is_required) 
VALUES (1, 'Item text', 'radio', 1, 1);
```

### Field Types Available
- `radio` - Ada / Tidak Ada
- `checkbox` - Multiple selection
- `date` - Date picker
- `number` - Numeric input
- `text` - Text input
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

### Template Not Loading
1. Check if template exists in database
2. Verify `is_active = 1` in audit_templates
3. Check browser console for JavaScript errors

### Approval Routing Not Showing
1. Import `add_advanced_features.sql`
2. Verify approval_rules table has data
3. Check Total Harga is calculated correctly

## 📝 License

This project is for educational purposes.

## 👨‍💻 Developer

Developed during internship program.

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## 📞 Support

For issues or questions, please open an issue on GitHub.

## Security Features
- Password hashing (bcrypt)
- SQL injection prevention (prepared statements)
- XSS prevention (htmlspecialchars)
- Session management
- Role-based access control

## Browser Support
- Chrome/Edge (recommended)
- Firefox
- Safari

## Troubleshooting

**Error: Connection failed**
- Pastikan MySQL/MariaDB sudah running
- Cek konfigurasi database di `config/database.php`

**Error: Database not found**
- Import file `database/schema.sql` ke phpMyAdmin

**Tidak bisa login**
- Pastikan database sudah di-import
- User default: admin / admin123

**Template tidak muncul**
- Cek tabel `audit_templates` pastikan is_active = 1

## Future Improvements
- [ ] Upload file pendukung
- [ ] Approval workflow
- [ ] Email notification
- [ ] Export to Excel/PDF
- [ ] Advanced reporting
- [ ] Mobile responsive enhancement

## Kontak & Support
Untuk pertanyaan atau bantuan, hubungi developer/supervisor proyek magang.

## Lisensi
Project ini dibuat untuk keperluan magang dan pembelajaran.

---
**Dibuat dengan ❤️ untuk Project Magang**
