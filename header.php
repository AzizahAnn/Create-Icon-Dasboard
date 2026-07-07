<?php
session_start();

// Cek apakah sudah login. Kalau belum, lempar ke halaman login.
if (empty($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"] ?? "Admin";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? htmlspecialchars($page_title) . " - " : "" ?>Arsip Digital</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body {
    margin:0;
    font-family: 'Segoe UI', sans-serif;
    background:#0f172a;
    color:#fff;
}

/* SIDEBAR */
.sidebar {
    position:fixed;
    width:240px;
    height:100vh;
    background:#111827;
    padding:20px;
    overflow-y:auto;
    scrollbar-width:thin;
    scrollbar-color:#334155 #111827;
}
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-track { background:#111827; }
.sidebar::-webkit-scrollbar-thumb { background:#334155; border-radius:10px; }
.sidebar h2 { color:#38bdf8; }
.sidebar a {
    display:block;
    padding:12px;
    color:#cbd5e1;
    text-decoration:none;
    margin-top:5px;
    border-radius:8px;
}
.sidebar a:hover { background:#1e293b; }

.sidebar .has-submenu {
    display:flex;
    justify-content:space-between;
    align-items:center;
    cursor:pointer;
    padding:12px;
    margin-top:5px;
    border-radius:8px;
    color:#cbd5e1;
}
.sidebar .has-submenu:hover { background:#1e293b; }
.sidebar .has-submenu i.chevron { transition:0.3s; font-size:12px; }
.sidebar .has-submenu.open i.chevron { transform:rotate(180deg); }

.submenu {
    max-height:0;
    overflow:hidden;
    transition:max-height 0.3s ease;
    margin-left:10px;
    border-left:2px solid #1e293b;
}
.submenu.open { max-height:500px; }
.submenu a { padding:10px 12px; font-size:13px; color:#94a3b8; }
.submenu a:hover { background:#1e293b; color:#38bdf8; }
.submenu a i { width:18px; color:#38bdf8; margin-right:6px; }
.submenu a img.submenu-icon {
    width:16px; height:16px; object-fit:contain;
    margin-right:6px; vertical-align:middle;
}

/* MAIN */
.main { margin-left:260px; padding:20px; }

/* TOPBAR */
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#1e293b;
    padding:15px;
    border-radius:10px;
}
.search { padding:10px; width:300px; border-radius:8px; border:none; }

.user-info { color:#cbd5e1; margin-right:15px; font-size:14px; }

/* BUTTON */
.btn { padding:10px 15px; border:none; border-radius:10px; cursor:pointer; margin-right:10px; }
.btn-blue { background:#38bdf8; color:#000; }
.btn-green { background:#22c55e; color:#000; }
.btn-purple { background:#a855f7; color:#fff; }
.btn-logout { background:#ef4444; color:#fff; text-decoration:none; display:inline-block; }
.btn-logout:hover { background:#dc2626; }

/* CARDS (dipakai di dashboard) */
.cards { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-top:20px; }
.card {
    background:#1e293b; padding:20px; border-radius:15px;
    min-height:110px; display:flex; flex-direction:column; justify-content:center;
}
.card h3 { margin:0; color:#38bdf8; }

/* TOOLS GRID (dipakai di dashboard) */
.tools-title { margin-top:20px; margin-bottom:10px; color:#fff; }
.tools-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:15px; }
.tool-card {
    background:#1e293b; padding:20px; border-radius:15px; cursor:pointer;
    border:none; text-align:center; color:#fff; font-family:inherit;
    text-decoration:none; display:flex; flex-direction:column;
    align-items:center; justify-content:center; min-height:110px;
}
.tool-card i { font-size:28px; color:#38bdf8; margin-bottom:10px; display:block; }
.tool-card img.tool-icon { width:56px; height:56px; object-fit:contain; margin-bottom:12px; display:block; }
.tool-card span { font-size:16px; color:#e2e8f0; }
.tool-card:hover { transform:translateY(-5px); transition:0.3s; background:#243147; }

/* GRID & BOX (dipakai di dashboard) */
.grid { display:grid; grid-template-columns:2fr 1fr; gap:15px; margin-top:20px; }
.box { background:#1e293b; padding:20px; border-radius:15px; }
.chart-box { background:#1e293b; margin-top:20px; padding:20px; border-radius:15px; }

/* PANEL (dipakai di halaman-halaman tools seperti gambar-ke-pdf.php) */
.panel {
    background:#1e293b;
    padding:25px;
    border-radius:15px;
    margin-top:20px;
}
.panel label {
    display:block;
    margin-bottom:12px;
    color:#94a3b8;
}
.btn-action {
    background:#38bdf8;
    color:#000;
    border:none;
    padding:10px 18px;
    border-radius:10px;
    cursor:pointer;
    margin-left:10px;
    font-weight:600;
}
.btn-action:hover { background:#0ea5e9; }
.note { margin-top:15px; color:#94a3b8; font-size:14px; }

/* ANIMATION */
.card:hover, .box:hover { transform:translateY(-5px); transition:0.3s; }
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2><i class="fa fa-database"></i> ARSIP PRO</h2>
    <a href="admin.php"><i class="fa fa-home"></i> Dashboard</a>

    <div class="has-submenu" id="dataArsipToggle">
        <span><i class="fa fa-folder"></i> Data Arsip</span>
        <i class="fa fa-chevron-down chevron"></i>
    </div>
    <div class="submenu open" id="dataArsipSubmenu">
        <a href="scan-dokumen.php"><img class="submenu-icon" src="icon/scan-dokumen.png" alt="Scan Dokumen"> Scan Dokumen</a>
        <a href="scan-gambar.php"><img class="submenu-icon" src="icon/scan-gambar.png" alt="Scan Gambar"> Scan Gambar</a>
        <a href="e-notulen.php"><img class="submenu-icon" src="icon/e-notulen.png" alt="E-Notulen"> E-Notulen</a>
        <a href="gambar-ke-pdf.php"><img class="submenu-icon" src="icon/impor-gambar-ke-pdf.png" alt="Gambar ke PDF"> Gambar ke PDF</a>
        <a href="lihat-data.php"><img class="submenu-icon" src="icon/lihat-data.png" alt="Lihat Data"> Lihat Data</a>
        <a href="ringkas-catatan.php"><img class="submenu-icon" src="icon/peringkas-catatan.png" alt="Peringkas Catatan"> Peringkas Catatan</a>
        <a href="konversi-bahasa.php"><img class="submenu-icon" src="icon/konversi-bahasa.png" alt="Konversi Bahasa"> Konversi Bahasa</a>
        <a href="stempel-waktu.php"><img class="submenu-icon" src="icon/stempel-waktu.png" alt="Stempel Waktu"> Stempel Waktu</a>
        <a href="impor-gambar.php"><img class="submenu-icon" src="icon/impor-gambar.png" alt="Impor Gambar"> Impor Gambar</a>
        <a href="impor-file.php"><img class="submenu-icon" src="icon/impor-dokumen.png" alt="Impor File"> Impor File</a>
        <a href="jernihkan-gambar.php"><img class="submenu-icon" src="icon/jernihkan-gambar.png" alt="Jernihkan Gambar"> Jernihkan Gambar</a>
        <a href="analisis-data.php"><img class="submenu-icon" src="icon/analisis-data.png" alt="Analisis Data"> Analisis Data</a>
    </div>

    <a href="#"><i class="fa fa-upload"></i> Upload Otomatis</a>
    <a href="#"><i class="fa fa-chart-line"></i> Analitik</a>
    <a href="#"><i class="fa fa-gear"></i> Setting</a>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <input class="search" placeholder="Cari arsip, dokumen, kategori...">
        <div>
            <span class="user-info"><i class="fa fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
            <a href="logout.php" class="btn btn-logout"><i class="fa fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <?php if (isset($page_title)): ?>
    <h2 style="margin-top:20px;">
        <?php if (isset($page_icon)): ?><i class="fa <?= htmlspecialchars($page_icon) ?>"></i><?php endif; ?>
        <?= htmlspecialchars($page_title) ?>
    </h2>
    <?php endif; ?>