# ANALISIS KEBUTUHAN SISTEM
## Self Audit System - Digitalisasi Form Self Audit

---

## 1. PENDAHULUAN

### 1.1 Latar Belakang
Sistem Self Audit merupakan sistem informasi berbasis web yang dikembangkan untuk mendigitalisasi proses audit internal yang sebelumnya dilakukan secara manual menggunakan checklist Excel. Sistem ini dirancang untuk meningkatkan efisiensi, akurasi, dan kemudahan dalam proses audit jual beli Mix Oil dan proses bisnis lainnya.

### 1.2 Tujuan Pengembangan
- Menggantikan proses manual checklist Excel dengan sistem digital yang terstruktur
- Mengotomatisasi validasi dan perhitungan dalam proses audit
- Mempermudah tracking dan monitoring status audit
- Menyediakan sistem approval routing otomatis berdasarkan nilai transaksi
- Menghasilkan laporan audit yang terstruktur dan dapat diekspor

### 1.3 Ruang Lingkup Sistem
Sistem ini mencakup:
- Manajemen user dan hak akses
- Pembuatan dan pengelolaan template audit
- Proses pengisian form audit dengan validasi dinamis
- Sistem approval multi-level
- Pengelolaan data audit submission
- Export dan cetak laporan audit
- Dashboard monitoring dan statistik

---

## 2. ANALISIS SISTEM YANG SEDANG BERJALAN

### 2.1 Proses Manual (Sebelum Digitalisasi)
- Menggunakan file Excel untuk checklist audit
- Pengisian manual tanpa validasi otomatis
- Perhitungan harus dilakukan manual
- Tidak ada sistem tracking status approval
- Sulit untuk mencari dan menganalisis data historis
- Risiko kehilangan data atau duplikasi
- Membutuhkan waktu lama untuk proses approval

### 2.2 Permasalahan Sistem Manual
1. **Tidak Efisien**: Proses pengisian dan validasi memakan waktu
2. **Rawan Error**: Kesalahan perhitungan manual
3. **Sulit Tracking**: Tidak ada monitoring status real-time
4. **Tidak Terstruktur**: Data tersebar di berbagai file Excel
5. **Tidak Ada Validasi**: Tidak ada pengecekan otomatis untuk aturan bisnis
6. **Sulit Reporting**: Sulit membuat laporan agregat
7. **Tidak Ada Audit Trail**: Tidak ada pencatatan perubahan data

---

## 3. ANALISIS KEBUTUHAN SISTEM

### 3.1 Kebutuhan Fungsional

#### 3.1.1 Modul Autentikasi
| ID | Kebutuhan | Prioritas | Status |
|----|-----------|-----------|--------|
| F-01 | Sistem login dengan username dan password | Tinggi | ✅ Implemented |
| F-02 | Session management untuk keamanan | Tinggi | ✅ Implemented |
| F-03 | Logout dan hapus session | Tinggi | ✅ Implemented |
| F-04 | Hash password dengan bcrypt | Tinggi | ✅ Implemented |
| F-05 | Role-based access control (Admin, Auditor, Viewer) | Tinggi | ✅ Implemented |

#### 3.1.2 Modul Dashboard
| ID | Kebutuhan | Prioritas | Status |
|----|-----------|-----------|--------|
| F-06 | Tampilkan total audit submissions | Tinggi | ✅ Implemented |
| F-07 | Tampilkan jumlah audit yang approved | Tinggi | ✅ Implemented |
| F-08 | Tampilkan jumlah audit pending review | Tinggi | ✅ Implemented |
| F-09 | Tampilkan list audit terbaru (5 terakhir) | Sedang | ✅ Implemented |
| F-10 | Statistik visual dengan card-based layout | Sedang | ✅ Implemented |

#### 3.1.3 Modul Template Audit
| ID | Kebutuhan | Prioritas | Status |
|----|-----------|-----------|--------|
| F-11 | CRUD template audit (Admin only) | Tinggi | ✅ Implemented |
| F-12 | Definisi tipe audit (Mix Oil, Vendor Evaluation, dll) | Tinggi | ✅ Implemented |
| F-13 | Konfigurasi scoring system | Sedang | ✅ Implemented |
| F-14 | Aktifkan/nonaktifkan template | Sedang | ✅ Implemented |
| F-15 | Template sections (grouping checklist items) | Tinggi | ✅ Implemented |
| F-16 | Template items dengan multiple field types | Tinggi | ✅ Implemented |
| F-17 | Field types: checkbox, date, text, number, radio, textarea, select | Tinggi | ✅ Implemented |
| F-18 | Field validation rules (required/optional) | Tinggi | ✅ Implemented |
| F-19 | Copy template untuk duplikasi cepat | Sedang | ✅ Implemented |

#### 3.1.4 Modul Audit Submission
| ID | Kebutuhan | Prioritas | Status |
|----|-----------|-----------|--------|
| F-20 | Pilih template audit sebelum membuat submission | Tinggi | ✅ Implemented |
| F-21 | Pengisian form audit dinamis sesuai template | Tinggi | ✅ Implemented |
| F-22 | Input data vendor/seller | Tinggi | ✅ Implemented |
| F-23 | Input quantity, harga satuan, total harga | Tinggi | ✅ Implemented |
| F-24 | Auto-calculate total harga (Qty × Harga Satuan) | Tinggi | ✅ Implemented |
| F-25 | Upload foto/dokumen pendukung (multiple files) | Sedang | ✅ Implemented |
| F-26 | Penomoran audit otomatis per template | Sedang | ✅ Implemented |
| F-27 | Save draft audit (dapat dilanjutkan kemudian) | Sedang | ✅ Implemented |
| F-28 | Submit audit untuk review | Tinggi | ✅ Implemented |
| F-29 | Edit audit yang masih draft | Sedang | ✅ Implemented |
| F-30 | Delete audit | Sedang | ✅ Implemented |

#### 3.1.5 Modul Business Logic & Validasi
| ID | Kebutuhan | Prioritas | Status |
|----|-----------|-----------|--------|
| F-31 | Validasi DP minimal 50% dari total harga | Tinggi | ✅ Implemented |
| F-32 | Validasi pelunasan = Total - DP | Tinggi | ✅ Implemented |
| F-33 | Validasi Qty di bukti transfer ≤ Qty SPK | Tinggi | ✅ Implemented |
| F-34 | Conditional validation: tanggal wajib jika pilih "Ada" | Tinggi | ✅ Implemented |
| F-35 | Approval routing otomatis berdasarkan nilai transaksi | Tinggi | ✅ Implemented |
| F-36 | Multi-level approval (Level 1, 2, 3) | Tinggi | ✅ Implemented |
| F-37 | Kategori approval: Procurement dan Finance | Tinggi | ✅ Implemented |
| F-38 | Dynamic approval items sesuai level | Sedang | ✅ Implemented |
| F-39 | Auto-scoring berdasarkan jawaban checklist | Sedang | ✅ Implemented |
| F-40 | Perhitungan persentase score | Sedang | ✅ Implemented |

#### 3.1.6 Modul Approval Rules (Admin)
| ID | Kebutuhan | Prioritas | Status |
|----|-----------|-----------|--------|
| F-41 | Definisi rule approval berdasarkan nilai transaksi | Tinggi | ✅ Implemented |
| F-42 | Kondisi: ≤, <, >, between | Tinggi | ✅ Implemented |
| F-43 | Assign approval ke kategori (Procurement/Finance) | Tinggi | ✅ Implemented |
| F-44 | Approval level (1, 2, 3) | Tinggi | ✅ Implemented |
| F-45 | CRUD approval rules | Tinggi | ✅ Implemented |
| F-46 | Aktifkan/nonaktifkan rule | Sedang | ✅ Implemented |

