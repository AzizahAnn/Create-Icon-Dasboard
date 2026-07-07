<?php
session_start();
if (empty($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}
$username = $_SESSION["username"] ?? "Admin";

// $page_title dan $page_icon diisi oleh file yang meng-include ini
$page_title = $page_title ?? "Tools";
$page_icon  = $page_icon  ?? "fa-gear";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> - Arsip Digital</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
* { box-sizing:border-box; }
body {
    margin:0;
    font-family:'Segoe UI', sans-serif;
    background:#0f172a;
    color:#fff;
}
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#1e293b;
    padding:15px 25px;
}
.topbar .brand {
    color:#38bdf8;
    font-weight:bold;
    font-size:18px;
}
.topbar .back-link {
    color:#cbd5e1;
    text-decoration:none;
    margin-right:20px;
}
.topbar .back-link:hover { color:#38bdf8; }
.topbar .right { display:flex; align-items:center; gap:15px; }
.user-info { color:#cbd5e1; font-size:14px; }
.btn-logout {
    background:#ef4444;
    color:#fff;
    text-decoration:none;
    padding:8px 14px;
    border-radius:8px;
}
.btn-logout:hover { background:#dc2626; }

.container {
    max-width:900px;
    margin:30px auto;
    padding:0 20px;
}

.page-header {
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:20px;
}
.page-header i {
    font-size:28px;
    color:#38bdf8;
}
.page-header h1 {
    font-size:22px;
    margin:0;
}

.panel {
    background:#1e293b;
    border-radius:15px;
    padding:25px;
    margin-bottom:20px;
}

label {
    display:block;
    margin-bottom:6px;
    color:#cbd5e1;
    font-size:14px;
}

input[type=text], input[type=file], textarea, select {
    width:100%;
    padding:10px;
    border-radius:8px;
    border:1px solid #334155;
    background:#0f172a;
    color:#fff;
    font-size:14px;
    margin-bottom:15px;
    font-family:inherit;
}

textarea { resize:vertical; min-height:120px; }

button.btn-action {
    padding:10px 18px;
    border:none;
    border-radius:8px;
    background:#38bdf8;
    color:#000;
    font-weight:bold;
    cursor:pointer;
    font-size:14px;
}
button.btn-action:hover { background:#0ea5e9; }

.result-box {
    background:#0f172a;
    border:1px solid #334155;
    border-radius:10px;
    padding:15px;
    margin-top:15px;
    white-space:pre-wrap;
    font-size:14px;
    color:#e2e8f0;
}

.note {
    font-size:12px;
    color:#64748b;
    margin-top:10px;
}

table {
    width:100%;
    border-collapse:collapse;
}
table th, table td {
    text-align:left;
    padding:10px;
    border-bottom:1px solid #334155;
    font-size:14px;
}
table th { color:#38bdf8; }

.file-list img {
    max-width:120px;
    border-radius:8px;
    margin:5px;
}
</style>
</head>
<body>

<div class="topbar">
    <div style="display:flex; align-items:center;">
        <a href="admin.php" class="back-link"><i class="fa fa-arrow-left"></i> Kembali</a>
        <span class="brand"><i class="fa fa-database"></i> ARSIP PRO</span>
    </div>
    <div class="right">
        <span class="user-info"><i class="fa fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
        <a href="logout.php" class="btn-logout"><i class="fa fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<div class="container">
    <div class="page-header">
        <i class="fa <?= htmlspecialchars($page_icon) ?>"></i>
        <h1><?= htmlspecialchars($page_title) ?></h1>
    </div>
