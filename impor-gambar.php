<?php
$page_title = "Impor Gambar";
$page_icon = "fa-file-import";
require "config.php";
include "header.php";

$EKSTENSI_DIIZINKAN = ["jpg", "jpeg", "png", "webp", "gif"];
$msg = "";
$msgType = "success"; // "success" atau "error"

// Ambil id kategori "Impor Gambar", kalau belum ada di tabel kategori, buat otomatis
function ambilKategoriId(PDO $pdo, string $namaKategori): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM kategori WHERE nama_kategori = ?");
    $stmt->execute([$namaKategori]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $stmt->execute([$namaKategori]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Gagal membuat kategori '$namaKategori': " . $e->getMessage());
        return null;
    }
}

// ==========================================
// AKSI: UPLOAD GAMBAR
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "upload" && isset($_FILES["gambar"])) {
    $keteranganBatch = trim($_POST["keterangan"] ?? "");
    $total = count($_FILES["gambar"]["name"]);
    $sukses = 0;
    $gagal = 0;

    if (!is_dir("uploads")) {
        mkdir("uploads", 0755, true);
    }

    $kategoriId = ambilKategoriId($pdo, "Impor Gambar");

    for ($i = 0; $i < $total; $i++) {
        if (($_FILES["gambar"]["error"][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $gagal++;
            continue;
        }

        $namaAsli = $_FILES["gambar"]["name"][$i];
        $ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

        if (!in_array($ext, $EKSTENSI_DIIZINKAN)) {
            $gagal++;
            continue;
        }

        $newName = "import_" . date("Ymd_His") . "_" . bin2hex(random_bytes(3)) . "." . $ext;
        $target = "uploads/" . $newName;

        if (move_uploaded_file($_FILES["gambar"]["tmp_name"][$i], $target)) {
            $sukses++;

            // Tentukan nama/keterangan yang disimpan
            if ($keteranganBatch !== "") {
                $namaDokumen = $total > 1 ? $keteranganBatch . " (" . ($i + 1) . ")" : $keteranganBatch;
            } else {
                $namaDokumen = pathinfo($namaAsli, PATHINFO_FILENAME);
            }

            if ($kategoriId) {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO arsip (nama_dokumen, kategori_id, file_path, uploaded_by, created_at)
                         VALUES (?, ?, ?, ?, NOW())"
                    );
                    $stmt->execute([
                        $namaDokumen,
                        $kategoriId,
                        $target,
                        $_SESSION["user_id"] ?? null,
                    ]);
                } catch (PDOException $e) {
                    error_log("Gagal simpan riwayat impor gambar: " . $e->getMessage());
                }
            }
        } else {
            $gagal++;
        }
    }

    if ($sukses > 0) {
        $msg = "$sukses dari $total gambar berhasil diimpor" . ($gagal > 0 ? " ($gagal gagal)." : ".");
    } else {
        $msg = "Tidak ada gambar yang berhasil diimpor. Pastikan format JPG/PNG/WEBP/GIF.";
        $msgType = "error";
    }
}

// ==========================================
// AKSI: HAPUS GAMBAR
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "hapus") {
    $id = (int) ($_POST["id"] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare(
                "SELECT arsip.file_path FROM arsip
                 JOIN kategori ON arsip.kategori_id = kategori.id
                 WHERE arsip.id = ? AND kategori.nama_kategori = 'Impor Gambar'"
            );
            $stmt->execute([$id]);
            $path = $stmt->fetchColumn();

            if ($path) {
                $del = $pdo->prepare(
                    "DELETE arsip FROM arsip
                     JOIN kategori ON arsip.kategori_id = kategori.id
                     WHERE arsip.id = ? AND kategori.nama_kategori = 'Impor Gambar'"
                );
                $del->execute([$id]);

                if (file_exists($path)) {
                    @unlink($path);
                }
                $msg = "Gambar berhasil dihapus.";
            } else {
                $msg = "Gambar tidak ditemukan.";
                $msgType = "error";
            }
        } catch (PDOException $e) {
            error_log("Gagal hapus gambar: " . $e->getMessage());
            $msg = "Gagal menghapus gambar.";
            $msgType = "error";
        }
    }
}

// ==========================================
// AKSI: UBAH NAMA/KETERANGAN
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "ubah") {
    $id = (int) ($_POST["id"] ?? 0);
    $namaBaru = trim($_POST["nama_baru"] ?? "");
    if ($id > 0 && $namaBaru !== "") {
        try {
            $stmt = $pdo->prepare(
                "UPDATE arsip a
                 JOIN kategori k ON a.kategori_id = k.id
                 SET a.nama_dokumen = ?
                 WHERE a.id = ? AND k.nama_kategori = 'Impor Gambar'"
            );
            $stmt->execute([$namaBaru, $id]);
            $msg = "Keterangan berhasil diperbarui.";
        } catch (PDOException $e) {
            error_log("Gagal ubah keterangan: " . $e->getMessage());
            $msg = "Gagal memperbarui keterangan.";
            $msgType = "error";
        }
    }
}