#### 3.1.7 Modul View & Export
| ID | Kebutuhan | Prioritas | Status |
|----|-----------|-----------|--------|
| F-47 | View detail audit submission | Tinggi | ✅ Implemented |
| F-48 | Tampilan Excel-like format | Sedang | ✅ Implemented |
| F-49 | Export ke PDF | Tinggi | ✅ Implemented |
| F-50 | Preview foto/dokumen yang diupload | Sedang | ✅ Implemented |
| F-51 | List semua audit dengan filter | Tinggi | ✅ Implemented |
| F-52 | Search audit by nomor, vendor, tanggal | Sedang | ✅ Implemented |
| F-53 | Filter by status (Draft, Submitted, Approved, dll) | Sedang | ✅ Implemented |
| F-54 | Pagination untuk large dataset | Sedang | ✅ Implemented |

#### 3.1.8 Modul User Management (Admin)
| ID | Kebutuhan | Prioritas | Status |
|----|-----------|-----------|--------|
| F-55 | CRUD user | Tinggi | ✅ Implemented |
| F-56 | Assign role (Admin, Auditor, Viewer) | Tinggi | ✅ Implemented |
| F-57 | Reset password user | Sedang | ✅ Implemented |
| F-58 | View user activity log | Rendah | ❌ Not Implemented |

### 3.2 Kebutuhan Non-Fungsional

#### 3.2.1 Keamanan (Security)
| ID | Kebutuhan | Deskripsi | Status |
|----|-----------|-----------|--------|
| NF-01 | Password Hashing | Menggunakan bcrypt untuk hash password | ✅ Implemented |
| NF-02 | SQL Injection Prevention | Prepared statements untuk query database | ✅ Implemented |
| NF-03 | XSS Prevention | Sanitasi input user | ✅ Implemented |
| NF-04 | Session Management | Secure session handling | ✅ Implemented |
| NF-05 | Role-based Access | Pembatasan akses berdasarkan role | ✅ Implemented |
| NF-06 | CSRF Protection | Token untuk form submission | ⚠️ Partial |

#### 3.2.2 Performance
| ID | Kebutuhan | Deskripsi | Target |
|----|-----------|-----------|--------|
| NF-07 | Response Time | Halaman loading < 2 detik | Untuk 1000 records |
| NF-08 | Database Query | Optimasi query dengan index | ✅ Implemented |
| NF-09 | File Upload | Maksimal 10MB per file | ✅ Implemented |
| NF-10 | Concurrent Users | Support minimal 50 concurrent users | To be tested |

#### 3.2.3 Usability
| ID | Kebutuhan | Deskripsi | Status |
|----|-----------|-----------|--------|
| NF-11 | User Interface | Modern, clean, dan intuitif | ✅ Implemented |
| NF-12 | Responsive Design | Dapat diakses dari desktop dan tablet | ✅ Implemented |
| NF-13 | Error Messages | Pesan error yang jelas dan informatif | ✅ Implemented |
| NF-14 | Help/Tooltip | Panduan untuk user | ⚠️ Partial |
| NF-15 | Notification | Flash messages untuk feedback | ✅ Implemented |

#### 3.2.4 Reliability
| ID | Kebutuhan | Deskripsi | Status |
|----|-----------|-----------|--------|
| NF-16 | Data Backup | Automated database backup | ⚠️ Manual |
| NF-17 | Error Handling | Graceful error handling | ✅ Implemented |
| NF-18 | Data Validation | Client-side & server-side validation | ✅ Implemented |
| NF-19 | Transaction | ACID properties untuk critical operations | ⚠️ Partial |

#### 3.2.5 Maintainability
| ID | Kebutuhan | Deskripsi | Status |
|----|-----------|-----------|--------|
| NF-20 | Code Structure | Modular dan terorganisir | ✅ Implemented |
| NF-21 | Documentation | Code comments dan README | ✅ Implemented |
| NF-22 | Configuration | Centralized config file | ✅ Implemented |
| NF-23 | Database Schema | Well-structured dengan foreign keys | ✅ Implemented |

---

## 4. ANALISIS AKTOR (USER)

### 4.1 Aktor dan Perannya

#### 4.1.1 Administrator
**Deskripsi**: Super user dengan akses penuh ke sistem
**Tanggung Jawab**:
- Mengelola user (create, edit, delete)
- Mengelola template audit
- Mengatur approval rules
- Mengatur approval items
- Monitoring seluruh aktivitas sistem
- Konfigurasi sistem

**Hak Akses**:
- ✅ Dashboard (full access)
- ✅ Create/Edit/Delete Audit
- ✅ View All Audits
- ✅ User Management
- ✅ Template Management
- ✅ Approval Rules Management
- ✅ System Configuration

#### 4.1.2 Auditor
**Deskripsi**: User yang melakukan audit dan mengisi form
**Tanggung Jawab**:
- Membuat audit submission baru
- Mengisi form audit sesuai template
- Upload dokumen pendukung
- Submit audit untuk review
- Edit audit yang masih draft
- View audit yang dibuat sendiri

**Hak Akses**:
- ✅ Dashboard (limited)
- ✅ Create Audit
- ✅ Edit Own Draft Audit
- ✅ Submit Audit
- ✅ View Own Audits
- ❌ User Management
- ❌ Template Management
- ❌ Delete Audit

#### 4.1.3 Viewer
**Deskripsi**: User yang hanya dapat melihat data audit
**Tanggung Jawab**:
- Melihat list audit
- Melihat detail audit
- Export/print audit

**Hak Akses**:
- ✅ Dashboard (read-only)
- ✅ View All Audits
- ✅ Export/Print Audit
- ❌ Create/Edit/Delete Audit
- ❌ User Management
- ❌ Template Management

---

## 5. USE CASE DIAGRAM

### 5.1 Use Case Utama

