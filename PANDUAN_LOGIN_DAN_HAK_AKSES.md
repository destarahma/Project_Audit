# PANDUAN LOGIN DAN HAK AKSES SISTEM AUDIT

## 📋 Daftar User dan Credentials

Sistem ini memiliki 3 user dengan role berbeda yang dapat digunakan untuk login:

### 1. Administrator (Admin)
```
Username: admin
Password: admin123
Role: Administrator
```
**Deskripsi:** Pengguna dengan hak akses penuh terhadap sistem.

---

### 2. Auditor
```
Username: auditor
Password: auditor123
Role: Auditor
```
**Deskripsi:** Pengguna yang bertugas membuat dan mengisi form audit.

---

### 3. Viewer
```
Username: viewer
Password: viewer123
Role: Viewer
```
**Deskripsi:** Pengguna yang hanya dapat melihat data audit (read-only).

---

## 🔐 Hak Akses Masing-Masing Role

### A. ADMINISTRATOR (Admin)

#### ✅ Akses Penuh:
1. **Dashboard**
   - Melihat statistik lengkap semua audit
   - Melihat audit dari semua user

2. **Audit Submission**
   - ✅ Membuat audit baru
   - ✅ Edit semua audit (termasuk punya user lain)
   - ✅ Hapus semua audit
   - ✅ View semua audit
   - ✅ Export PDF semua audit
   - ✅ Submit audit

3. **Kelola Template Audit**
   - ✅ Melihat semua template
   - ✅ Membuat template baru (via copy)
   - ✅ Edit template
   - ✅ Copy template
   - ✅ Aktifkan/Nonaktifkan template
   - ✅ Lihat preview template

4. **Kelola User**
   - ✅ Melihat semua user
   - ✅ Tambah user baru
   - ✅ Edit user
   - ✅ Hapus user
   - ✅ Reset password user

5. **Filter & Search**
   - ✅ Filter audit (Template, Status, Tanggal)
   - ✅ Search audit (Nomor, Vendor)

#### 📌 Menu yang Terlihat:
- Dashboard
- Daftar Audit (semua audit)
- Buat Audit
- **Kelola Template Audit** (khusus admin)
- **Kelola User** (khusus admin)
- Logout

---

### B. AUDITOR

#### ✅ Akses Terbatas pada Audit Sendiri:
1. **Dashboard**
   - Melihat statistik audit sendiri saja
   - Melihat recent submissions sendiri

2. **Audit Submission**
   - ✅ Membuat audit baru
   - ✅ Edit audit draft sendiri
   - ✅ Hapus draft sendiri
   - ✅ View audit sendiri saja
   - ✅ Export PDF audit sendiri
   - ✅ Submit audit

3. **Kelola Template**
   - ❌ Tidak dapat akses

4. **Kelola User**
   - ❌ Tidak dapat akses

5. **Filter & Search**
   - ✅ Filter audit (hanya audit sendiri)
   - ✅ Search audit (hanya dalam audit sendiri)

#### ⚠️ Batasan:
- Tidak dapat melihat audit user lain
- Tidak dapat edit/hapus audit yang sudah submitted
- Tidak dapat akses halaman admin

#### 📌 Menu yang Terlihat:
- Dashboard (limited)
- Daftar Audit (hanya punya sendiri)
- Buat Audit
- Logout

---

### C. VIEWER

#### ✅ Akses Read-Only Semua Audit:
1. **Dashboard**
   - Melihat statistik semua audit (read-only)
   - Tidak ada tombol action

2. **Audit Viewing**
   - ✅ View semua audit (read-only)
   - ✅ Export PDF semua audit
   - ❌ Tidak dapat create audit
   - ❌ Tidak dapat edit audit
   - ❌ Tidak dapat hapus audit

3. **Kelola Template**
   - ❌ Tidak dapat akses

4. **Kelola User**
   - ❌ Tidak dapat akses

5. **Filter & Search**
   - ✅ Filter audit semua user
   - ✅ Search audit semua user

#### ⚠️ Batasan:
- Hanya dapat melihat, tidak dapat melakukan perubahan
- Tidak ada tombol "Buat Audit", "Edit", "Hapus"
- Tidak dapat akses halaman admin

#### 📌 Menu yang Terlihat:
- Dashboard (read-only)
- Daftar Audit (semua audit, read-only)
- Logout

---

## 🔄 Cara Login

