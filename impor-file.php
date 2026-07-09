<?php
session_start();

if (empty($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"] ?? "Admin";

require "config.php";

$upload_dir = "uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$allowed  = ["pdf", "doc", "docx", "xls", "xlsx", "txt", "csv", "zip"];
$max_size = 20 * 1024 * 1024; // 20 MB
$msg      = "";
$msg_type = ""; // success | error

// Ambil id kategori "Impor File" — pastikan sudah ditambahkan di tabel kategori
$kategoriImporFileId = null;
try {
    $stmtKategori = $pdo->prepare("SELECT id FROM kategori WHERE nama_kategori = ? LIMIT 1");
    $stmtKategori->execute(["Impor File"]);
    $kategoriImporFileId = $stmtKategori->fetchColumn();
    $kategoriImporFileId = $kategoriImporFileId ?: null;
} catch (Exception $e) {
    // Diamkan kalau gagal ambil kategori
}

// ==== HANDLE UPLOAD ====
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["dokumen"])) {
    $file = $_FILES["dokumen"];
    $ext  = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if ($file["error"] !== UPLOAD_ERR_OK) {
        $msg = "Terjadi kesalahan saat upload (kode error: {$file['error']}).";
        $msg_type = "error";
    } elseif (!in_array($ext, $allowed)) {
        $msg = "Format file tidak didukung. Format yang diizinkan: " . implode(", ", $allowed);
        $msg_type = "error";
    } elseif ($file["size"] > $max_size) {
        $msg = "Ukuran file terlalu besar. Maksimal " . ($max_size / 1024 / 1024) . " MB.";
        $msg_type = "error";
    } else {
        $originalName = pathinfo($file["name"], PATHINFO_FILENAME);
        $safeName     = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $newName      = $safeName . "_" . date("Ymd_His") . "." . $ext;

        if (move_uploaded_file($file["tmp_name"], $upload_dir . $newName)) {
            $msg = "File berhasil diimpor: " . $newName;
            $msg_type = "success";

            // Ambil id user yang sedang login
            $userId = null;
            try {
                $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $stmtUser->execute([$username]);
                $userId = $stmtUser->fetchColumn();
                $userId = $userId ?: null;
            } catch (Exception $e) {
                // Diamkan
            }

            // Catat ke tabel arsip dengan kategori_id khusus "Impor File"
            try {
                $stmt = $pdo->prepare("INSERT INTO arsip (nama_dokumen, kategori_id, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$newName, $kategoriImporFileId, $upload_dir . $newName, $userId]);
            } catch (Exception $e) {
                $msg = "File tersimpan, tapi gagal dicatat ke database: " . $e->getMessage();
                $msg_type = "error";
            }
        } else {
            $msg = "Gagal mengimpor file. Periksa izin folder uploads/.";
            $msg_type = "error";
        }
    }
}

// ==== HANDLE DELETE ====
if (isset($_GET["delete"])) {
    $id = (int) $_GET["delete"];

    $stmt = $pdo->prepare("SELECT file_path FROM arsip WHERE id = ? AND kategori_id = ?");
    $stmt->execute([$id, $kategoriImporFileId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Hapus file fisik kalau masih ada
        if (is_file($row["file_path"])) {
            unlink($row["file_path"]);
        }

        // Hapus baris dari database
        $del = $pdo->prepare("DELETE FROM arsip WHERE id = ?");
        $del->execute([$id]);

        $msg = "File berhasil dihapus.";
        $msg_type = "success";
    } else {
        $msg = "Data file tidak ditemukan.";
        $msg_type = "error";
    }
}

// ==== LIST FILES (dari database, hanya kategori Impor File) ====
$stmt = $pdo->prepare("SELECT * FROM arsip WHERE kategori_id = ? ORDER BY created_at DESC");
$stmt->execute([$kategoriImporFileId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$icon_map = [
    "pdf"  => ["fa-file-pdf",   "#f87171"],
    "doc"  => ["fa-file-word",  "#60a5fa"],
    "docx" => ["fa-file-word",  "#60a5fa"],
    "xls"  => ["fa-file-excel", "#4ade80"],
    "xlsx" => ["fa-file-excel", "#4ade80"],
    "txt"  => ["fa-file-lines", "#facc15"],
    "csv"  => ["fa-file-csv",   "#4ade80"],
    "zip"  => ["fa-file-zipper","#c084fc"],
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Impor File - Arsip Digital</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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
.sidebar a.active { background:#1e293b; color:#38bdf8; }

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
.submenu a {
    padding:10px 12px;
    font-size:13px;
    color:#94a3b8;
}
.submenu a:hover { background:#1e293b; color:#38bdf8; }
.submenu a.active { background:#1e293b; color:#38bdf8; }
.submenu a img.submenu-icon {
    width:16px;
    height:16px;
    object-fit:contain;
    margin-right:6px;
    vertical-align:middle;
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

/* BUTTON */
.btn { padding:10px 15px; border:none; border-radius:10px; cursor:pointer; margin-right:10px; }
.btn-blue { background:#38bdf8; color:#000; }
.btn-green { background:#22c55e; color:#000; }
.btn-purple { background:#a855f7; color:#fff; }
.btn-logout { background:#ef4444; color:#fff; text-decoration:none; display:inline-block; }
.btn-logout:hover { background:#dc2626; }
.user-info { color:#cbd5e1; margin-right:15px; font-size:14px; }

/* PAGE HEADER */
.page-header {
    margin-top:20px;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:10px;
}
.page-header i { color:#38bdf8; font-size:22px; }
.page-header h2 { margin:0; font-size:22px; }

/* BOX / PANEL */
.box {
    background:#1e293b;
    padding:20px;
    border-radius:15px;
    margin-top:20px;
}
.box label {
    display:block;
    margin-bottom:10px;
    color:#94a3b8;
    font-size:14px;
}

/* DROPZONE */
.import-dropzone {
    border: 2px dashed #334155;
    border-radius: 14px;
    padding: 40px 20px;
    text-align: center;
    background: #0f172a;
    cursor: pointer;
    transition: all .2s ease;
}
.import-dropzone.dragover {
    border-color: #38bdf8;
    background: #0c1a2b;
}
.import-dropzone i.upload-icon {
    font-size: 42px;
    color: #38bdf8;
    margin-bottom: 12px;
    display: block;
}
.import-dropzone p { color:#94a3b8; margin: 4px 0; }
.import-dropzone .file-picked { color:#38bdf8; font-weight:600; margin-top:8px; }
.import-dropzone input[type="file"] { display:none; }

.alert-box {
    margin-top: 14px;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 14px;
}
.alert-success { background: rgba(74,222,128,.12); color:#4ade80; border:1px solid rgba(74,222,128,.3); }
.alert-error { background: rgba(248,113,113,.12); color:#f87171; border:1px solid rgba(248,113,113,.3); }

/* TABLE */
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { text-align:left; padding:12px 10px; border-bottom:1px solid #334155; font-size:14px; }
th { color:#94a3b8; font-weight:500; }
.file-row-icon { display:flex; align-items:center; gap:10px; }
.file-row-icon i { font-size:18px; }
a.link-download { color:#38bdf8; text-decoration:none; }
a.link-download:hover { text-decoration:underline; }
a.link-view { color:#4ade80; text-decoration:none; margin-right:14px; }
a.link-view:hover { text-decoration:underline; }
a.link-hapus { color:#f87171; text-decoration:none; margin-left:14px; }
a.link-hapus:hover { text-decoration:underline; }

.box:hover { transform:translateY(-2px); transition:0.3s; }
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2><i class="fa fa-database"></i> DOKPIN 5.0</h2>
    <a href="admin.php"><i class="fa fa-home"></i> Dashboard</a>

    <div class="has-submenu open" id="dataArsipToggle">
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
        <a href="impor-file.php" class="active"><img class="submenu-icon" src="icon/impor-dokumen.png" alt="Impor File"> Impor File</a>
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

    <!-- PAGE HEADER -->
    <div class="page-header">
        <i class="fa fa-folder-open"></i>
        <h2>Impor File</h2>
    </div>

    <!-- UPLOAD BOX -->
    <div class="box">
        <form method="POST" enctype="multipart/form-data" id="importForm">
            <label>Pilih atau seret file dokumen ke sini</label>
            <div class="import-dropzone" id="dropzone">
                <i class="fa fa-cloud-arrow-up upload-icon"></i>
                <p>Klik untuk pilih file, atau drag & drop di sini</p>
                <p style="font-size:12px;">Format: PDF, DOC(X), XLS(X), TXT, CSV, ZIP — Maks 20MB</p>
                <div class="file-picked" id="filePicked"></div>
                <input type="file" name="dokumen" id="fileInput">
            </div>
            <button type="submit" class="btn btn-blue" style="margin-top:14px;">
                <i class="fa fa-upload"></i> Impor File
            </button>
        </form>

        <?php if ($msg): ?>
            <div class="alert-box <?= $msg_type === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- LIST BOX -->
    <div class="box">
        <label>Daftar File Diimpor (<?= count($files) ?>)</label>
        <?php if (empty($files)): ?>
            <p style="color:#94a3b8;">Belum ada file diimpor.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Nama File</th><th>Ukuran</th><th>Diupload</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($files as $f):
                    $path = $f["file_path"];
                    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    [$icon, $color] = $icon_map[$ext] ?? ["fa-file", "#94a3b8"];
                    $size = is_file($path) ? round(filesize($path) / 1024, 1) . " KB" : "-";
                    $time = date("d M Y, H:i", strtotime($f["created_at"]));
                ?>
                    <tr>
                        <td>
                            <div class="file-row-icon">
                                <i class="fa <?= $icon ?>" style="color:<?= $color ?>;"></i>
                                <?= htmlspecialchars($f["nama_dokumen"]) ?>
                            </div>
                        </td>
                        <td><?= $size ?></td>
                        <td><?= $time ?></td>
                        <td>
                            <?php $previewable = in_array($ext, ["pdf", "txt", "csv"]); ?>
                            <?php if ($previewable): ?>
                                <a href="<?= htmlspecialchars($path) ?>" target="_blank" class="link-view"><i class="fa fa-eye"></i> Lihat</a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($path) ?>" download class="link-download"><i class="fa fa-download"></i> Download</a>
                            <a href="?delete=<?= $f['id'] ?>"
                               class="link-hapus"
                               onclick="return confirm('Yakin mau hapus <?= htmlspecialchars(addslashes($f['nama_dokumen'])) ?>? File tidak bisa dikembalikan.');">
                                <i class="fa fa-trash"></i> Hapus
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<script>
document.getElementById('dataArsipToggle').addEventListener('click', function() {
    this.classList.toggle('open');
    document.getElementById('dataArsipSubmenu').classList.toggle('open');
});

const dropzone   = document.getElementById('dropzone');
const fileInput  = document.getElementById('fileInput');
const filePicked = document.getElementById('filePicked');

document.getElementById('importForm').addEventListener('submit', function(e) {
    if (fileInput.files.length === 0) {
        e.preventDefault();
        alert('Silakan pilih file terlebih dahulu sebelum mengimpor.');
    }
});

dropzone.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        filePicked.textContent = "Terpilih: " + fileInput.files[0].name;
    }
});

['dragenter', 'dragover'].forEach(evt => {
    dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });
});

['dragleave', 'drop'].forEach(evt => {
    dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
    });
});

dropzone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    if (dt.files.length > 0) {
        fileInput.files = dt.files;
        filePicked.textContent = "Terpilih: " + dt.files[0].name;
    }
});
</script>

</body>
</html>