```
┌─────────────────────────────────────────────────────────────┐
│                    SELF AUDIT SYSTEM                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────┐                                              │
│  │ Admin    │────────┐                                     │
│  └──────────┘        │                                     │
│       │              ├──> UC-01: Login                     │
│       │              ├──> UC-02: Kelola User               │
│       │              ├──> UC-03: Kelola Template           │
│       │              ├──> UC-04: Kelola Approval Rules     │
│       │              ├──> UC-05: View Dashboard            │
│       │              ├──> UC-06: Buat Audit                │
│       │              ├──> UC-07: Edit Audit                │
│       │              ├──> UC-08: Delete Audit              │
│       │              ├──> UC-09: View Audit List           │
│       │              └──> UC-10: Export Audit PDF          │
│                                                             │
│  ┌──────────┐                                              │
│  │ Auditor  │────────┐                                     │
│  └──────────┘        │                                     │
│       │              ├──> UC-01: Login                     │
│       │              ├──> UC-05: View Dashboard            │
│       │              ├──> UC-06: Buat Audit                │
│       │              ├──> UC-07: Edit Own Draft Audit      │
│       │              ├──> UC-09: View Own Audits           │
│       │              └──> UC-10: Export Audit PDF          │
│                                                             │
│  ┌──────────┐                                              │
│  │ Viewer   │────────┐                                     │
│  └──────────┘        │                                     │
│                      ├──> UC-01: Login                     │
│                      ├──> UC-05: View Dashboard            │
│                      ├──> UC-09: View All Audits           │
│                      └──> UC-10: Export Audit PDF          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 5.2 Detail Use Case

#### UC-06: Buat Audit (Create Audit Submission)

**ID**: UC-06  
**Nama**: Buat Audit Baru  
**Aktor**: Admin, Auditor  
**Deskripsi**: User membuat audit submission baru berdasarkan template yang dipilih  
**Precondition**: User sudah login  
**Postcondition**: Audit submission tersimpan di database dengan status draft/submitted  

**Alur Normal**:
1. User mengakses menu "Buat Audit"
2. Sistem menampilkan list template audit yang aktif
3. User memilih template audit
4. Sistem menampilkan form audit sesuai template
5. User mengisi data vendor/seller
6. User mengisi quantity dan harga
7. Sistem auto-calculate total harga
8. Sistem menampilkan approval routing otomatis berdasarkan nilai
9. User mengisi checklist items sesuai section
10. Sistem melakukan validasi conditional (tanggal wajib jika "Ada")
11. User mengisi data pembayaran (DP dan pelunasan)
12. Sistem validasi DP minimal 50%
13. Sistem validasi pelunasan = Total - DP
14. User upload foto/dokumen pendukung (optional)
15. Sistem menampilkan approval items sesuai level
16. User mengisi approval checklist
17. User submit atau save draft
18. Sistem generate nomor audit otomatis
19. Sistem calculate score
20. Sistem simpan data ke database
21. Sistem tampilkan konfirmasi sukses

**Alur Alternatif**:
- 5a. User tidak mengisi field required → Sistem tampilkan error
- 11a. DP < 50% → Sistem tampilkan warning
- 13a. Qty transfer > Qty SPK → Sistem tampilkan error
- 14a. File upload terlalu besar → Sistem tampilkan error
- 17a. User pilih save draft → Audit tersimpan dengan status draft

---

## 6. ANALISIS BASIS DATA

### 6.1 Entity Relationship Diagram (ERD)

```
┌─────────────┐          ┌──────────────────┐          ┌──────────────────┐
│   users     │          │ audit_templates  │          │ approval_rules   │
├─────────────┤          ├──────────────────┤          ├──────────────────┤
│ id (PK)     │──────┐   │ id (PK)          │──────┐   │ id (PK)          │
│ username    │      │   │ template_name    │      │   │ template_id (FK) │
│ password    │      │   │ template_code    │      │   │ rule_name        │
│ full_name   │      │   │ audit_type       │      │   │ required_appr    │
│ email       │      │   │ description      │      │   │ approval_cat     │
│ role        │      │   │ scoring_enabled  │      │   │ condition_op     │
│ created_at  │      │   │ max_score        │      │   │ condition_value  │
│ updated_at  │      │   │ is_active        │      │   │ approval_level   │
└─────────────┘      │   │ created_by (FK)  │──┐   │   │ is_active        │
                     │   │ created_at       │  │   │   └──────────────────┘
                     │   │ updated_at       │  │   │
                     │   └──────────────────┘  │   │   ┌──────────────────┐
                     │            │            │   │   │ approval_items   │
                     │            │            │   │   ├──────────────────┤
                     │            └────────────┼───┘   │ id (PK)          │
                     │                         │       │ template_id (FK) │
                     │   ┌──────────────────┐  │       │ item_name        │
                     │   │template_sections │  │       │ item_order       │
                     │   ├──────────────────┤  │       │ required_for_lvl │
                     │   │ id (PK)          │  │       │ is_active        │
                     │   │ template_id (FK) │──┘       └──────────────────┘
                     │   │ section_order    │
                     │   │ section_title    │          ┌──────────────────┐
                     │   │ created_at       │          │ po_info          │
                     │   └──────────────────┘          ├──────────────────┤
                     │            │                    │ id (PK)          │
                     │            │                    │ submission_id(FK)│
                     │            ▼                    │ po_number        │
                     │   ┌──────────────────┐          │ po_date          │
                     │   │ template_items   │          │ supplier_name    │
                     │   ├──────────────────┤          │ description      │
                     │   │ id (PK)          │          │ amount           │
                     │   │ section_id (FK)  │          │ payment_terms    │
                     │   │ item_order       │          │ delivery_date    │
                     │   │ item_text        │          │ notes            │
                     │   │ field_type       │          └──────────────────┘
                     │   │ field_options    │
                     │   │ score_value      │
                     │   │ is_required      │
                     │   │ created_at       │
                     │   └──────────────────┘
                     │            │
                     │            │
                     └────────────┼───────────┐
                                  │           │
                     ┌────────────▼──────────┐│
                     │ audit_submissions    ││
                     ├──────────────────────┤│
                     │ id (PK)              ││
                     │ template_id (FK)     │┘
                     │ audit_number         │
                     │ submitted_by (FK)    │
                     │ submission_date      │
                     │ seller_name          │
                     │ quantity             │
                     │ unit_price           │
                     │ total_price          │
                     │ total_score          │
                     │ percentage_score     │
                     │ status               │
                     │ auto_status          │
                     │ notes                │
                     │ created_at           │
                     │ updated_at           │
                     └──────────────────────┘
                                  │
                                  │
                                  ▼
                     ┌──────────────────────┐
                     │ audit_responses      │
                     ├──────────────────────┤
                     │ id (PK)              │
                     │ submission_id (FK)   │
                     │ item_id (FK)         │
                     │ response_value       │
                     │ response_date        │
                     │ created_at           │
                     └──────────────────────┘
