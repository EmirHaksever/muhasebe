<?php
session_start();
include_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: /../auth/login.php");
    exit;
}

date_default_timezone_set('Europe/Istanbul');
