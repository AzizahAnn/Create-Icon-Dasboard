<?php
$page_title = "Jernihkan Gambar";
$page_icon_img = "icon/jernihkan-gambar.png";
include "header.php";

$username = $_SESSION["username"] ?? "Admin";

require "config.php";

$upload_dir = "uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$hasil_file    = "";
$original_file = "";
$error         = "";

// ==== PASTIKAN KATEGORI "Jernihkan Gambar" ADA DI DATABASE ====
$kategoriId = null;
try {
    $stmtKategori = $pdo->prepare("SELECT id FROM kategori WHERE nama_kategori = ? LIMIT 1");
    $stmtKategori->execute(["Jernihkan Gambar"]);
    $kategoriId = $stmtKategori->fetchColumn();

    if (!$kategoriId) {
        $insertKategori = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $insertKategori->execute(["Jernihkan Gambar"]);
        $kategoriId = $pdo->lastInsertId();
    }
} catch (Exception $e) {
    $error = "Gagal menyiapkan kategori 'Jernihkan Gambar': " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["gambar"])) {
    if (!extension_loaded('gd')) {
        $error = "Ekstensi GD belum aktif di PHP kamu. Aktifkan dulu di php.ini (extension=gd), lalu restart Apache.";
    } elseif ($_FILES["gambar"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Terjadi kesalahan saat upload (kode error: {$_FILES['gambar']['error']}).";
    } else {
        $ext = strtolower(pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION));
        $tmp = $_FILES["gambar"]["tmp_name"];

        $img = null;
        if ($ext === "jpg" || $ext === "jpeg") $img = @imagecreatefromjpeg($tmp);
        elseif ($ext === "png") $img = @imagecreatefrompng($tmp);

        if (!$img) {
            $error = "Format gambar tidak didukung. Gunakan JPG atau PNG.";
        } else {
            // Simpan gambar asli untuk perbandingan before/after
            $originalName = "original_" . date("Ymd_His") . "." . $ext;
            move_uploaded_file($tmp, $upload_dir . $originalName);
            $original_file = $upload_dir . $originalName;

            // Buka ulang gambar dari file yang sudah disimpan (tmp_name sudah dipindah)
            $img = ($ext === "png") ? imagecreatefrompng($original_file) : imagecreatefromjpeg($original_file);

            // Proses "penjernihan": naikkan kontras, ketajaman, dan kurangi noise ringan
            imagefilter($img, IMG_FILTER_CONTRAST, -10);   // naikkan kontras
            imagefilter($img, IMG_FILTER_BRIGHTNESS, 5);   // sedikit lebih terang

            // sharpen manual pakai convolution matrix
            $sharpenMatrix = [
                [-1, -1, -1],
                [-1, 9, -1],
                [-1, -1, -1]
            ];
            imageconvolution($img, $sharpenMatrix, 1, 0);

            $newName = "clean_" . date("Ymd_His") . ".png";
            $target  = $upload_dir . $newName;
            imagepng($img, $target);
            imagedestroy($img);

            $hasil_file = $target;

            // Catat ke tabel arsip (kolom sesuai struktur aslinya: judul, kategori_id,
            // jenis_arsip, file_path, format_file, ukuran_file, uploaded_by, status, sifat)
            try {
                // Ambil id user yang sedang login
                $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $stmtUser->execute([$username]);
                $userId = $stmtUser->fetchColumn();
                $userId = $userId ?: null;

                $ukuranKb = round(filesize($target) / 1024, 1);

                $stmt = $pdo->prepare("INSERT INTO arsip
                    (judul, kategori_id, jenis_arsip, file_path, format_file, ukuran_file, uploaded_by, status, sifat)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'Biasa')");
                $stmt->execute([
                    $newName,
                    $kategoriId,
                    "Gambar",
                    $target,
                    "png",
                    $ukuranKb,
                    $userId,
                ]);
            } catch (Exception $e) {
                // Diamkan kalau gagal, atau aktifkan baris ini untuk debug:
                $error = "Gambar tersimpan, tapi gagal dicatat ke arsip: " . $e->getMessage();
            }
        }
    }
}

// Hapus riwayat (file + record database)
if (isset($_GET['hapus']) && ctype_digit($_GET['hapus'])) {
    $hapusId = (int) $_GET['hapus'];
    try {
        $stmtCek = $pdo->prepare("SELECT file_path FROM arsip WHERE id = ? AND kategori_id = ?");
        $stmtCek->execute([$hapusId, $kategoriId]);
        $rowHapus = $stmtCek->fetch();

        if ($rowHapus) {
            // Hapus file fisik kalau ada
            if (!empty($rowHapus['file_path']) && file_exists($rowHapus['file_path'])) {
                unlink($rowHapus['file_path']);
            }
            $stmtDel = $pdo->prepare("DELETE FROM arsip WHERE id = ?");
            $stmtDel->execute([$hapusId]);
        }
    } catch (Exception $e) {
        // Diamkan kalau gagal hapus
    }
    header("Location: jernihkan-gambar.php");
    exit;
}