```

### 6.2 Deskripsi Tabel

#### 6.2.1 users
Menyimpan data user sistem dengan role-based access.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| username | VARCHAR(50) | Username unik untuk login |
| password | VARCHAR(255) | Password ter-hash (bcrypt) |
| full_name | VARCHAR(100) | Nama lengkap user |
| email | VARCHAR(100) | Email user |
| role | ENUM | Role: admin, auditor, viewer |
| created_at | TIMESTAMP | Waktu pembuatan record |
| updated_at | TIMESTAMP | Waktu update terakhir |

#### 6.2.2 audit_templates
Menyimpan master template audit yang dapat dikustomisasi.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| template_name | VARCHAR(100) | Nama template |
| template_code | VARCHAR(50) | Kode unik template |
| audit_type | ENUM | Jenis audit (mix_oil, vendor_evaluation, dll) |
| description | TEXT | Deskripsi template |
| scoring_enabled | TINYINT | Flag enable/disable scoring |
| max_score | INT | Maksimal score (default 100) |
| is_active | TINYINT | Status aktif template |
| created_by | INT | Foreign key ke users |
| created_at | TIMESTAMP | Waktu pembuatan |
| updated_at | TIMESTAMP | Waktu update terakhir |

#### 6.2.3 template_sections
Menyimpan section/bagian dalam template audit untuk grouping items.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| template_id | INT | Foreign key ke audit_templates |
| section_order | INT | Urutan section |
| section_title | VARCHAR(200) | Judul section |
| created_at | TIMESTAMP | Waktu pembuatan |

#### 6.2.4 template_items
Menyimpan item checklist dalam setiap section dengan berbagai tipe field.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| section_id | INT | Foreign key ke template_sections |
| item_order | INT | Urutan item dalam section |
| item_text | TEXT | Teks pertanyaan/checklist |
| field_type | ENUM | Tipe field: checkbox, date, text, number, radio, textarea, select |
| field_options | TEXT | Options untuk radio/select (JSON format) |
| score_value | INT | Nilai score untuk item ini |
| is_required | TINYINT | Flag wajib/tidak wajib |
| created_at | TIMESTAMP | Waktu pembuatan |

#### 6.2.5 audit_submissions
Menyimpan data submission audit yang dibuat oleh user.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| template_id | INT | Foreign key ke audit_templates |
| audit_number | INT | Nomor audit per template (auto-increment) |
| submitted_by | INT | Foreign key ke users |
| submission_date | DATE | Tanggal submission |
| seller_name | VARCHAR(100) | Nama vendor/seller |
| quantity | VARCHAR(50) | Jumlah barang |
| unit_price | VARCHAR(50) | Harga satuan |
| total_price | VARCHAR(50) | Total harga (calculated) |
| total_score | INT | Total score audit |
| percentage_score | DECIMAL(5,2) | Persentase score |
| status | ENUM | Status: draft, submitted, reviewed, approved, rejected |
| auto_status | VARCHAR(50) | Status otomatis dari approval routing |
| notes | TEXT | Catatan tambahan |
| created_at | TIMESTAMP | Waktu pembuatan |
| updated_at | TIMESTAMP | Waktu update terakhir |

#### 6.2.6 audit_responses
Menyimpan jawaban/response untuk setiap item dalam audit submission.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| submission_id | INT | Foreign key ke audit_submissions |
| item_id | INT | Foreign key ke template_items |
| response_value | TEXT | Nilai jawaban user |
| response_date | DATE | Tanggal response (untuk field type date) |
| created_at | TIMESTAMP | Waktu pembuatan |

#### 6.2.7 approval_rules
Menyimpan rule approval routing berdasarkan nilai transaksi.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| template_id | INT | Foreign key ke audit_templates |
| rule_name | VARCHAR(100) | Nama rule |
| required_approval | VARCHAR(200) | Approval yang diperlukan |
| approval_category | VARCHAR(50) | Kategori: Procurement, Finance |
| condition_operator | VARCHAR(20) | Operator: <=, <, >, between |
| condition_value | VARCHAR(50) | Nilai kondisi |
| approval_level | INT | Level approval (1, 2, 3) |
| is_active | TINYINT | Status aktif rule |

#### 6.2.8 approval_items
Menyimpan item checklist approval yang dinamis berdasarkan level.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| template_id | INT | Foreign key ke audit_templates |
| item_name | VARCHAR(200) | Nama item approval |
| item_order | INT | Urutan item |
| required_for_level | INT | Required untuk level berapa (1, 2, 3) |
| is_active | TINYINT | Status aktif item |

#### 6.2.9 po_info
Menyimpan informasi Purchase Order terkait audit submission.

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| submission_id | INT | Foreign key ke audit_submissions |
| po_number | VARCHAR(50) | Nomor PO |
| po_date | DATE | Tanggal PO |
| supplier_name | VARCHAR(100) | Nama supplier |
| description | TEXT | Deskripsi PO |
| amount | DECIMAL(15,2) | Nilai PO |
| payment_terms | VARCHAR(100) | Syarat pembayaran |
| delivery_date | DATE | Tanggal pengiriman |
| notes | TEXT | Catatan PO |

---

## 7. ANALISIS PROSES BISNIS

### 7.1 Business Process Flow - Audit Mix Oil

```
┌──────────────┐
│    START     │
└──────┬───────┘
       │
       ▼
