<?php
$page_title = "Impor File";
$page_icon_img = "icon/impor-dokumen.png";
include "header.php";

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

// ==== PASTIKAN KATEGORI "Impor File" ADA DI DATABASE ====
// Kalau belum ada, dibuat otomatis di sini supaya file yang diupload
// selalu punya kategori_id yang valid (bukan NULL).
$kategoriImporFileId = null;
try {
    $stmtKategori = $pdo->prepare("SELECT id FROM kategori WHERE nama_kategori = ? LIMIT 1");
    $stmtKategori->execute(["Impor File"]);
    $kategoriImporFileId = $stmtKategori->fetchColumn();

    if (!$kategoriImporFileId) {
        $insertKategori = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $insertKategori->execute(["Impor File"]);
        $kategoriImporFileId = $pdo->lastInsertId();
    }
} catch (Exception $e) {
    $msg = "Gagal menyiapkan kategori 'Impor File': " . $e->getMessage();
    $msg_type = "error";
}

// ==== HANDLE UPLOAD ====
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["dokumen"])) {
    $file = $_FILES["dokumen"];
    $ext  = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if ($file["error"] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => "File melebihi batas upload_max_filesize di php.ini.",
            UPLOAD_ERR_FORM_SIZE  => "File melebihi batas MAX_FILE_SIZE pada form.",
            UPLOAD_ERR_PARTIAL    => "File hanya terupload sebagian.",
            UPLOAD_ERR_NO_FILE    => "Tidak ada file yang dipilih.",
            UPLOAD_ERR_NO_TMP_DIR => "Folder temporary tidak ditemukan di server.",
            UPLOAD_ERR_CANT_WRITE => "Gagal menulis file ke disk.",
            UPLOAD_ERR_EXTENSION  => "Upload dihentikan oleh ekstensi PHP.",
        ];
        $msg = $errorMessages[$file["error"]] ?? "Terjadi kesalahan saat upload (kode error: {$file['error']}).";
        $msg_type = "error";
    } elseif (!in_array($ext, $allowed)) {
        $msg = "Format file tidak didukung. Format yang diizinkan: " . implode(", ", $allowed);
        $msg_type = "error";
    } elseif ($file["size"] > $max_size) {
        $msg = "Ukuran file terlalu besar. Maksimal " . ($max_size / 1024 / 1024) . " MB.";
        $msg_type = "error";
    } elseif (!$kategoriImporFileId) {
        $msg = "Upload dibatalkan: kategori 'Impor File' belum siap. " . $msg;
        $msg_type = "error";
    } else {
        $originalName = pathinfo($file["name"], PATHINFO_FILENAME);
        $safeName     = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $newName      = $safeName . "_" . date("Ymd_His") . "." . $ext;

        if (!is_writable($upload_dir)) {
            $msg = "Folder uploads/ tidak bisa ditulis. Periksa permission foldernya.";
            $msg_type = "error";
        } elseif (move_uploaded_file($file["tmp_name"], $upload_dir . $newName)) {
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
                $ukuranKb = round(filesize($upload_dir . $newName) / 1024, 1);
                $stmt = $pdo->prepare("INSERT INTO arsip
                    (judul, kategori_id, jenis_arsip, file_path, format_file, ukuran_file, uploaded_by, status, sifat)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'Biasa')");
                $stmt->execute([
                    $originalName,
                    $kategoriImporFileId,
                    "Lainnya",
                    $upload_dir . $newName,
                    $ext,
                    $ukuranKb,
                    $userId,
                ]);
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
$files = [];
if ($kategoriImporFileId) {
    $stmt = $pdo->prepare("SELECT * FROM arsip WHERE kategori_id = ? ORDER BY created_at DESC");
    $stmt->execute([$kategoriImporFileId]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
                                <?= htmlspecialchars($f["judul"]) ?>
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
                               onclick="return confirm('Yakin mau hapus <?= htmlspecialchars(addslashes($f['judul'])) ?>? File tidak bisa dikembalikan.');">
                                <i class="fa fa-trash"></i> Hapus
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<script>
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