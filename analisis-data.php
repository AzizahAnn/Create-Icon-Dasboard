<?php
$page_title = "Analisis Data";
$page_icon_img = "icon/analisis-data.png";
include "header.php";

// Data simulasi -- nanti diganti hasil query database
$per_kategori = ["SK" => 320, "Laporan" => 280, "Surat" => 410, "Notulen" => 150, "Kontrak" => 120];
$per_bulan = ["Jan" => 120, "Feb" => 190, "Mar" => 300, "Apr" => 250, "Mei" => 400, "Jun" => 500];
?>

<div class="panel">
    <label>Distribusi Arsip per Kategori</label>
    <canvas id="chartKategori"></canvas>
</div>

<div class="panel">
    <label>Tren Arsip Masuk per Bulan</label>
    <canvas id="chartBulan"></canvas>
</div>

<p class="note">Data di atas masih simulasi. Setelah database siap, grafik ini akan otomatis mengambil data arsip yang sebenarnya.</p>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('chartKategori'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($per_kategori)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($per_kategori)) ?>,
            backgroundColor: ['#38bdf8','#22c55e','#a855f7','#f59e0b','#ef4444']
        }]
    },
    options: {
        plugins: { legend: { labels: { color: '#fff' } } }
    }
});

new Chart(document.getElementById('chartBulan'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($per_bulan)) ?>,
        datasets: [{
            label: 'Arsip Masuk',
            data: <?= json_encode(array_values($per_bulan)) ?>,
            backgroundColor: '#38bdf8'
        }]
    },
    options: {
        plugins: { legend: { labels: { color: '#fff' } } },
        scales: {
            x: { ticks: { color: '#fff' } },
            y: { ticks: { color: '#fff' } }
        }
    }
});
</script>

<?php include "footer.php"; ?>