┌──────────────────────────────┐
│ 1. PENGAJUAN USULAN PENJUALAN│
├──────────────────────────────┤
│ - ROA (dan QFS)              │
│ - Email konfirmasi ke Trading│
│ - Email usulan dari User     │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ 2. PELAKSANAAN PENJUALAN     │
├──────────────────────────────┤
│ - Input 3 penawaran vendor   │
│ - Approval QCF               │
│ - Upload QCF document        │
│ - Input periode QCF          │
│ - Upload SPK/PJB             │
│ - Approval SPK dari customer │
│ - Kirim email ke Refcon      │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ 3. INPUT NILAI TRANSAKSI     │
├──────────────────────────────┤
│ - Input Qty                  │
│ - Input Harga Satuan         │
│ - Auto Calculate Total       │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ 4. APPROVAL ROUTING          │
├──────────────────────────────┤
│ IF Total ≤ 500 juta:         │
│   - Procurement: Yuliasri    │
│   - Finance: Sulistyo        │
│   - Level: 1                 │
│                              │
│ IF 500 juta < Total ≤ 5M:    │
│   - Procurement: Maya        │
│   - Finance: Rahadian        │
│   - Level: 2                 │
│                              │
│ IF Total > 5 Milyar:         │
│   - Procurement: Yudi        │
│   - Finance: Zainul          │
│   - Level: 3                 │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ 5. PENERIMAAN PEMBAYARAN     │
├──────────────────────────────┤
│ - Konfirmasi Qty & Harga     │
│ - Upload bukti DP            │
│ - Input nilai DP             │
│ ┌─────────────────────────┐  │
│ │ VALIDASI: DP ≥ 50%      │  │
│ └─────────────────────────┘  │
│ - Radio: Info penerimaan DP  │
│ - Upload bukti pelunasan     │
│ - Input nilai pelunasan      │
│ ┌─────────────────────────┐  │
│ │ VALIDASI:               │  │
│ │ Pelunasan = Total - DP  │  │
│ │ Sisa harus 0            │  │
│ └─────────────────────────┘  │
│ - Email instruksi pengambilan│
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ 6. MENGELUARKAN BARANG       │
├──────────────────────────────┤
│ - Koordinasi jadwal ambil    │
│ - Upload BON keluar          │
│ - Input Qty BON keluar       │
│ ┌─────────────────────────┐  │
│ │ VALIDASI:               │  │
│ │ Qty BON ≤ Qty SPK       │  │
│ └─────────────────────────┘  │
│ - Kirim BON via email        │
│ - TTD serah terima dari cust │
│ - Upload foto barang keluar  │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ 7. REVIEW APPROVAL ITEMS     │
├──────────────────────────────┤
│ Dynamic checklist sesuai     │
│ approval level yang diperlukan│
│                              │
│ Level 1: 5 items             │
│ Level 2: 7 items             │
│ Level 3: 10 items            │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ 8. AUTO SCORING              │
├──────────────────────────────┤
│ - Hitung total score         │
│ - Hitung persentase          │
│ - Tentukan status            │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ 9. SUBMIT AUDIT              │
├──────────────────────────────┤
│ - Generate audit number      │
│ - Save to database           │
│ - Status: Submitted          │
│ - Send notification          │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────┐
│     END      │
└──────────────┘
```

### 7.2 Business Rules

#### 7.2.1 Validasi Down Payment (DP)
**Rule**: DP minimal harus 50% dari total harga  
**Implementasi**: 
- Input: Nilai DP dan Total Harga
- Calculation: Persentase DP = (DP / Total) × 100%
- Validation: Persentase DP ≥ 50%
- Error Message: "DP minimal harus 50% dari total harga. Saat ini: {X}%"

**Contoh**:
```
Total Harga: Rp 1.000.000.000
DP Valid: ≥ Rp 500.000.000
DP Invalid: < Rp 500.000.000
```

#### 7.2.2 Validasi Pelunasan
**Rule**: Pelunasan harus sama dengan Total - DP (Sisa = 0)  
**Implementasi**:
- Input: Total Harga, DP, Pelunasan
- Calculation: Sisa = Total - DP - Pelunasan
- Validation: Sisa = 0
- Error Message: "Total pembayaran tidak sesuai. Sisa: Rp {Sisa}"

**Contoh**:
```
Total Harga: Rp 1.000.000.000
DP: Rp 600.000.000
Pelunasan Valid: Rp 400.000.000 (Sisa = 0)
Pelunasan Invalid: Rp 300.000.000 (Sisa = 100.000.000)
```

#### 7.2.3 Validasi Quantity
**Rule**: Quantity di BON tidak boleh melebihi Quantity di SPK  
**Implementasi**:
- Input: Qty SPK, Qty BON
- Validation: Qty BON ≤ Qty SPK
- Error Message: "Qty barang keluar ({Qty BON}) melebihi Qty di SPK ({Qty SPK})"

**Contoh**:
```
Qty SPK: 1000 Liter
Qty BON Valid: ≤ 1000 Liter
Qty BON Invalid: > 1000 Liter
```

#### 7.2.4 Conditional Validation - Tanggal
**Rule**: Jika user pilih "Ada", maka tanggal wajib diisi  
**Implementasi**:
- Field Type: Radio button dengan options ["Ada", "Tidak Ada"]
- Conditional: IF radio = "Ada" THEN date field required
- Client-side validation menggunakan JavaScript
- Server-side validation untuk backup

**Contoh**:
```
Approval QCF: [Ada] → Periode QCF: [Required]
Approval QCF: [Tidak Ada] → Periode QCF: [Optional]
```

#### 7.2.5 Approval Routing Rules
**Rule**: Approval otomatis berdasarkan nilai transaksi dengan 2 kategori dan 3 level

**Level 1: ≤ Rp 500.000.000**
- Procurement: Yuliasri
- Finance: Sulistyo
- Approval Items: 5 items

**Level 2: Rp 500.000.000 < Total ≤ Rp 5.000.000.000**
- Procurement: Maya
- Finance: Rahadian
- Approval Items: 7 items

**Level 3: > Rp 5.000.000.000**
- Procurement: Yudi (Procurement Director)
- Finance: Zainul Arifin (Finance Director)
- Approval Items: 10 items

#### 7.2.6 Auto Calculation
**Rule**: Total harga otomatis dihitung dari Qty × Harga Satuan  
**Implementasi**:
- Input: Quantity (number), Unit Price (number)
- Calculation: Total Price = Quantity × Unit Price
- Real-time calculation menggunakan JavaScript
- Format output: Rp X.XXX.XXX

**Contoh**:
```
Quantity: 1000
Unit Price: Rp 50.000
Total Price: Rp 50.000.000 (auto-calculated)
```

#### 7.2.7 Penomoran Audit
**Rule**: Nomor audit auto-increment per template, dimulai dari 1  
**Implementasi**:
- Query: MAX(audit_number) WHERE template_id = X
- Calculation: New audit_number = MAX + 1
- Format: Template Code + nomor (contoh: MIX_OIL_001-0001)

**Contoh**:
```
Template: MIX_OIL_001
Audit #1: MIX_OIL_001-0001
Audit #2: MIX_OIL_001-0002
Audit #3: MIX_OIL_001-0003
```

#### 7.2.8 Scoring System
**Rule**: Perhitungan score otomatis berdasarkan jawaban checklist  
**Implementasi**:
- Setiap template item memiliki score_value
- IF checkbox checked OR radio selected OR text filled → add score_value
- Total Score = SUM(score_value dari semua item yang terjawab)
- Percentage Score = (Total Score / Max Score) × 100%

**Contoh**:
```
Max Score: 100
Item 1 (checked): +10
Item 2 (checked): +15
Item 3 (not checked): +0
Item 4 (checked): +20
Total Score: 45
Percentage: 45%
```

---

## 8. ANALISIS TEKNOLOGI

### 8.1 Teknologi yang Digunakan

#### 8.1.1 Backend
| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| PHP | 7.4+ | Server-side scripting |
| MySQL/MariaDB | 5.7+ / 10.3+ | Database management |
| Apache | 2.4+ | Web server |

#### 8.1.2 Frontend
| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| HTML5 | - | Markup structure |
| CSS3 | - | Styling & layout |
| JavaScript | ES6 | Client-side interactivity |
| Font Awesome | 6.4.0 | Icons |

#### 8.1.3 Development Tools
| Tool | Fungsi |
|------|--------|
| XAMPP | Local development environment |
| Git | Version control |
| GitHub | Repository hosting |
| VS Code / PHPStorm | IDE |
| phpMyAdmin | Database administration |

### 8.2 Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │         Web Browser (Chrome, Firefox, etc)      │   │
│  └─────────────────────────────────────────────────┘   │
│              │                                          │
│              │ HTTP/HTTPS                               │
│              ▼                                          │
└─────────────────────────────────────────────────────────┘
               │
               │
┌──────────────┼──────────────────────────────────────────┐
│              ▼          WEB SERVER LAYER                │
│  ┌───────────────────────────────────────────────────┐ │
│  │              Apache Web Server                    │ │
│  │              (Port 80/443)                        │ │
│  └───────────────────────────────────────────────────┘ │
│              │                                          │
│              ▼                                          │
└─────────────────────────────────────────────────────────┘
               │
               │
┌──────────────┼──────────────────────────────────────────┐
│              ▼       APPLICATION LAYER                  │
│  ┌───────────────────────────────────────────────────┐ │
│  │                 PHP Application                   │ │
│  ├───────────────────────────────────────────────────┤ │
│  │  ┌─────────────┐  ┌─────────────┐               │ │
│  │  │  Config     │  │  Includes   │               │ │
│  │  │  - config   │  │  - functions│               │ │
│  │  │  - database │  │  - business │               │ │
│  │  │             │  │    logic    │               │ │
│  │  │             │  │  - header   │               │ │
│  │  │             │  │  - footer   │               │ │
│  │  └─────────────┘  └─────────────┘               │ │
│  │                                                   │ │
│  │  ┌───────────────────────────────────────────┐  │ │
│  │  │         Application Modules               │  │ │
│  │  ├───────────────────────────────────────────┤  │ │
│  │  │ - Authentication (login/logout)           │  │ │
│  │  │ - Dashboard                               │  │ │
│  │  │ - Audit Management (CRUD)                 │  │ │
│  │  │ - Template Management (Admin)             │  │ │
│  │  │ - User Management (Admin)                 │  │ │
│  │  │ - Approval Rules Management               │  │ │
│  │  │ - Export/Download (PDF)                   │  │ │
│  │  └───────────────────────────────────────────┘  │ │
│  └───────────────────────────────────────────────────┘ │
│              │                                          │
│              ▼                                          │
└─────────────────────────────────────────────────────────┘
               │
               │ PDO/MySQLi
               │
┌──────────────┼──────────────────────────────────────────┐
│              ▼          DATABASE LAYER                  │
│  ┌───────────────────────────────────────────────────┐ │
│  │            MySQL/MariaDB Server                   │ │
│  ├───────────────────────────────────────────────────┤ │
│  │  Database: audit_system                           │ │
│  │  ┌─────────────────┐  ┌─────────────────┐        │ │
│  │  │  Master Tables  │  │ Transaction Tbl │        │ │
│  │  ├─────────────────┤  ├─────────────────┤        │ │
│  │  │ - users         │  │ - audit_subm... │        │ │
│  │  │ - audit_templ.. │  │ - audit_resp... │        │ │
│  │  │ - template_sec..│  │ - po_info       │        │ │
│  │  │ - template_item │  │                 │        │ │
│  │  │ - approval_rule │  │                 │        │ │
│  │  │ - approval_item │  │                 │        │ │
│  │  └─────────────────┘  └─────────────────┘        │ │
│  └───────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### 8.3 Struktur Folder Aplikasi

```
Project_Audit/
│
├── admin/                      # Module khusus admin
│   ├── templates.php           # Kelola template
│   ├── template_view.php       # View detail template
│   ├── template_edit.php       # Edit template
│   ├── template_copy.php       # Copy template
│   └── users.php               # Kelola user
│
├── api/                        # API endpoints
│   └── get_template.php        # Get template via AJAX
│
├── assets/                     # Static assets
│   ├── css/
│   │   ├── style.css           # Main stylesheet
│   │   └── excel-style.css     # Excel-like styling
│   └── js/
│       └── script.js           # Main JavaScript
│
├── audit/                      # Module audit
│   ├── select_type.php         # Pilih template audit
│   ├── create.php              # Buat audit baru
│   ├── list.php                # List semua audit
│   ├── view.php                # View detail audit
│   ├── delete.php              # Hapus audit
│   └── download_pdf.php        # Export ke PDF
│
├── config/                     # Konfigurasi
│   ├── config.php              # Main config
│   ├── config.example.php      # Config template
│   ├── database.php            # Database config
│   └── database.example.php    # Database template
│
├── database/                   # Database scripts
│   ├── schema.sql              # Main schema
│   ├── add_advanced_features.sql # Advanced features
│   ├── add_audit_numbering.sql # Audit numbering
│   ├── add_po_date_fields.sql  # PO fields
│   ├── reset_audit_numbers.sql # Reset numbering
│   └── setup_po_templates.sql  # PO templates
│
├── docs/                       # Dokumentasi
│   ├── CHANGELOG_PO_TAGGING.md
│   └── SETUP_PO_TEMPLATES.md
│
├── includes/                   # Reusable components
│   ├── functions.php           # Helper functions
│   ├── business_logic.php      # Business logic engine
│   ├── header.php              # Header template
│   └── footer.php              # Footer template
│
├── uploads/                    # Upload directory
│   └── photos/                 # Uploaded photos
│
├── index.php                   # Dashboard
├── login.php                   # Login page
├── logout.php                  # Logout handler
└── README.md                   # Documentation
```

### 8.4 Keamanan Sistem

#### 8.4.1 Authentication & Authorization
- **Password Hashing**: Menggunakan `password_hash()` dengan algoritma bcrypt
- **Session Management**: PHP session dengan secure configuration
- **Role-based Access Control**: Admin, Auditor, Viewer dengan hak akses berbeda
- **Login Protection**: Redirect otomatis jika belum login

#### 8.4.2 Input Validation
- **Client-side**: JavaScript validation untuk UX
- **Server-side**: PHP validation untuk security
- **Sanitization**: `htmlspecialchars()` dan custom `sanitize()` function
- **Prepared Statements**: Mencegah SQL injection

#### 8.4.3 File Upload Security
- **File Type Validation**: Hanya allow image files
- **File Size Limit**: Maksimal 10MB per file
- **Unique Filename**: Timestamp-based naming untuk avoid collision
- **Secure Directory**: Upload ke folder dengan permission terbatas

---

## 9. FITUR-FITUR UNGGULAN

### 9.1 Digitalisasi Validasi Bisnis

#### 9.1.1 Validasi DP Minimal 50%
**Deskripsi**: Sistem otomatis memvalidasi bahwa nilai Down Payment (DP) minimal 50% dari total harga transaksi.

**Implementasi**:
- Input field: DP Amount, Total Price
- Calculation engine di `business_logic.php`
- Real-time validation dengan error message
- Warning visual dengan warna merah jika tidak memenuhi syarat

**Business Impact**:
- Mencegah kesalahan pembayaran
- Memastikan cash flow perusahaan
- Mengurangi risiko finansial

#### 9.1.2 Validasi Pelunasan
**Deskripsi**: Sistem memastikan total pembayaran (DP + Pelunasan) sama dengan total harga, sehingga tidak ada sisa pembayaran.

**Implementasi**:
- Auto-calculation: Sisa = Total - DP - Pelunasan
- Validation: Sisa harus = 0
- Error message jika tidak sesuai

**Business Impact**:
- Mencegah kekurangan atau kelebihan pembayaran
- Memastikan akurasi data keuangan
- Memudahkan rekonsiliasi

#### 9.1.3 Validasi Quantity
**Deskripsi**: Sistem memvalidasi bahwa quantity di BON keluar tidak melebihi quantity di SPK.

**Implementasi**:
- Comparison: Qty BON ≤ Qty SPK
- Error blocking jika melebihi
- Visual warning

**Business Impact**:
- Mencegah over-delivery
- Kontrol inventory lebih baik
- Mengurangi dispute dengan customer

### 9.2 Approval Routing Otomatis

#### 9.2.1 Multi-level Approval
**Deskripsi**: Sistem otomatis menentukan level approval yang diperlukan berdasarkan nilai transaksi.

**3 Level Approval**:
- **Level 1** (≤ Rp 500 juta): Manager level
- **Level 2** (Rp 500 juta - 5 Milyar): General Manager level
- **Level 3** (> Rp 5 Milyar): Director level

**Business Impact**:
- Approval sesuai authority matrix
- Proses lebih cepat (tidak perlu eskalasi manual)
- Audit trail yang jelas

#### 9.2.2 Dual Category Approval
**Deskripsi**: Setiap transaksi memerlukan approval dari 2 kategori: Procurement dan Finance.

**Kategori**:
- **Procurement**: Validasi aspek operasional dan vendor
- **Finance**: Validasi aspek finansial dan budget

**Business Impact**:
- Check and balance antara departemen
- Validasi yang lebih komprehensif
- Mengurangi risiko fraud

#### 9.2.3 Dynamic Approval Items
**Deskripsi**: Checklist approval yang muncul berbeda sesuai level approval yang diperlukan.

**Jumlah Items**:
- Level 1: 5 items (basic checks)
- Level 2: 7 items (additional checks)
- Level 3: 10 items (comprehensive checks)

**Business Impact**:
- Review lebih detail untuk transaksi besar
- Efisiensi untuk transaksi kecil
- Fleksibel dan scalable

### 9.3 Penomoran Audit Otomatis

**Deskripsi**: Sistem otomatis generate nomor audit yang unik per template, dimulai dari 1 dan auto-increment.

**Format**: `{TEMPLATE_CODE}-{AUDIT_NUMBER}`  
**Contoh**: `MIX_OIL_001-0001`, `MIX_OIL_001-0002`, dst.

**Business Impact**:
- Tracking lebih mudah
- Tidak ada duplikasi nomor
- Referensi yang konsisten

### 9.4 Conditional Validation

**Deskripsi**: Validasi field yang berubah secara dinamis berdasarkan pilihan user di field lain.

**Contoh**: Jika user pilih "Ada" untuk Approval QCF, maka field Periode QCF menjadi wajib diisi.

**Implementasi**:
- JavaScript untuk real-time UX
- PHP untuk server-side validation
- Visual indicator (required asterisk)

**Business Impact**:
- Data lebih lengkap dan akurat
- User guidance yang lebih baik
- Mengurangi data incomplete

### 9.5 Auto-Calculation

**Deskripsi**: Sistem otomatis menghitung nilai-nilai yang dapat dikalkulasi.

**Kalkulasi Otomatis**:
- Total Harga = Qty × Harga Satuan
- Sisa Pembayaran = Total - DP - Pelunasan
- Total Score = Sum of item scores
- Percentage Score = (Total Score / Max Score) × 100%

**Business Impact**:
- Eliminasi human error
- Proses lebih cepat
- Konsistensi data

### 9.6 Scoring System

**Deskripsi**: Sistem scoring otomatis untuk menilai kelengkapan dan kualitas audit.

**Mekanisme**:
- Setiap template item memiliki score value
- Score dijumlahkan otomatis saat submit
- Percentage score untuk standarisasi

**Business Impact**:
- Objektif measurement
- Identifikasi gap dengan cepat
- Performance indicator

### 9.7 Template Management yang Fleksibel

**Deskripsi**: Admin dapat membuat, edit, dan copy template audit sesuai kebutuhan bisnis.

**Fitur**:
- Multiple template types
- Section-based organization
- Various field types (8 types)
- Scoring configuration
- Active/Inactive status

**Business Impact**:
- Adaptasi cepat terhadap perubahan proses bisnis
- Standardisasi proses audit
- Reusability template

### 9.8 Excel-like Interface

**Deskripsi**: Interface form audit dirancang menyerupai Excel untuk memudahkan transisi dari sistem manual.

**Karakteristik**:
- Grid layout
- Cell-like input fields
- Bordered tables
- Familiar look and feel

**Business Impact**:
- Kurva pembelajaran lebih cepat
- User adoption lebih mudah
- Mengurangi resistance to change

### 9.9 Multi-file Upload

**Deskripsi**: User dapat upload multiple foto/dokumen pendukung sekaligus.

**Fitur**:
- Drag and drop support
- Preview before upload
- Multiple file selection
- File type validation

**Business Impact**:
- Dokumentasi lebih lengkap
- Evidence yang kuat
- Audit trail visual

### 9.10 Export ke PDF

**Deskripsi**: Audit submission dapat diekspor ke format PDF untuk keperluan cetak dan sharing.

**Fitur**:
- Professional layout
- Include all sections
- Include uploaded photos
- Include approval information

**Business Impact**:
- Mudah untuk sharing
- Hard copy untuk filing
- Professional presentation

---

## 10. ANALISIS KELEBIHAN DAN KEKURANGAN SISTEM

### 10.1 Kelebihan Sistem

#### 10.1.1 Efisiensi Operasional
✅ **Otomasi Proses**: Banyak proses manual digantikan dengan otomasi
✅ **Approval Routing**: Tidak perlu manual routing, sistem otomatis tentukan approver
✅ **Auto-calculation**: Eliminasi perhitungan manual yang rawan error
✅ **Template Reusable**: Sekali buat template, dapat digunakan berulang kali

#### 10.1.2 Validasi dan Kontrol
✅ **Business Rule Enforcement**: Aturan bisnis ter-enforce otomatis
✅ **Multi-level Validation**: Client-side dan server-side validation
✅ **Conditional Logic**: Validasi yang cerdas berdasarkan context
✅ **Data Integrity**: Foreign key constraints menjaga integritas data

#### 10.1.3 Tracking dan Monitoring
✅ **Real-time Status**: Status audit dapat di-track real-time
✅ **Audit Trail**: History perubahan tercatat
✅ **Dashboard**: Overview statistik sekilas pandang
✅ **Penomoran Sistematis**: Mudah mencari dan referensi audit

#### 10.1.4 User Experience
✅ **Familiar Interface**: Excel-like design memudahkan transisi
✅ **Responsive Design**: Dapat diakses dari berbagai device
✅ **Error Messages**: Pesan error yang jelas dan membantu
✅ **Visual Feedback**: Loading indicators, success/error notifications

#### 10.1.5 Scalability dan Flexibility
✅ **Template System**: Mudah menambah jenis audit baru
✅ **Dynamic Forms**: Form dapat berubah sesuai kebutuhan
✅ **Role-based Access**: Scalable untuk banyak user dengan role berbeda
✅ **Modular Code**: Mudah untuk maintenance dan enhancement

### 10.2 Kekurangan dan Area Improvement

#### 10.2.1 Keamanan
⚠️ **CSRF Protection**: Belum implement CSRF token untuk form
⚠️ **Rate Limiting**: Tidak ada protection untuk brute force attack
⚠️ **2FA**: Tidak ada two-factor authentication
⚠️ **Password Policy**: Tidak ada enforcement untuk password complexity

**Recommendation**: 
- Implement CSRF token
- Add login attempt limiting
- Optional 2FA for admin
- Password strength requirement

#### 10.2.2 Performance
⚠️ **Pagination**: Pagination ada tapi bisa dioptimasi dengan lazy loading
⚠️ **Image Optimization**: Upload image tidak di-compress otomatis
⚠️ **Caching**: Tidak ada caching mechanism
⚠️ **Database Indexing**: Perlu review index untuk query optimization

**Recommendation**:
- Implement Redis/Memcached untuk caching
- Auto-compress uploaded images
- Add more database indexes
- Lazy loading untuk large datasets

#### 10.2.3 Fitur
❌ **Audit Log**: Tidak ada logging untuk user activity
❌ **Email Notification**: Tidak ada email notification untuk approval
❌ **Mobile App**: Hanya web-based, tidak ada mobile app
❌ **Advanced Reporting**: Belum ada dashboard analytics yang advanced
❌ **Bulk Operations**: Tidak ada fitur bulk approve/reject

**Recommendation**:
- Implement comprehensive audit log
- Email notification untuk workflow
- Consider mobile app development
- Advanced analytics dashboard
- Bulk operations untuk admin

#### 10.2.4 Integration
❌ **ERP Integration**: Tidak terintegrasi dengan sistem ERP/SAP
❌ **API**: Belum ada REST API untuk external integration
❌ **Export Options**: Hanya PDF, belum ada Excel export
❌ **Document Management**: Tidak terintegrasi dengan DMS

**Recommendation**:
- Develop REST API
- Export to Excel/CSV
- Integration dengan sistem existing
- Consider DMS integration

#### 10.2.5 Backup dan Recovery
⚠️ **Automated Backup**: Backup masih manual
⚠️ **Disaster Recovery**: Belum ada disaster recovery plan
⚠️ **Version Control**: Data tidak versioned

**Recommendation**:
- Automated daily backup
- Offsite backup storage
- Disaster recovery procedure
- Consider data versioning

---

## 11. RENCANA PENGEMBANGAN SISTEM

### 11.1 Short-term (1-3 bulan)

#### 11.1.1 Security Enhancement
- [ ] Implement CSRF protection
- [ ] Add login rate limiting
- [ ] Password strength enforcement
- [ ] Session timeout configuration
- [ ] Security audit dan penetration testing

#### 11.1.2 Performance Optimization
- [ ] Database query optimization
- [ ] Add database indexes
- [ ] Image compression on upload
- [ ] Implement basic caching
- [ ] Frontend optimization (minify CSS/JS)

#### 11.1.3 User Experience Improvement
- [ ] Enhanced error messages
- [ ] Tooltips dan help text
- [ ] Keyboard shortcuts
- [ ] Better mobile responsive
- [ ] Loading indicators untuk semua AJAX calls

### 11.2 Mid-term (3-6 bulan)

#### 11.2.1 Notification System
- [ ] Email notification untuk approval request
- [ ] Email notification untuk status change
- [ ] In-app notification
- [ ] Notification preferences
- [ ] Digest email option

#### 11.2.2 Advanced Features
- [ ] Audit log untuk user activity
- [ ] Bulk operations (approve/reject multiple)
- [ ] Advanced search dan filter
- [ ] Export to Excel/CSV
- [ ] Print optimization

#### 11.2.3 Analytics dan Reporting
- [ ] Dashboard dengan charts (Chart.js)
- [ ] Trend analysis
- [ ] Performance metrics
- [ ] Custom reports
- [ ] Data export untuk analysis

### 11.3 Long-term (6-12 bulan)

#### 11.3.1 Integration
- [ ] REST API development
- [ ] Integration dengan ERP system
- [ ] Integration dengan email system (SMTP)
- [ ] Integration dengan document management system
- [ ] SSO (Single Sign-On) support

#### 11.3.2 Mobile Development
- [ ] Progressive Web App (PWA)
- [ ] Native mobile app (Android/iOS)
- [ ] Mobile-optimized workflows
- [ ] Offline capability
- [ ] Push notifications

#### 11.3.3 Advanced Capabilities
- [ ] AI/ML untuk anomaly detection
- [ ] Predictive analytics
- [ ] Automated approval berdasarkan ML
- [ ] OCR untuk upload document
- [ ] Natural language processing untuk search

#### 11.3.4 Enterprise Features
- [ ] Multi-tenant support
- [ ] Advanced role management (custom roles)
- [ ] Approval workflow builder (drag-and-drop)
- [ ] Template marketplace
- [ ] API marketplace untuk integration

---

## 12. KESIMPULAN DAN REKOMENDASI

### 12.1 Kesimpulan

Sistem Self Audit yang dikembangkan berhasil mendigitalisasi proses audit manual dengan baik, mencakup fitur-fitur essential yang dibutuhkan untuk operasional sehari-hari. Sistem ini menawarkan beberapa keunggulan utama:

1. **Digitalisasi Penuh**: Menggantikan checklist Excel manual dengan sistem digital yang terstruktur
2. **Otomasi Cerdas**: Approval routing, validasi, dan perhitungan otomatis
3. **Fleksibilitas Tinggi**: Template system yang dapat disesuaikan dengan berbagai jenis audit
4. **User-Friendly**: Interface familiar dengan Excel untuk memudahkan adoption
5. **Business Logic Enforcement**: Aturan bisnis ter-enforce secara sistematis

Sistem ini telah implement best practices dalam development seperti:
- Prepared statements untuk SQL injection prevention
- Password hashing dengan bcrypt
- Role-based access control
- Modular code structure
- Comprehensive database schema dengan foreign keys

### 12.2 Rekomendasi untuk Pengembangan Selanjutnya

#### 12.2.1 Prioritas Tinggi (Immediate Action Required)
1. **Security Enhancement**: Implement CSRF protection dan rate limiting
2. **Backup Strategy**: Setup automated backup untuk data protection
3. **Documentation**: Lengkapi user manual dan technical documentation
4. **Testing**: Comprehensive testing (unit, integration, UAT)

#### 12.2.2 Prioritas Sedang (Next 3-6 months)
1. **Email Notification**: Untuk workflow approval dan status changes
2. **Audit Log**: Comprehensive logging untuk compliance dan troubleshooting
3. **Performance Optimization**: Caching dan query optimization
4. **Analytics Dashboard**: Advanced reporting dan visualisasi data

#### 12.2.3 Prioritas Rendah (Future Enhancement)
1. **Mobile Application**: Native atau PWA untuk mobile access
2. **API Development**: REST API untuk integration dengan sistem lain
3. **AI/ML Features**: Predictive analytics dan anomaly detection
4. **Multi-tenant**: Jika akan dijadikan SaaS product

### 12.3 Rekomendasi untuk Implementasi

#### 12.3.1 Change Management
- Training untuk user tentang sistem baru
- Parallel run dengan sistem lama selama periode transisi
- Feedback mechanism untuk continuous improvement
- Champion di setiap department untuk support adoption

#### 12.3.2 Infrastructure
- Dedicated server atau cloud hosting (AWS, GCP, Azure)
- SSL certificate untuk HTTPS
- Regular backup ke offsite location
- Monitoring dan alerting system

#### 12.3.3 Maintenance
- Regular security updates
- Database optimization berkala
- User feedback review dan prioritization
- Version control dengan Git untuk semua changes

### 12.4 Value Proposition

Sistem ini memberikan value kepada organisasi dalam bentuk:

1. **Efisiensi Waktu**: Reduce proses audit dari beberapa hari menjadi beberapa jam
2. **Akurasi Data**: Eliminasi human error dalam perhitungan dan validasi
3. **Compliance**: Audit trail yang clear untuk compliance requirement
4. **Cost Saving**: Reduce paper usage, manual work, dan error correction cost
5. **Scalability**: Mudah untuk scale up seiring pertumbuhan bisnis
6. **Decision Support**: Data analytics untuk better decision making

### 12.5 Penutup

Sistem Self Audit ini merupakan solusi yang solid untuk digitalisasi proses audit internal. Dengan foundational yang kuat, sistem ini siap untuk digunakan dalam production environment dan dapat dikembangkan lebih lanjut sesuai dengan evolusi kebutuhan bisnis.

Investasi dalam pengembangan sistem ini akan memberikan ROI (Return on Investment) yang signifikan melalui peningkatan efisiensi operasional, pengurangan error, dan improvement dalam compliance dan audit trail.

---

## LAMPIRAN

### A. Daftar Istilah

| Istilah | Definisi |
|---------|----------|
| Self Audit | Audit internal yang dilakukan oleh organisasi terhadap proses bisnisnya sendiri |
| Mix Oil | Minyak campuran yang diperjualbelikan |
| ROA | Request of Approval |
| QFS | Quality Specification |
| QCF | Quality Certificate Form |
| SPK | Surat Perintah Kerja |
| PJB | Perjanjian Jual Beli |
| DP | Down Payment (Uang Muka) |
| BON | Bukti pengeluaran barang |
| Procurement | Departemen pengadaan |
| Approval Routing | Alur persetujuan otomatis |
| Template | Master form audit yang dapat digunakan berulang kali |
| Submission | Instance dari audit yang telah diisi |

### B. Referensi Dokumen

1. [README.md](README.md) - Dokumentasi utama project
2. [CLEANUP_SUMMARY.md](CLEANUP_SUMMARY.md) - Summary cleanup project
3. [CHANGELOG_PO_TAGGING.md](docs/CHANGELOG_PO_TAGGING.md) - Changelog PO tagging feature
4. [SETUP_PO_TEMPLATES.md](docs/SETUP_PO_TEMPLATES.md) - Setup guide PO templates
5. [PENOMORAN_AUDIT_PER_TEMPLATE.md](PENOMORAN_AUDIT_PER_TEMPLATE.md) - Dokumentasi penomoran audit
6. [PERBAIKAN_TANGGAL_PO.md](PERBAIKAN_TANGGAL_PO.md) - Perbaikan tanggal PO

### C. Kontak dan Support

Untuk pertanyaan atau issue terkait sistem, hubungi:
- **Developer**: [GitHub Repository](https://github.com/destarahma/Project_Audit)
- **Email**: admin@audit.com
- **Documentation**: [Project Wiki](https://github.com/destarahma/Project_Audit/wiki)

---

**Dokumen ini dibuat untuk keperluan Laporan Kerja Praktik (KP)**  
**Tanggal**: 25 Januari 2026  
**Versi**: 1.0  
**Status**: Final
