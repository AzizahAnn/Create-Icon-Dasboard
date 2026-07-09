<?php
$page_title = "Analisis Data";
$page_icon_img = "icon/analisis-data.png";
include "header.php";

require "config.php";

// 1. Jumlah arsip per kategori
$perKategori = $pdo->query("
    SELECT kategori.nama_kategori, COUNT(arsip.id) AS jumlah
    FROM kategori
    LEFT JOIN arsip ON arsip.kategori_id = kategori.id
    GROUP BY kategori.id, kategori.nama_kategori
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Jumlah arsip per bulan (6 bulan terakhir)
$perBulan = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS bulan, COUNT(*) AS jumlah
    FROM arsip
    WHERE created_at >= NOW() - INTERVAL 6 MONTH
    GROUP BY bulan
    ORDER BY bulan ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Jumlah arsip per status
$perStatus = $pdo->query("
    SELECT status, COUNT(*) AS jumlah
    FROM arsip
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Jumlah arsip per sifat (kerahasiaan)
$perSifat = $pdo->query("
    SELECT sifat, COUNT(*) AS jumlah
    FROM arsip
    GROUP BY sifat
")->fetchAll(PDO::FETCH_ASSOC);

// Siapkan data buat Chart.js (dikirim sebagai JSON ke JavaScript)
$labelKategori = array_column($perKategori, "nama_kategori");
$dataKategori = array_column($perKategori, "jumlah");

$labelBulan = array_column($perBulan, "bulan");
$dataBulan = array_column($perBulan, "jumlah");

$labelStatus = array_column($perStatus, "status");
$dataStatus = array_column($perStatus, "jumlah");

$labelSifat = array_column($perSifat, "sifat");
$dataSifat = array_column($perSifat, "jumlah");
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="grid" style="grid-template-columns:1fr 1fr;">

    <div class="box">
        <h3>Arsip per Kategori</h3>
        <canvas id="chartKategori"></canvas>
    </div>

    <div class="box">
        <h3>Tren Arsip Masuk (6 Bulan Terakhir)</h3>
        <canvas id="chartBulan"></canvas>
    </div>

    <div class="box">
        <h3>Distribusi Status</h3>
        <canvas id="chartStatus"></canvas>
    </div>

    <div class="box">
        <h3>Distribusi Sifat/Kerahasiaan</h3>
        <canvas id="chartSifat"></canvas>
    </div>

</div>

<script>
// Data dari PHP dikirim ke JS lewat json_encode
const labelKategori = <?= json_encode($labelKategori) ?>;
const dataKategori = <?= json_encode($dataKategori) ?>;

const labelBulan = <?= json_encode($labelBulan) ?>;
const dataBulan = <?= json_encode($dataBulan) ?>;

const labelStatus = <?= json_encode($labelStatus) ?>;
const dataStatus = <?= json_encode($dataStatus) ?>;

const labelSifat = <?= json_encode($labelSifat) ?>;
const dataSifat = <?= json_encode($dataSifat) ?>;

const opsiWarnaTeks = { plugins: { legend: { labels: { color: '#fff' } } }, scales: { x: { ticks: { color: '#fff' } }, y: { ticks: { color: '#fff' } } } };

new Chart(document.getElementById('chartKategori'), {
    type: 'bar',
    data: {
        labels: labelKategori,
        datasets: [{ label: 'Jumlah Arsip', data: dataKategori, backgroundColor: '#38bdf8' }]
    },
    options: opsiWarnaTeks
});

new Chart(document.getElementById('chartBulan'), {
    type: 'line',
    data: {
        labels: labelBulan,
        datasets: [{ label: 'Arsip Masuk', data: dataBulan, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.2)', fill: true, tension: 0.4 }]
    },
    options: opsiWarnaTeks
});

new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: labelStatus,
        datasets: [{ data: dataStatus, backgroundColor: ['#38bdf8', '#a855f7', '#ef4444'] }]
    },
    options: { plugins: { legend: { labels: { color: '#fff' } } } }
});

new Chart(document.getElementById('chartSifat'), {
    type: 'doughnut',
    data: {
        labels: labelSifat,
        datasets: [{ data: dataSifat, backgroundColor: ['#334155', '#f59e0b', '#dc2626'] }]
    },
    options: { plugins: { legend: { labels: { color: '#fff' } } } }
});
</script>

<?php include "footer.php"; ?>