// Ambil riwayat gambar yang sudah dijernihkan
$riwayat = [];
if ($kategoriId) {
    try {
        $stmtRiwayat = $pdo->prepare("SELECT id, judul, file_path, created_at FROM arsip WHERE kategori_id = ? ORDER BY created_at DESC");
        $stmtRiwayat->execute([$kategoriId]);
        $riwayat = $stmtRiwayat->fetchAll();
    } catch (Exception $e) {
        // Diamkan kalau gagal ambil riwayat
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jernihkan Gambar - Arsip Digital</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body {
    margin:0;
    font-family: 'Segoe UI', sans-serif;
    background:#0f172a;
    color:#fff;
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
.import-dropzone .preview-thumb {
    max-width: 220px;
    max-height: 160px;
    border-radius: 10px;
    margin-top: 12px;
    display: none;
}

.alert-box {
    margin-top: 14px;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 14px;
}
.alert-error { background: rgba(248,113,113,.12); color:#f87171; border:1px solid rgba(248,113,113,.3); }
.alert-note { background: rgba(148,163,184,.12); color:#94a3b8; border:1px solid rgba(148,163,184,.2); }

/* COMPARE */
.compare-grid {
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 15px;
}
.compare-box {
    background:#0f172a;
    border-radius:12px;
    padding:12px;
    text-align:center;
}
.compare-box span {
    display:block;
    color:#94a3b8;
    font-size:13px;
    margin-bottom:8px;
}
.compare-box img {
    max-width:100%;
    border-radius:10px;
    display:block;
    margin:0 auto;
}
@media (max-width: 700px) {
    .compare-grid { grid-template-columns: 1fr; }
}

.link-download {
    color:#38bdf8;
    text-decoration:none;
    display:inline-block;
    margin-top:12px;
}
.link-download:hover { text-decoration:underline; }
.link-hapus {
    color:#f87171;
    text-decoration:none;
}
.link-hapus:hover { text-decoration:underline; }

.box:hover { transform:translateY(-2px); transition:0.3s; }

/* RIWAYAT TABLE */
.riwayat-table {
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}
.riwayat-table thead tr {
    text-align:left;
    color:#94a3b8;
    font-size:13px;
    border-bottom:1px solid #334155;
}
.riwayat-table th, .riwayat-table td {
    padding:10px 8px;
}
.riwayat-table tbody tr {
    border-bottom:1px solid #1e293b;
}
.riwayat-table tbody tr:hover {
    background:#0f172a;
}
.riwayat-empty {
    color:#94a3b8;
    font-size:13px;
}
.riwayat-thumb {
    width:60px;
    height:60px;
    object-fit:cover;
    border-radius:8px;
    cursor:pointer;
    transition:transform .15s ease;
    border:1px solid #334155;
}
.riwayat-thumb:hover {
    transform:scale(1.08);
    border-color:#38bdf8;
}

/* MODAL PREVIEW */
.preview-modal {
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.85);
    z-index:1000;
    align-items:center;
    justify-content:center;
}
.preview-modal.show { display:flex; }
.preview-modal img {
    max-width:90vw;
    max-height:85vh;
    border-radius:12px;
    box-shadow:0 10px 40px rgba(0,0,0,0.5);
}
.preview-modal-close {
    position:absolute;
    top:20px;
    right:30px;
    color:#fff;
    font-size:32px;
    cursor:pointer;
    background:none;
    border:none;
    line-height:1;
}
.preview-modal-caption {
    position:absolute;
    bottom:24px;
    left:0;
    right:0;
    text-align:center;
    color:#cbd5e1;
    font-size:14px;
}
</style>
</head>

<body>

    <!-- UPLOAD BOX -->
    <div class="box">
        <form method="POST" enctype="multipart/form-data" id="importForm">
            <label>Pilih gambar yang buram / kualitasnya kurang bagus</label>
            <div class="import-dropzone" id="dropzone">
                <i class="fa fa-wand-magic-sparkles upload-icon"></i>
                <p>Klik untuk pilih gambar, atau drag & drop di sini</p>
                <p style="font-size:12px;">Format: JPG, JPEG, PNG</p>
                <div class="file-picked" id="filePicked"></div>
                <img id="previewThumb" class="preview-thumb" alt="Preview">
                <input type="file" name="gambar" id="fileInput" accept="image/jpeg,image/png">
            </div>
            <button type="submit" class="btn btn-blue" style="margin-top:14px;">
                <i class="fa fa-wand-magic-sparkles"></i> Jernihkan Gambar
            </button>
        </form>

        <?php if ($error): ?>
            <div class="alert-box alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>

    <!-- HASIL -->
    <?php if ($hasil_file): ?>
    <div class="box">
        <label>Hasil Perbandingan</label>
        <div class="compare-grid">
            <div class="compare-box">
                <span>Sebelum</span>
                <img src="<?= htmlspecialchars($original_file) ?>" alt="Gambar asli">
            </div>
            <div class="compare-box">
                <span>Sesudah</span>
                <img src="<?= htmlspecialchars($hasil_file) ?>" alt="Gambar hasil jernih">
            </div>
        </div>
        <a href="<?= htmlspecialchars($hasil_file) ?>" download class="link-download">
            <i class="fa fa-download"></i> Download gambar hasil
        </a>
    </div>
    <?php endif; ?>

    <!-- RIWAYAT -->
    <div class="box">
        <label>Riwayat Gambar yang Sudah Dijernihkan</label>
        <?php if (count($riwayat) > 0): ?>
        <table class="riwayat-table">
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Nama File</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $r): ?>
                <tr>
                    <td>
                        <img src="<?= htmlspecialchars($r['file_path']) ?>"
                             alt="<?= htmlspecialchars($r['judul']) ?>"
                             class="riwayat-thumb"
                             onclick="bukaPreview('<?= htmlspecialchars($r['file_path']) ?>', '<?= htmlspecialchars($r['judul']) ?>')">
                    </td>
                    <td><?= htmlspecialchars($r['judul']) ?></td>
                    <td style="color:#94a3b8;"><?= htmlspecialchars($r['created_at']) ?></td>
                    <td>
                        <a href="<?= htmlspecialchars($r['file_path']) ?>" download class="link-download" style="margin:0;">
                            <i class="fa fa-download"></i> Download
                        </a>
                        &nbsp;|&nbsp;
                        <a href="jernihkan-gambar.php?hapus=<?= (int) $r['id'] ?>"
                           class="link-hapus"
                           onclick="return confirm('Yakin mau hapus <?= htmlspecialchars(addslashes($r['judul'])) ?>? File tidak bisa dikembalikan.');">
                            <i class="fa fa-trash"></i> Hapus
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="riwayat-empty">Belum ada riwayat gambar yang dijernihkan.</p>
        <?php endif; ?>
    </div>

    <div class="box">
        <p style="color:#94a3b8; margin:0; font-size:13px;">
            Catatan: ini perbaikan dasar (kontras, ketajaman, noise ringan) pakai PHP GD — bukan AI upscaling.
            Untuk hasil yang jauh lebih tajam, biasanya butuh model AI khusus.
        </p>
    </div>

</div>

<!-- MODAL PREVIEW GAMBAR -->
<div class="preview-modal" id="previewModal" onclick="tutupPreview(event)">
    <button class="preview-modal-close" onclick="tutupPreview(event)">&times;</button>
    <img id="previewModalImg" src="" alt="Preview">
    <div class="preview-modal-caption" id="previewModalCaption"></div>
</div>

<script>
function bukaPreview(src, nama) {
    document.getElementById('previewModalImg').src = src;
    document.getElementById('previewModalCaption').textContent = nama;
    document.getElementById('previewModal').classList.add('show');
}

function tutupPreview(e) {
    // Jangan tutup kalau yang diklik adalah gambarnya sendiri
    if (e.target.id === 'previewModalImg') return;
    document.getElementById('previewModal').classList.remove('show');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.getElementById('previewModal').classList.remove('show');
    }
});

// Guard: elemen dataArsipToggle mungkin berasal dari header.php (sidebar),
// jika tidak ditemukan, skip tanpa menghentikan script lain di bawahnya.
const dataArsipToggleEl  = document.getElementById('dataArsipToggle');
const dataArsipSubmenuEl = document.getElementById('dataArsipSubmenu');
if (dataArsipToggleEl && dataArsipSubmenuEl) {
    dataArsipToggleEl.addEventListener('click', function() {
        this.classList.toggle('open');
        dataArsipSubmenuEl.classList.toggle('open');
    });
}

const dropzone     = document.getElementById('dropzone');
const fileInput    = document.getElementById('fileInput');
const filePicked   = document.getElementById('filePicked');
const previewThumb = document.getElementById('previewThumb');

document.getElementById('importForm').addEventListener('submit', function(e) {
    if (fileInput.files.length === 0) {
        e.preventDefault();
        alert('Silakan pilih gambar terlebih dahulu.');
    }
});

dropzone.addEventListener('click', () => fileInput.click());

function showPreview(file) {
    if (!file) return;
    filePicked.textContent = "Terpilih: " + file.name;
    const reader = new FileReader();
    reader.onload = (e) => {
        previewThumb.src = e.target.result;
        previewThumb.style.display = "block";
    };
    reader.readAsDataURL(file);
}

fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        showPreview(fileInput.files[0]);
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
        showPreview(dt.files[0]);
    }
});
</script>

</body>
</html>
