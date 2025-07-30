# Codify - Platform Berbagi Cuplikan Kode

[![Versi PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MySQL-orange.svg)](https://www.mysql.com/)
[![Lisensi](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**Codify** adalah sebuah platform berbasis web yang dirancang untuk developer, programmer, dan penggiat teknologi untuk berbagi cuplikan kode (*code snippet*) dengan mudah dan cepat. Platform ini tidak hanya berfungsi sebagai tempat penyimpanan kode, tetapi juga sebagai ruang komunitas untuk berinteraksi, belajar, dan berkolaborasi.

## Daftar Isi
1.  [Fitur Utama](#fitur-utama)
2.  [Teknologi yang Digunakan](#teknologi-yang-digunakan)
3.  [Struktur Proyek](#struktur-proyek)
4.  [Instalasi & Konfigurasi](#instalasi--konfigurasi)
    * [Prasyarat](#prasyarat)
    * [Langkah-langkah Instalasi](#langkah-langkah-instalasi)
5.  [Dokumentasi API](#dokumentasi-api)
    * [Autentikasi](#autentikasi)
    * [Endpoint](#endpoint)
6.  [Kontribusi](#kontribusi)
7.  [Author](#author)

## Fitur Utama

Codify dilengkapi dengan berbagai fitur untuk meningkatkan pengalaman pengguna:

* **Berbagi Snippet**: Pengguna dapat dengan mudah memposting cuplikan kode dengan judul, deskripsi, dan memilih bahasa pemrograman yang sesuai dengan *syntax highlighting*.
* **Manajemen Snippet**: Pengguna memiliki *dashboard* pribadi untuk mengelola, mengedit, dan menghapus semua cuplikan kode yang pernah mereka bagikan.
* **Profil Pengguna Kustom**: Setiap pengguna memiliki halaman profil yang dapat disesuaikan dengan foto, *banner*, bio, dan tautan sosial media.
* **Sistem Komunitas & Interaksi**:
    * **Komentar**: Pengguna dapat memberikan komentar dan balasan pada setiap *snippet* untuk berdiskusi atau memberikan masukan.
    * **Suka (Like)**: Terdapat tombol "suka" untuk menandai komentar yang bermanfaat.
* **Leaderboard**: Halaman yang menampilkan peringkat pengguna berdasarkan jumlah *snippet* yang mereka bagikan untuk memotivasi kontribusi.
* **Panel Admin (Owner)**: Fitur khusus untuk *Owner* guna mengelola pengguna, peran (*roles*), dan gelar (*titles*).
* **API**: Menyediakan API untuk berbagi dan mengelola *snippet* dari aplikasi eksternal.

## Teknologi yang Digunakan

* **Backend**: PHP 8.1+
* **Database**: MySQL (MariaDB)
* **Frontend**: HTML5, CSS3, JavaScript (dengan Alpine.js dan Tailwind CSS)
* **Syntax Highlighting**: Highlight.js
* **Web Server**: Apache (disarankan, karena ada file `.htaccess`)

## Struktur Proyek


/
├── api/                  # Berisi file-file endpoint API
├── assets/               # File aset seperti CSS dan JavaScript
├── components/           # Komponen UI modular (misal: modal)
├── db/                   # Direktori untuk gambar profil, thumbnail, dll.
├── dbsql/                # Skema database awal
├── includes/             # File-file penting seperti koneksi DB dan fungsi
├── Owner/                # Catatan dan ide untuk pengembangan
├── .htaccess             # Konfigurasi Apache untuk URL bersih dan keamanan
├── admin.php             # Panel khusus Owner
├── dashboard.php         # Dashboard pengguna
├── index.php             # Halaman utama dan registrasi/login
├── leaderboard.php       # Halaman peringkat pengguna
├── profile.php           # Halaman profil pengguna
└── view.php              # Halaman untuk melihat detail snippet


## Instalasi & Konfigurasi

### Prasyarat

* Web server (Apache direkomendasikan)
* PHP versi 8.1 atau lebih tinggi
* Database MySQL atau MariaDB
* Ekstensi PHP: `mysqli`, `finfo`

### Langkah-langkah Instalasi

1.  **Clone Repositori**
    ```bash
    git clone [https://github.com/dgamegt/codify.git](https://github.com/dgamegt/codify.git)
    cd codify
    ```

2.  **Konfigurasi Database**
    * Buat database baru di MySQL/MariaDB.
    * Ubah file `includes/db.php` sesuai dengan kredensial database Anda:
        ```php
        define('DB_SERVER', 'localhost');
        define('DB_USERNAME', 'username_anda');
        define('DB_PASSWORD', 'password_anda');
        define('DB_NAME', 'nama_database_anda');
        ```

3.  **Impor Skema Database**
    * Impor file `dbsql/database.sql` ke dalam database yang baru Anda buat. Ini akan membuat tabel-tabel yang diperlukan.
    * Setelah itu, jalankan juga query dari file `Owner/ide perubahan sql.sql` untuk menambahkan tabel `comments` dan `comment_likes`, serta memperbarui tabel `users`.

4.  **Konfigurasi Web Server**
    * Pastikan `mod_rewrite` di Apache sudah aktif.
    * File `.htaccess` yang sudah ada akan menangani *clean URLs* dan beberapa aspek keamanan dasar.

5.  **Akses Aplikasi**
    * Buka aplikasi melalui browser Anda. Anda bisa mulai dengan mendaftar akun baru. Akun pertama yang dibuat secara otomatis akan menjadi *Owner*.

## Dokumentasi API

Codify menyediakan RESTful API untuk mengelola *snippet* secara terprogram.

### Autentikasi

Semua *request* ke API harus menyertakan *Bearer Token* di *header* `Authorization`. API Key bisa didapatkan dari halaman profil pengguna.

**Header:**
`Authorization: Bearer <API_KEY_ANDA>`

### Endpoint

Base URL: `https://yourdomain.com/`

#### 1. Membuat Snippet Baru

* **Endpoint**: `POST /users.php`
* **Deskripsi**: Membuat dan menyimpan *snippet* baru.
* **Body (JSON)**:
    ```json
    {
      "title": "Judul Snippet",
      "code_content": "Isi kode Anda di sini...",
      "language": "javascript"
    }
    ```
* **Contoh cURL**:
    ```bash
    curl -X POST [https://yourdomain.com/users.php](https://yourdomain.com/users.php) \
    -H "Authorization: Bearer <API_KEY_ANDA>" \
    -H "Content-Type: application/json" \
    -d '{"title": "Test API", "code_content": "console.log(\"Hello API\");", "language": "javascript"}'
    ```

#### 2. Mengedit Snippet

* **Endpoint**: `PUT /users.php?share_id={snippet_id}`
* **Deskripsi**: Memperbarui *snippet* yang sudah ada.
* **Body (JSON)**:
    ```json
    {
      "title": "Judul Baru",
      "code_content": "Kode yang sudah diperbarui...",
      "language": "python"
    }
    ```

#### 3. Menghapus Snippet

* **Endpoint**: `DELETE /users.php?share_id={snippet_id}`
* **Deskripsi**: Menghapus *snippet* milik pengguna.

#### 4. Mendapatkan Daftar Snippet

* **Endpoint**: `GET /users.php`
* **Deskripsi**: Mengambil daftar semua *snippet* yang dimiliki oleh pengguna (berdasarkan API Key).

## Kontribusi

Kontribusi dalam bentuk apapun sangat diterima. Jika Anda menemukan bug atau memiliki ide fitur baru, silakan buka *issue* di repositori GitHub.

## Author

Proyek ini dibuat dan dikelola oleh **DGXO**.