1. Buka browser dan akses: `http://localhost/Project_Audit/`
2. Masukkan username dan password sesuai role yang diinginkan
3. Klik tombol "Login"
4. Sistem akan redirect ke dashboard sesuai role

---

## 📊 Matriks Hak Akses

| Fitur/Aksi | Admin | Auditor | Viewer |
|------------|-------|---------|--------|
| **Dashboard** |
| View Statistics | ✅ All | ✅ Own | ✅ All (RO) |
| **Audit** |
| Create Audit | ✅ | ✅ | ❌ |
| Edit Draft | ✅ All | ✅ Own | ❌ |
| Delete Audit | ✅ All | ✅ Own Draft | ❌ |
| View Audit | ✅ All | ✅ Own | ✅ All (RO) |
| Submit Audit | ✅ | ✅ | ❌ |
| Export PDF | ✅ All | ✅ Own | ✅ All |
| **Filter & Search** |
| Filter Audit | ✅ All | ✅ Own | ✅ All |
| Search Audit | ✅ All | ✅ Own | ✅ All |
| **Administration** |
| Manage Templates | ✅ | ❌ | ❌ |
| Manage Users | ✅ | ❌ | ❌ |
| System Config | ✅ | ❌ | ❌ |

**Keterangan:**
- ✅ = Dapat akses
- ❌ = Tidak dapat akses
- RO = Read Only
- All = Semua data
- Own = Hanya data sendiri

---

## 🧪 Testing Login untuk Setiap Role

### Test 1: Login sebagai Admin
```
1. Logout jika sedang login
2. Login dengan: admin / admin123
3. Cek menu yang muncul: harus ada "Kelola Template" dan "Kelola User"
4. Cek Daftar Audit: harus bisa lihat semua audit dari semua user
5. Cek tombol: harus ada "Buat Audit Baru", "Edit", "Hapus"
```

### Test 2: Login sebagai Auditor
```
1. Logout dari akun sebelumnya
2. Login dengan: auditor / auditor123
3. Cek menu yang muncul: TIDAK ada menu "Kelola Template" dan "Kelola User"
4. Cek Daftar Audit: hanya audit yang dibuat oleh auditor
5. Buat audit baru: harus bisa
6. Edit audit draft: harus bisa (hanya punya sendiri)
7. Coba akses admin/templates.php: harus redirect dengan pesan "Akses ditolak"
```

### Test 3: Login sebagai Viewer
```
1. Logout dari akun sebelumnya
2. Login dengan: viewer / viewer123
3. Cek menu yang muncul: TIDAK ada menu "Kelola Template", "Kelola User", dan "Buat Audit"
4. Cek Daftar Audit: bisa lihat semua audit tapi TIDAK ada tombol Edit/Hapus
5. Coba create audit: tombol "Buat Audit Baru" tidak ada
6. Export PDF: harus bisa
7. Filter & Search: harus bisa, tapi hanya untuk view
```

---

## 🔒 Keamanan

1. **Password** sudah menggunakan bcrypt hash
2. **Session** management untuk autentikasi
3. **Role-based access control** di setiap halaman
4. **Redirect otomatis** jika akses tidak sah
5. **Flash message** untuk feedback aksi

---

## 📝 Catatan Penting

1. **Jangan share password** ke user yang tidak berhak
2. **Ganti password default** setelah deployment
3. **Backup database** secara berkala
4. **Monitor aktivitas** admin secara rutin
5. **Review hak akses** user secara periodik

---

## 🆘 Troubleshooting

### Masalah: Tidak bisa login
**Solusi:**
- Cek username dan password
- Pastikan user sudah dibuat di database
- Cek tabel `users` di phpMyAdmin

### Masalah: Menu admin tidak muncul
**Solusi:**
- Pastikan login dengan user yang role = 'admin'
- Logout dan login ulang
- Clear browser cache

### Masalah: Auditor bisa lihat audit user lain
**Solusi:**
- Cek implementasi di audit/list.php
- Pastikan query WHERE submitted_by = ?
- Report sebagai bug

---

## 📞 Support

Jika ada pertanyaan atau masalah terkait login dan hak akses:
1. Cek dokumentasi ini terlebih dahulu
2. Cek file `includes/functions.php` untuk fungsi requireLogin() dan isAdmin()
3. Cek implementasi role check di setiap halaman

---

**Dibuat pada:** 27 Januari 2026
**Terakhir update:** 27 Januari 2026
