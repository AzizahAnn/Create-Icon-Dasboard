<?php
$page_title = "Peringkas Catatan";
$page_icon = "fa-list-check";
include "includes/header.php";

$ringkasan = "";
$teks_asli = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teks_asli = trim($_POST["teks"] ?? "");
    $jumlah_kalimat = max(1, (int)($_POST["jumlah"] ?? 3));

    if ($teks_asli !== "") {
        // Ringkasan ekstraktif sederhana:
        // pecah jadi kalimat, skor tiap kalimat dari frekuensi kata penting, ambil skor tertinggi
        $kalimat = preg_split('/(?<=[.!?])\s+/', $teks_asli, -1, PREG_SPLIT_NO_EMPTY);

        $stopwords = ["yang","dan","di","ke","dari","untuk","pada","dengan","atau","ini","itu","adalah","akan","juga","tersebut","sebagai","dalam","tidak","ada"];

        $kata_freq = [];
        foreach ($kalimat as $k) {
            $words = preg_split('/\s+/', strtolower(preg_replace('/[^\w\s]/', '', $k)));
            foreach ($words as $w) {
                if ($w === "" || in_array($w, $stopwords)) continue;
                $kata_freq[$w] = ($kata_freq[$w] ?? 0) + 1;
            }
        }

        $skor_kalimat = [];
        foreach ($kalimat as $idx => $k) {
            $words = preg_split('/\s+/', strtolower(preg_replace('/[^\w\s]/', '', $k)));
            $skor = 0;
            foreach ($words as $w) {
                $skor += $kata_freq[$w] ?? 0;
            }
            $skor_kalimat[$idx] = $skor;
        }

        arsort($skor_kalimat);
        $terpilih = array_slice(array_keys($skor_kalimat), 0, min($jumlah_kalimat, count($kalimat)), true);
        sort($terpilih); // urutkan sesuai urutan asli

        $hasil = [];
        foreach ($terpilih as $idx) {
            $hasil[] = trim($kalimat[$idx]);
        }
        $ringkasan = implode(" ", $hasil);
    }
}
?>

<div class="panel">
    <form method="POST">
        <label>Tempel catatan / teks panjang di sini</label>
        <textarea name="teks" rows="8" required><?= htmlspecialchars($teks_asli) ?></textarea>

        <label>Jumlah kalimat ringkasan</label>
        <input type="text" name="jumlah" value="3" style="width:80px;">

        <button type="submit" class="btn-action"><i class="fa fa-compress"></i> Ringkas</button>
    </form>
</div>

<?php if ($ringkasan): ?>
<div class="panel">
    <label>Hasil Ringkasan</label>
    <div class="result-box"><?= htmlspecialchars($ringkasan) ?></div>
</div>
<?php endif; ?>

<p class="note" style="margin-top:-10px;">Catatan: ini ringkasan otomatis sederhana berbasis frekuensi kata, bukan AI generatif. Untuk hasil yang lebih pintar, nanti bisa disambungkan ke Claude API.</p>

<?php include "includes/footer.php"; ?>