// ==========================================
// AMBIL DAFTAR GAMBAR YANG SUDAH DIIMPOR
// ==========================================
$riwayat = [];
try {
    $stmt = $pdo->query(
        "SELECT arsip.* FROM arsip
         JOIN kategori ON arsip.kategori_id = kategori.id
         WHERE kategori.nama_kategori = 'Impor Gambar'
         ORDER BY arsip.created_at DESC"
    );
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Gagal ambil daftar impor gambar: " . $e->getMessage());
}
?>

<div class="panel">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="upload">
        <label>Pilih satu atau beberapa gambar</label>
        <input type="file" name="gambar[]" accept="image/*" multiple required>

        <label>Nama / Keterangan (opsional)</label>
        <input type="text" name="keterangan" placeholder="Contoh: Foto kegiatan rapat bulan Juni">

        <button type="submit" class="btn-action"><i class="fa fa-file-import"></i> Impor Gambar</button>
    </form>
    <?php if ($msg): ?>
        <p class="note" style="<?= $msgType === "error" ? "color:#fca5a5;" : "color:#86efac;" ?>"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>
</div>

<div class="panel">
    <label>Gambar yang Sudah Diimpor (<?= count($riwayat) ?>)</label>
    <?php if (empty($riwayat)): ?>
        <p class="note">Belum ada gambar diimpor.</p>
    <?php else: ?>
        <div class="gallery-grid">
            <?php foreach ($riwayat as $r): ?>
                <div class="gallery-item">
                    <img
                        src="<?= htmlspecialchars($r["file_path"]) ?>"
                        alt="<?= htmlspecialchars($r["nama_dokumen"]) ?>"
                        onclick="bukaPreview('<?= htmlspecialchars($r["file_path"], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($r["nama_dokumen"]), ENT_QUOTES) ?>')"
                    >
                    <div class="gallery-caption"><?= htmlspecialchars($r["nama_dokumen"]) ?></div>
                    <div class="gallery-actions">
                        <button type="button" class="btn-mini" onclick="toggleUbah(<?= $r["id"] ?>)"><i class="fa fa-pen"></i></button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin mau hapus gambar ini?');">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= $r["id"] ?>">
                            <button type="submit" class="btn-mini btn-mini-danger"><i class="fa fa-trash"></i></button>
                        </form>
                    </div>
                    <form method="POST" id="formUbah<?= $r["id"] ?>" class="form-ubah" style="display:none;">
                        <input type="hidden" name="aksi" value="ubah">
                        <input type="hidden" name="id" value="<?= $r["id"] ?>">
                        <input type="text" name="nama_baru" value="<?= htmlspecialchars($r["nama_dokumen"]) ?>">
                        <button type="submit" class="btn-mini">Simpan</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL PREVIEW -->
<div id="previewModal" class="preview-modal" onclick="tutupPreview()">
    <span class="preview-close">&times;</span>
    <img id="previewImg" src="" alt="">
    <div id="previewCaption" class="preview-caption"></div>
</div>

<style>
.gallery-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(150px, 1fr));
    gap:15px;
    margin-top:12px;
}
.gallery-item {
    background:#0f172a;
    border:1px solid #334155;
    border-radius:10px;
    padding:8px;
    text-align:center;
}
.gallery-item img {
    width:100%;
    height:120px;
    object-fit:cover;
    border-radius:8px;
    cursor:zoom-in;
    display:block;
}
.gallery-caption {
    font-size:12px;
    color:#cbd5e1;
    margin-top:8px;
    word-break:break-word;
    min-height:16px;
}
.gallery-actions {
    display:flex;
    justify-content:center;
    gap:6px;
    margin-top:8px;
}
.btn-mini {
    padding:5px 9px;
    font-size:11px;
    border:none;
    border-radius:6px;
    background:#38bdf8;
    color:#000;
    cursor:pointer;
}
.btn-mini-danger { background:#ef4444; color:#fff; }
.form-ubah {
    margin-top:8px;
    display:flex;
    gap:6px;
}
.form-ubah input[type=text] {
    margin-bottom:0;
    padding:6px;
    font-size:12px;
}

.preview-modal {
    display:none;
    position:fixed;
    z-index:999;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.9);
    text-align:center;
    cursor:zoom-out;
}
.preview-modal img {
    max-width:90%;
    max-height:80vh;
    margin-top:5vh;
    border-radius:10px;
}
.preview-caption {
    color:#fff;
    margin-top:15px;
    font-size:14px;
}
.preview-close {
    position:absolute;
    top:20px; right:35px;
    color:#fff;
    font-size:40px;
    cursor:pointer;
}
</style>

<script>
function bukaPreview(src, nama) {
    document.getElementById('previewImg').src = src;
    document.getElementById('previewCaption').textContent = nama;
    document.getElementById('previewModal').style.display = 'block';
}
function tutupPreview() {
    document.getElementById('previewModal').style.display = 'none';
}
function toggleUbah(id) {
    const form = document.getElementById('formUbah' + id);
    form.style.display = form.style.display === 'none' ? 'flex' : 'none';
}
</script>

<?php include "footer.php"; ?>