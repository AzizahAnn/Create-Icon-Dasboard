<?php
// simulasi data arsip
$total_arsip = 1280;
$total_kategori = 24;
$total_user = 56;
$arsip_baru = 18;
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

/* CANVAS */
.chart-box {
    background:#1e293b;
    margin-top:20px;
    padding:20px;
    border-radius:15px;
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
    <h2><i class="fa fa-database"></i> ARSIP PRO</h2>
    <a href="#"><i class="fa fa-home"></i> Dashboard</a>
    <a href="#"><i class="fa fa-folder"></i> Data Arsip</a>
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