<?php
// Nama file halaman yang lagi dibuka, dipakai buat nandain menu aktif
$halaman_sekarang = basename($_SERVER["PHP_SELF"]);

// Daftar halaman yang termasuk submenu "Data Arsip"
$menu_arsip = [
    "scan-dokumen.php", "scan-gambar.php", "e-notulen.php", "gambar-ke-pdf.php",
    "lihat-data.php", "ringkas-catatan.php", "konversi-bahasa.php", "stempel-waktu.php",
    "impor-gambar.php", "impor-file.php", "jernihkan-gambar.php", "analisis-data.php"
];

$submenu_terbuka = in_array($halaman_sekarang, $menu_arsip);

// Fungsi bantu buat nge-print class "active" kalau halaman ini yang lagi dibuka
if (!function_exists('kelas_aktif')) {
    function kelas_aktif($nama_file, $halaman_sekarang) {
        return $nama_file === $halaman_sekarang ? "active" : "";
    }
}
?>

<style>
/* ===== LAYOUT DASAR (dipakai semua halaman) ===== */
* {
    box-sizing: border-box;
}

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
.sidebar a.active {
    background:#38bdf8;
    color:#0f172a;
    font-weight:bold;
}
.sidebar a.active:hover {
    background:#38bdf8;
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
    max-height:280px;
    overflow-y:auto;
}

.submenu::-webkit-scrollbar {
    width:5px;
}
.submenu::-webkit-scrollbar-track {
    background:transparent;
}
.submenu::-webkit-scrollbar-thumb {
    background:#334155;
    border-radius:10px;
}
.submenu {
    scrollbar-width:thin;
    scrollbar-color:#334155 transparent;
}

.submenu a {
    padding:10px 12px;
    font-size:13px;
    color:#94a3b8;
    direction:ltr;
}

.submenu a:hover {
    background:#1e293b;
    color:#38bdf8;
}

.submenu a.active {
    background:#1e293b;
    color:#38bdf8;
    font-weight:bold;
}

.submenu a i {
    width:18px;
    color:#38bdf8;
    margin-right:6px;
}

.submenu a img.submenu-icon {
    width:16px;
    height:16px;
    object-fit:contain;
    margin-right:6px;
    vertical-align:middle;
}

/* AREA KONTEN UTAMA - WAJIB SAMA DI SEMUA HALAMAN */
.main {
    margin-left:260px;
    padding:20px;
    min-height:100vh;
}

/* TOPBAR (opsional, kalau halaman lain juga pakai) */
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

.user-info {
    color:#cbd5e1;
    margin-right:15px;
    font-size:14px;
}

/* BUTTON UMUM */
.btn, .btn-action {
    padding:10px 15px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    margin-right:10px;
    font-size:14px;
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

.btn-action {
    background:#38bdf8;
    color:#000;
    font-weight:bold;
}
.btn-action:hover { opacity:0.9; }

/* PANEL (dipakai halaman tools seperti scan-gambar.php) */
.panel {
    background:#1e293b;
    padding:20px;
    border-radius:15px;
    margin-bottom:20px;
}
.panel label {
    display:block;
    font-weight:bold;
    margin-bottom:10px;
    color:#e2e8f0;
}
.panel .note {
    color:#94a3b8;
    font-size:13px;
    margin-top:10px;
}
</style>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2><i class="fa fa-database"></i> DOKPIN 5.0</h2>

    <a href="admin.php" class="<?= kelas_aktif('admin.php', $halaman_sekarang) ?>">
        <i class="fa fa-home"></i> Dashboard
    </a>

    <div class="has-submenu <?= $submenu_terbuka ? 'open' : '' ?>" id="dataArsipToggle">
        <span><i class="fa fa-folder"></i> Data Arsip</span>
        <i class="fa fa-chevron-down chevron"></i>
    </div>
    <div class="submenu <?= $submenu_terbuka ? 'open' : '' ?>" id="dataArsipSubmenu">
        <a href="scan-dokumen.php" class="<?= kelas_aktif('scan-dokumen.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/scan-dokumen.png" alt="Scan Dokumen"> Scan Dokumen
        </a>
        <a href="scan-gambar.php" class="<?= kelas_aktif('scan-gambar.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/scan-gambar.png" alt="Scan Gambar"> Scan Gambar
        </a>
        <a href="e-notulen.php" class="<?= kelas_aktif('e-notulen.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/e-notulen.png" alt="E-Notulen"> E-Notulen
        </a>
        <a href="gambar-ke-pdf.php" class="<?= kelas_aktif('gambar-ke-pdf.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/impor-gambar-ke-pdf.png" alt="Gambar ke PDF"> Gambar ke PDF
        </a>
        <a href="lihat-data.php" class="<?= kelas_aktif('lihat-data.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/lihat-data.png" alt="Lihat Data"> Lihat Data
        </a>
        <a href="ringkas-catatan.php" class="<?= kelas_aktif('ringkas-catatan.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/peringkas-catatan.png" alt="Peringkas Catatan"> Peringkas Catatan
        </a>
        <a href="konversi-bahasa.php" class="<?= kelas_aktif('konversi-bahasa.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/konversi-bahasa.png" alt="Konversi Bahasa"> Konversi Bahasa
        </a>
        <a href="stempel-waktu.php" class="<?= kelas_aktif('stempel-waktu.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/stempel-waktu.png" alt="Stempel Waktu"> Stempel Waktu
        </a>
        <a href="impor-gambar.php" class="<?= kelas_aktif('impor-gambar.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/impor-gambar.png" alt="Impor Gambar"> Impor Gambar
        </a>
        <a href="impor-file.php" class="<?= kelas_aktif('impor-file.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/impor-dokumen.png" alt="Impor File"> Impor File
        </a>
        <a href="jernihkan-gambar.php" class="<?= kelas_aktif('jernihkan-gambar.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/jernihkan-gambar.png" alt="Jernihkan Gambar"> Jernihkan Gambar
        </a>
        <a href="analisis-data.php" class="<?= kelas_aktif('analisis-data.php', $halaman_sekarang) ?>">
            <img class="submenu-icon" src="icon/analisis-data.png" alt="Analisis Data"> Analisis Data
        </a>
    </div>

    <a href="#"><i class="fa fa-upload"></i> Upload Otomatis</a>
    <a href="#"><i class="fa fa-chart-line"></i> Analitik</a>
    <a href="#"><i class="fa fa-gear"></i> Setting</a>
</div>

<script>
document.getElementById('dataArsipToggle').addEventListener('click', function() {
    this.classList.toggle('open');
    document.getElementById('dataArsipSubmenu').classList.toggle('open');
});
</script>