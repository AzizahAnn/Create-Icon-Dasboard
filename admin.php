<?php
session_start();

// Cek apakah sudah login. Kalau belum, lempar ke halaman login.
if (empty($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"] ?? "Admin";

require "config.php";

// Ambil data statistik asli dari database
$total_arsip = $pdo->query("SELECT COUNT(*) FROM arsip")->fetchColumn();
$total_kategori = $pdo->query("SELECT COUNT(*) FROM kategori")->fetchColumn();
$total_user = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$arsip_baru = $pdo->query("SELECT COUNT(*) FROM arsip WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Arsip Digital</title>

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
.sidebar::-webkit-scrollbar {
    width:6px;
}
.sidebar::-webkit-scrollbar-track {
    background:#111827;
}
.sidebar::-webkit-scrollbar-thumb {
    background:#334155;
    border-radius:10px;
}
.sidebar h2 {
    color:#38bdf8;
}
.sidebar a {
    display:block;
    padding:12px;
    color:#cbd5e1;
    text-decoration:none;
    margin-top:5px;
    border-radius:8px;
}
.sidebar a:hover {
    background:#1e293b;
}

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

.sidebar .has-submenu:hover {
    background:#1e293b;
}

.sidebar .has-submenu i.chevron {
    transition:0.3s;
    font-size:12px;
}

.sidebar .has-submenu.open i.chevron {
    transform:rotate(180deg);
}

.submenu {
    max-height:0;
    overflow:hidden;
    transition:max-height 0.3s ease;
    margin-left:10px;
    border-left:2px solid #1e293b;
}

.submenu.open {
    max-height:500px;
}

.submenu a {
    padding:10px 12px;
    font-size:13px;
    color:#94a3b8;
}

.submenu a:hover {
    background:#1e293b;
    color:#38bdf8;
}

.submenu a i {
    width:18px;
    color:#38bdf8;
    margin-right:6px;
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
}

.search {
    padding:10px;
    width:300px;
    border-radius:8px;
    border:none;
}

/* CARDS */
.cards {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-top:20px;
}

.card {
    background:#1e293b;
    padding:20px;
    border-radius:15px;
    min-height:110px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.card h3 {
    margin:0;
    color:#38bdf8;
}

/* BUTTON */
.btn {
    padding:10px 15px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    margin-right:10px;
}
.btn-blue { background:#38bdf8; color:#000; }
.btn-green { background:#22c55e; color:#000; }
.btn-purple { background:#a855f7; color:#fff; }
.btn-logout {
    background:#ef4444;
    color:#fff;
    text-decoration:none;
    display:inline-block;
}
.btn-logout:hover { background:#dc2626; }

.user-info {
    color:#cbd5e1;
    margin-right:15px;
    font-size:14px;
}

/* CANVAS */
.chart-box {
    background:#1e293b;
    margin-top:20px;
    padding:20px;
    border-radius:15px;
}

/* TOOLS GRID */
.tools-title {
    margin-top:20px;
    margin-bottom:10px;
    color:#fff;
}

.tools-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
}

.tool-card {
    background:#1e293b;
    padding:20px;
    border-radius:15px;
    cursor:pointer;
    border:none;
    text-align:center;
    color:#fff;
    font-family:inherit;
    text-decoration:none;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    min-height:110px;
}

.tool-card i {
    font-size:28px;
    color:#38bdf8;
    margin-bottom:10px;
    display:block;
}

.tool-card img.tool-icon {
    width:56px;
    height:56px;
    object-fit:contain;
    margin-bottom:12px;
    display:block;
}

.submenu a img.submenu-icon {
    width:16px;
    height:16px;
    object-fit:contain;
    margin-right:6px;
    vertical-align:middle;
}

.tool-card span {
    font-size:16px;
    color:#e2e8f0;
}

.tool-card:hover {
    transform:translateY(-5px);
    transition:0.3s;
    background:#243147;
}

/* GRID */
.grid {
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:15px;
    margin-top:20px;
}

.box {
    background:#1e293b;
    padding:20px;
    border-radius:15px;
}

/* ANIMATION */
.card:hover, .box:hover {
    transform:translateY(-5px);
    transition:0.3s;
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2><i class="fa fa-database"></i> DOKPIN 5.0</h2>
    <a href="#"><i class="fa fa-home"></i> Dashboard</a>

    <div class="has-submenu" id="dataArsipToggle">
        <span><i class="fa fa-folder"></i> Data Arsip</span>
        <i class="fa fa-chevron-down chevron"></i>
    </div>
    <div class="submenu" id="dataArsipSubmenu">
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
            <button class="btn btn-blue"><i class="fa fa-plus"></i> Upload</button>
            <button class="btn btn-green"><i class="fa fa-magic"></i> Auto Arsip</button>
            <button class="btn btn-purple"><i class="fa fa-bolt"></i> Scan AI</button>
            <span class="user-info"><i class="fa fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
            <a href="logout.php" class="btn btn-logout"><i class="fa fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <!-- CARDS -->
    <div class="cards">
        <div class="card">
            <h3><?= $total_arsip ?></h3>
            <p>Total Arsip</p>
        </div>
        <div class="card">
            <h3><?= $total_kategori ?></h3>
            <p>Kategori</p>
        </div>
        <div class="card">
            <h3><?= $total_user ?></h3>
            <p>User Aktif</p>
        </div>
        <div class="card">
            <h3><?= $arsip_baru ?></h3>
            <p>Arsip Baru</p>
        </div>
    </div>

    <!-- TOOLS GRID -->
    <h3 class="tools-title">Menu Tools</h3>
    <div class="tools-grid">
        <a href="scan-dokumen.php" class="tool-card"><img class="tool-icon" src="icon/scan-dokumen.png" alt="Scan Dokumen"><span>Scan Dokumen (Gambar ke Teks)</span></a>
        <a href="scan-gambar.php" class="tool-card"><img class="tool-icon" src="icon/scan-gambar.png" alt="Scan Gambar"><span>Scan Gambar</span></a>
        <a href="e-notulen.php" class="tool-card"><img class="tool-icon" src="icon/e-notulen.png" alt="E-Notulen"><span>E-Notulen (Voice to Teks)</span></a>
        <a href="gambar-ke-pdf.php" class="tool-card"><img class="tool-icon" src="icon/impor-gambar-ke-pdf.png" alt="Gambar ke PDF"><span>Ubah Gambar ke PDF</span></a>
        <a href="lihat-data.php" class="tool-card"><img class="tool-icon" src="icon/lihat-data.png" alt="Lihat Data"><span>Lihat Data</span></a>
        <a href="ringkas-catatan.php" class="tool-card"><img class="tool-icon" src="icon/peringkas-catatan.png" alt="Peringkas Catatan"><span>Peringkas Catatan</span></a>
        <a href="konversi-bahasa.php" class="tool-card"><img class="tool-icon" src="icon/konversi-bahasa.png" alt="Konversi Bahasa"><span>Konversi Bahasa</span></a>
        <a href="stempel-waktu.php" class="tool-card"><img class="tool-icon" src="icon/stempel-waktu.png" alt="Stempel Waktu"><span>Stempel Waktu</span></a>
        <a href="impor-gambar.php" class="tool-card"><img class="tool-icon" src="icon/impor-gambar.png" alt="Impor Gambar"><span>Impor Gambar</span></a>
        <a href="impor-file.php" class="tool-card"><img class="tool-icon" src="icon/impor-dokumen.png" alt="Impor File"><span>Impor File</span></a>
        <a href="jernihkan-gambar.php" class="tool-card"><img class="tool-icon" src="icon/jernihkan-gambar.png" alt="Jernihkan Gambar"><span>Jernihkan Gambar</span></a>
        <a href="analisis-data.php" class="tool-card"><img class="tool-icon" src="icon/analisis-data.png" alt="Analisis Data"><span>Analisis Data</span></a>
    </div>

    <!-- CHART -->
    <div class="chart-box">
        <h3>Statistik Arsip</h3>
        <canvas id="chartArsip"></canvas>
    </div>

    <!-- GRID -->
    <div class="grid">

        <div class="box">
            <h3>Aktivitas Terbaru</h3>
            <p>📁 Dokumen SK ditambahkan</p>
            <p>📄 PDF laporan diupload</p>
            <p>🧠 AI mengklasifikasi arsip</p>
            <p>📊 Backup database otomatis</p>
        </div>

        <div class="box">
            <h3>Quick Tools</h3>
            <button class="btn btn-blue">Scan Dokumen</button><br><br>
            <button class="btn btn-green">Auto Klasifikasi</button><br><br>
            <button class="btn btn-purple">Generate Laporan</button>
        </div>

    </div>

</div>

<script>
document.getElementById('dataArsipToggle').addEventListener('click', function() {
    this.classList.toggle('open');
    document.getElementById('dataArsipSubmenu').classList.toggle('open');
});
</script>

<script>
// CHART CANVAS MODERN
const ctx = document.getElementById('chartArsip');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Jan','Feb','Mar','Apr','Mei','Jun'],
        datasets: [{
            label: 'Arsip Masuk',
            data: [120, 190, 300, 250, 400, 500],
            borderColor: '#38bdf8',
            backgroundColor: 'rgba(56,189,248,0.2)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        plugins: {
            legend: { labels: { color: '#fff' } }
        },
        scales: {
            x: { ticks: { color: '#fff' } },
            y: { ticks: { color: '#fff' } }
        }
    }
});
</script>

</body>
</html>