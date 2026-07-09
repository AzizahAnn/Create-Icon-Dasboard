<?php
$page_title = "Impor Gambar";
$page_icon_img = "icon/impor-gambar.png";
include "header.php";

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["gambar"])) {
    $allowed = ["jpg", "jpeg", "png", "webp", "gif"];
    $sukses = 0;
    $total = count($_FILES["gambar"]["name"]);

    for ($i = 0; $i < $total; $i++) {
        $ext = strtolower(pathinfo($_FILES["gambar"]["name"][$i], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES["gambar"]["error"][$i] === 0) {
            $newName = "import_" . date("Ymd_His") . "_" . $i . "." . $ext;
            if (move_uploaded_file($_FILES["gambar"]["tmp_name"][$i], "uploads/" . $newName)) {
                $sukses++;
            }
        }
    }
    $msg = "$sukses dari $total gambar berhasil diimpor.";
}

$files = glob("uploads/import_*.{jpg,jpeg,png,webp,gif}", GLOB_BRACE);
rsort($files);
?>

<div class="panel">
    <form method="POST" enctype="multipart/form-data">
        <label>Pilih beberapa gambar sekaligus</label>
        <input type="file" name="gambar[]" accept="image/*" multiple required>
        <button type="submit" class="btn-action"><i class="fa fa-file-import"></i> Impor Gambar</button>
    </form>
    <?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
</div>

<div class="panel">
    <label>Gambar yang Sudah Diimpor</label>
    <?php if (empty($files)): ?>
        <p class="note">Belum ada gambar diimpor.</p>
    <?php else: ?>
        <div class="file-list">
            <?php foreach ($files as $f): ?>
                <img src="<?= htmlspecialchars($f) ?>" alt="import">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include "footer.php"; ?>
