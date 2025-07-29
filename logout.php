<?php
// Selalu mulai session di awal
session_start();

// Hapus semua variabel di dalam session
$_SESSION = array();

// Hancurkan session-nya
session_destroy();

// Arahkan pengguna kembali ke halaman utama (index.php), bukan ke login.php
header("location: index.php");
exit;
?>