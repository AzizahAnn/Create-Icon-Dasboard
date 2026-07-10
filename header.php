<?php
session_start();

if (empty($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"] ?? "Admin";

// $page_title, $page_icon, $page_icon_img diisi dari file yang include ini
$page_title = $page_title ?? "Halaman";
$page_icon = $page_icon ?? "fa-file";       // fallback: font awesome (kalau tidak ada gambar)
$page_icon_img = $page_icon_img ?? null;     // opsional: path ke file gambar icon
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> - Arsip Digital</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    margin:0;
    font-family: 'Segoe UI', sans-serif;
    background:#0f172a;
    color:#fff;
}

/* MAIN */
.main {
    margin-left:260px;
    padding:20px;
}

/* TOPBAR */
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#1e293b;
    padding:15px;
    border-radius:10px;
    gap:15px;
    flex-wrap:wrap;
}

.topbar-left {
    display:flex;
    align-items:center;
    gap:14px;
}

.back-link {
    color:#94a3b8;
    text-decoration:none;
    font-size:14px;
    display:flex;
    align-items:center;
    gap:6px;
    padding:8px 12px;
    border-radius:8px;
    transition:0.2s;
}
.back-link:hover {
    background:#0f172a;
    color:#38bdf8;
}

.page-icon-title {
    display:flex;
    align-items:center;
    gap:10px;
}
.page-icon-title img {
    width:28px;
    height:28px;
    object-fit:contain;
}
.page-icon-title h2 {
    margin:0;
    font-size:19px;
}

.topbar-right {
    display:flex;
    align-items:center;
    gap:12px;
}

.user-info {
    color:#cbd5e1;
    font-size:14px;
}

.btn-logout {
    background:#ef4444;
    color:#fff;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:10px 15px;
    border-radius:10px;
}
.btn-logout:hover { background:#dc2626; }

/* PANEL (kontainer form/konten) */
.panel {
    background:#1e293b;
    padding:20px;
    border-radius:15px;
    margin-bottom:20px;
}

.panel label {
    display:block;
    margin-top:12px;
    margin-bottom:6px;
    color:#94a3b8;
    font-size:14px;
    font-weight:600;
}

.panel label:first-child {
    margin-top:0;
}

/* FORM ELEMENTS */
textarea,
select,
input[type="text"],
input[type="file"] {
    width:100%;
    max-width:600px;
    box-sizing:border-box;
    padding:10px;
    border-radius:8px;
    border:1px solid #334155;
    background:#0f172a;
    color:#fff;
    font-family:inherit;
    font-size:14px;
}

textarea:focus,
select:focus,
input[type="text"]:focus {
    outline:none;
    border-color:#38bdf8;
}

textarea {
    resize:vertical;
}

/* BUTTON AKSI */
.btn-action {
    margin-top:15px;
    padding:10px 18px;
    border:none;
    border-radius:10px;
    background:#38bdf8;
    color:#000;
    font-weight:600;
    cursor:pointer;
    font-size:14px;
}
.btn-action:hover {
    background:#0ea5e9;
}

/* HASIL TERJEMAHAN / RESULT BOX */
.result-box {
    background:#0f172a;
    border:1px solid #334155;
    border-radius:8px;
    padding:12px;
    color:#e2e8f0;
    white-space:pre-wrap;
    word-break:break-word;
}

/* TABLE RIWAYAT */
table {
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}
table th, table td {
    padding:10px;
    text-align:left;
    border-bottom:1px solid #334155;
    font-size:14px;
}
table th {
    color:#38bdf8;
}

.note {
    color:#94a3b8;
    font-size:13px;
}
</style>
</head>
<body>

<?php include "sidebar.php"; ?>

<div class="main">

    <!-- TOPBAR HALAMAN -->
    <div class="topbar">
        <div class="topbar-left">
            <a href="admin.php" class="back-link">
                <i class="fa fa-arrow-left"></i>
            </a>

            <div class="page-icon-title">
                <?php if ($page_icon_img): ?>
                    <img src="<?= htmlspecialchars($page_icon_img) ?>" alt="">
                <?php else: ?>
                    <i class="fa <?= htmlspecialchars($page_icon) ?>" style="font-size:22px; color:#38bdf8;"></i>
                <?php endif; ?>
                <h2><?= htmlspecialchars($page_title) ?></h2>
            </div>
        </div>

        <div class="topbar-right">
            <span class="user-info"><i class="fa fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
            <a href="logout.php" class="btn-logout"><i class="fa fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div style="margin-top:20px;">