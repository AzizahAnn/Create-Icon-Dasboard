<?php
$page_title = "Stempel Waktu";
$page_icon = "fa-clock";
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
            $width = imagesx($img);
            $height = imagesy($img);

            $teks = date("d-m-Y H:i:s");
            $fontSize = max(2, intval($width / 400)) + 3;

            // kotak semi transparan di pojok kanan bawah
            $textColor = imagecolorallocate($img, 255, 255, 255);
            $bgColor = imagecolorallocatealpha($img, 0, 0, 0, 60);

            $textWidth = strlen($teks) * imagefontwidth($fontSize);
            $textHeight = imagefontheight($fontSize);
            $padding = 10;

            $x = $width - $textWidth - (2 * $padding) - 15;
            $y = $height - $textHeight - (2 * $padding) - 15;

            imagefilledrectangle($img, $x, $y, $x + $textWidth + (2*$padding), $y + $textHeight + (2*$padding), $bgColor);
            imagestring($img, $fontSize, $x + $padding, $y + $padding, $teks, $textColor);

            $newName = "stamped_" . date("Ymd_His") . ".png";
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
        <label>Pilih gambar (JPG/PNG) yang mau distempel tanggal & jam</label>
        <input type="file" name="gambar" accept="image/jpeg,image/png" required>
        <button type="submit" class="btn-action"><i class="fa fa-stamp"></i> Tambahkan Stempel</button>
    </form>
    <?php if ($error): ?><p class="note" style="color:#fca5a5;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
</div>

<?php if ($hasil_file): ?>
<div class="panel">
    <label>Hasil</label>
    <img src="<?= htmlspecialchars($hasil_file) ?>" style="max-width:100%; border-radius:10px;">
    <p class="note"><a href="<?= htmlspecialchars($hasil_file) ?>" download style="color:#38bdf8;">Download gambar hasil stempel</a></p>
</div>
<?php endif; ?>

<?php include "includes/footer.php"; ?>
