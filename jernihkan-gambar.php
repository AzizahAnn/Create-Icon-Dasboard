<?php
$page_title = "Jernihkan Gambar";
$page_icon = "fa-wand-magic-sparkles";
include "includes/header.php";

$hasil_file = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["gambar"])) {
    if (!extension_loaded('gd')) {
        $error = "Ekstensi GD belum aktif di PHP kamu. Aktifkan dulu di php.ini (extension=gd), lalu restart Apache.";
    } else {
        $ext = strtolower(pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION));
        $tmp = $_FILES["gambar"]["tmp_name"];

        $img = null;
        if ($ext === "jpg" || $ext === "jpeg") $img = @imagecreatefromjpeg($tmp);
        elseif ($ext === "png") $img = @imagecreatefrompng($tmp);

        if (!$img) {
            $error = "Format gambar tidak didukung. Gunakan JPG atau PNG.";
        } else {
            // Proses "penjernihan": naikkan kontras, ketajaman, dan kurangi noise ringan
            imagefilter($img, IMG_FILTER_SMOOTH, -4);      // kurangi noise sedikit
            imagefilter($img, IMG_FILTER_CONTRAST, -15);   // naikkan kontras
            imagefilter($img, IMG_FILTER_BRIGHTNESS, 5);   // sedikit lebih terang

            // sharpen manual pakai convolution matrix
            $sharpenMatrix = [
                [0, -1, 0],
                [-1, 5, -1],
                [0, -1, 0]
            ];
            imageconvolution($img, $sharpenMatrix, 1, 0);

            $newName = "clean_" . date("Ymd_His") . ".png";
            $target = "uploads/" . $newName;
            imagepng($img, $target);
            imagedestroy($img);

            $hasil_file = $target;
        }
    }
}
?>

<div class="panel">
    <form method="POST" enctype="multipart/form-data">
        <label>Pilih gambar yang buram / kualitasnya kurang bagus</label>
        <input type="file" name="gambar" accept="image/jpeg,image/png" required>
        <button type="submit" class="btn-action"><i class="fa fa-wand-magic-sparkles"></i> Jernihkan Gambar</button>
    </form>
    <?php if ($error): ?><p class="note" style="color:#fca5a5;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
</div>

<?php if ($hasil_file): ?>
<div class="panel">
    <label>Hasil</label>
    <img src="<?= htmlspecialchars($hasil_file) ?>" style="max-width:100%; border-radius:10px;">
    <p class="note"><a href="<?= htmlspecialchars($hasil_file) ?>" download style="color:#38bdf8;">Download gambar hasil</a></p>
</div>
<?php endif; ?>

<p class="note">Catatan: ini perbaikan dasar (kontras, ketajaman, noise ringan) pakai PHP GD — bukan AI upscaling. Untuk hasil yang jauh lebih tajam, biasanya butuh model AI khusus.</p>

<?php include "includes/footer.php"; ?>
