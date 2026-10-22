<?php
/* ============================================================
   LOGOUT
   Fitur: hapus seluruh session (data login) lalu ke halaman login.
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

session_unset();     // kosongkan semua data session
session_destroy();   // hapus session di server

header('Location: ' . BASE_URL . 'auth/login.php');
exit;
