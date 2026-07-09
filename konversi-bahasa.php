<?php
$page_title = "Konversi Bahasa";
$page_icon_img = "icon/konversi-bahasa.png";
include "header.php";

$hasil = "";
$teks = "";
$dari = "id";
$ke = "en";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teks = trim($_POST["teks"] ?? "");
    $dari = $_POST["dari"] ?? "id";
    $ke = $_POST["ke"] ?? "en";

    if ($teks !== "") {
        $url = "https://api.mymemory.translated.net/get?q=" . urlencode($teks) . "&langpair=$dari|$ke";
        $response = @file_get_contents($url);
        if ($response !== false) {
            $json = json_decode($response, true);
            $hasil = $json["responseData"]["translatedText"] ?? "Gagal menerjemahkan.";
        } else {
            $hasil = "Tidak bisa terhubung ke layanan terjemahan. Cek koneksi internet.";
        }
    }
}
?>

<div class="panel">
    <form method="POST">
        <label>Teks yang mau diterjemahkan</label>
        <textarea name="teks" rows="5" required><?= htmlspecialchars($teks) ?></textarea>

        <label>Dari Bahasa</label>
        <select name="dari">
            <option value="id" <?= $dari=="id"?"selected":"" ?>>Indonesia</option>
            <option value="en" <?= $dari=="en"?"selected":"" ?>>Inggris</option>
            <option value="ja" <?= $dari=="ja"?"selected":"" ?>>Jepang</option>
            <option value="ar" <?= $dari=="ar"?"selected":"" ?>>Arab</option>
        </select>

        <label>Ke Bahasa</label>
        <select name="ke">
            <option value="en" <?= $ke=="en"?"selected":"" ?>>Inggris</option>
            <option value="id" <?= $ke=="id"?"selected":"" ?>>Indonesia</option>
            <option value="ja" <?= $ke=="ja"?"selected":"" ?>>Jepang</option>
            <option value="ar" <?= $ke=="ar"?"selected":"" ?>>Arab</option>
        </select>

        <button type="submit" class="btn-action"><i class="fa fa-language"></i> Terjemahkan</button>
    </form>
</div>

<?php if ($hasil): ?>
<div class="panel">
    <label>Hasil Terjemahan</label>
    <div class="result-box"><?= htmlspecialchars($hasil) ?></div>
</div>
<?php endif; ?>

<p class="note" style="margin-top:-10px;">Menggunakan layanan gratis MyMemory Translation API (butuh koneksi internet, ada batas penggunaan harian).</p>

<?php include "footer.php"; ?>
