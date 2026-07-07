<?php
$page_title = "Scan Gambar";
$page_icon = "fa-camera";
include "includes/header.php";

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["gambar"])) {
    $ext = strtolower(pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "webp"];
    if (in_array($ext, $allowed)) {
        $newName = "scan_" . date("Ymd_His") . "." . $ext;
        $target = "uploads/" . $newName;
        if (move_uploaded_file($_FILES["gambar"]["tmp_name"], $target)) {
            $msg = "Gambar berhasil disimpan sebagai <b>$newName</b>";
        } else {
            $msg = "Gagal menyimpan gambar.";
        }
    } else {
        $msg = "Format file tidak didukung. Gunakan JPG/PNG/WEBP.";
    }
}

// Ambil daftar gambar yang sudah discan
$files = glob("uploads/scan_*.{jpg,jpeg,png,webp}", GLOB_BRACE);
rsort($files);
?>

<div class="panel">
    <form method="POST" enctype="multipart/form-data">
        <label>Ambil / Pilih gambar untuk di-scan</label>
        <input type="file" name="gambar" accept="image/*" capture="environment" required>
        <button type="submit" class="btn-action"><i class="fa fa-upload"></i> Simpan Scan</button>
    </form>
    <?php if ($msg): ?>
        <p class="note"><?= $msg ?></p>
    <?php endif; ?>
</div>

<div class="panel">
    <label>Riwayat Hasil Scan</label>
    <?php if (empty($files)): ?>
        <p class="note">Belum ada gambar yang discan.</p>
    <?php else: ?>
        <div class="file-list">
            <?php foreach ($files as $f): ?>
                <img src="<?= htmlspecialchars($f) ?>" alt="scan">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
