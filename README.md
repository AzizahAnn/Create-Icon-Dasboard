# Create-Icon-Dasboard
## Penugasan Magang Skuyy 

# Panduan Alur Kerja Git (Edit → Add → Pull → Commit → Push)

Dokumen ini menjelaskan urutan perintah Git yang aman dipakai saat bekerja bareng (kolaborasi) di satu repository, supaya perubahan kode dari kamu dan teman satu tim nggak saling menimpa atau bentrok.

## Kenapa Urutannya Begini?

Kalau kamu langsung `commit` + `push` tanpa `pull` dulu, ada risiko kode kamu bentrok sama perubahan teman yang udah lebih dulu push. Dengan **pull dulu sebelum push**, Git bisa gabungin (merge) perubahan kamu sama punya teman lebih awal, jadi konfliknya lebih kecil kemungkinan terjadi pas mau push.

## Langkah-Langkah

### 1. Edit kode seperti biasa
Ubah, tambah, atau hapus file di project sesuai kebutuhan.

### 2. Tandai perubahan yang mau disimpan (staging)
```bash
git add .
```
Perintah ini menandai **semua file yang berubah** di folder project supaya siap di-commit. Kalau cuma mau nandain file tertentu:
```bash
git add nama-file.php
```

### 3. Tarik perubahan terbaru dari GitHub
```bash
git pull origin main
```
Ganti `main` dengan nama branch yang dipakai (kadang namanya `master`). Ini menarik perubahan terbaru dari teman, digabung ke kode kamu.

> ⚠️ **Kalau muncul konflik** (`CONFLICT`), Git akan menandai bagian yang bentrok di file terkait dengan simbol `<<<<<<<`, `=======`, `>>>>>>>`. Buka file itu, putuskan mana yang mau dipakai, hapus simbol-simbol tersebut, lalu lanjut ke langkah 4.

### 4. Commit perubahan
```bash
git commit -m "Deskripsi singkat perubahan yang dibuat"
```
Ganti teks di dalam tanda kutip dengan penjelasan singkat, misalnya:
```bash
git commit -m "Tambah fitur scan gambar via kamera"
```

### 5. Push ke GitHub
```bash
git push origin main
```
Ini mengirim commit kamu ke repository GitHub, supaya teman-teman lain bisa `pull` dan dapat perubahan terbaru.

## Ringkasan Urutan Perintah

```bash
# 1. Edit kode di editor (VS Code, dll)

# 2. Tandai perubahan
git add .

# 3. Tarik perubahan terbaru dari GitHub
git pull origin main

# 4. Commit perubahan
git commit -m "Pesan commit"

# 5. Push ke GitHub
git push origin main
```

## Tips Tambahan

- **Cek status dulu sebelum add**, biar tau file mana aja yang berubah:
  ```bash
  git status
  ```
- **Cek riwayat commit** kalau mau lihat histori perubahan:
  ```bash
  git log --oneline
  ```
- **Kalau ragu ada konflik atau belum yakin mau commit**, jangan buru-buru `push` — cek dulu `git status` dan `git diff` buat lihat perubahan yang bakal dikirim.
- **Biasakan `pull` di awal sesi kerja** (sebelum mulai edit kode hari itu), bukan cuma sebelum push — ini ngurangin kemungkinan konflik besar di kemudian hari.

## Kalau Terjadi Konflik Merge

1. Jalankan `git status` buat lihat file mana yang konflik (ditandai *"both modified"*)
2. Buka file itu, cari penanda konflik:
   ```
   <<<<<<< HEAD
   kode versi kamu
   =======
   kode versi yang ditarik dari pull
   >>>>>>> abcd123
   ```
3. Edit manual, putuskan kode mana yang dipakai (atau gabungkan keduanya), lalu hapus semua simbol `<<<<<<<`, `=======`, `>>>>>>>`
4. Tandai file itu sudah selesai:
   ```bash
   git add nama-file-yang-konflik.php
   ```
5. Lanjutkan commit:
   ```bash
   git commit -m "Resolve merge conflict"
   ```
6. Push seperti biasa:
   ```bash
   git push origin main
   ```