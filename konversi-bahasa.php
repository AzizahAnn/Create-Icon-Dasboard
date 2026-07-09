<?php
$page_title = "Konversi Bahasa";
$page_icon = "fa-language";
require "config.php";
require "vendor/autoload.php";
include "header.php";

const MAX_KARAKTER_TEKS = 500;       // batas untuk form teks langsung
const MAX_KARAKTER_DOKUMEN = 8000;   // batas untuk dokumen yang diupload
const MAX_UKURAN_DOKUMEN = 5 * 1024 * 1024; // 5MB
const EKSTENSI_DOKUMEN_DIIZINKAN = ["txt", "docx", "pdf"];

$bahasa_list = [
    "id" => "Indonesia",
    "en" => "Inggris",
    "ja" => "Jepang",
    "ar" => "Arab",
];

// ==========================================
// FUNGSI BANTUAN
// ==========================================

// Panggil Google Translate untuk SATU potongan teks (idealnya < ~1500 karakter)
function panggilGoogleTranslate(string $teks, string $dari, string $ke, ?string &$error): string
{
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl="
        . urlencode($dari) . "&tl=" . urlencode($ke) . "&dt=t&q=" . urlencode($teks);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => "Mozilla/5.0",
    ]);
    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErrno !== 0) {
        $error = "Tidak bisa terhubung ke layanan terjemahan (" . $curlError . ").";
        return "";
    }
    if ($httpCode !== 200) {
        $error = "Layanan terjemahan sedang bermasalah (HTTP $httpCode). Coba lagi nanti.";
        return "";
    }

    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($json[0]) || !is_array($json[0])) {
        $error = "Gagal membaca hasil terjemahan.";
        return "";
    }

    $potongan = [];
    foreach ($json[0] as $bagian) {
        if (isset($bagian[0])) {
            $potongan[] = $bagian[0];
        }
    }
    return implode("", $potongan);
}

// Pecah teks panjang jadi potongan-potongan kecil (per kalimat) biar aman dikirim ke API
function pecahTeksJadiPotongan(string $teks, int $maxLen): array
{
    $kalimatArr = preg_split('/(?<=[.!?\n])\s+/u', $teks) ?: [$teks];
    $chunks = [];
    $current = "";
    foreach ($kalimatArr as $kalimat) {
        if ($current !== "" && mb_strlen($current) + mb_strlen($kalimat) + 1 > $maxLen) {
            $chunks[] = $current;
            $current = $kalimat;
        } else {
            $current .= ($current === "" ? "" : " ") . $kalimat;
        }
    }
    if (trim($current) !== "") {
        $chunks[] = $current;
    }
    return $chunks;
}

// Terjemahkan teks panjang dengan cara dipecah dulu, lalu digabung lagi
function terjemahkanTeksPanjang(string $teks, string $dari, string $ke, ?string &$error): string
{
    $potongan = pecahTeksJadiPotongan($teks, 1500);
    $hasilGabungan = [];
    foreach ($potongan as $bagian) {
        $hasilBagian = panggilGoogleTranslate($bagian, $dari, $ke, $error);
        if ($error) {
            return "";
        }
        $hasilGabungan[] = $hasilBagian;
    }
    return implode(" ", $hasilGabungan);
}

// Ekstrak teks dari file .txt, .docx, atau .pdf
function ekstrakTeksDokumen(string $path, string $ext, ?string &$error): string
{
    try {
        switch ($ext) {
            case "txt":
                $isi = file_get_contents($path);
                return $isi !== false ? $isi : "";

            case "docx":
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
                $teks = "";
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, "getText")) {
                            $t = $element->getText();
                            if (is_string($t)) {
                                $teks .= $t . "\n";
                            }
                        } elseif (method_exists($element, "getElements")) {
                            foreach ($element->getElements() as $sub) {
                                if (method_exists($sub, "getText")) {
                                    $t = $sub->getText();
                                    if (is_string($t)) {
                                        $teks .= $t . " ";
                                    }
                                }
                            }
                            $teks .= "\n";
                        }
                    }
                }
                return trim($teks);

            case "pdf":
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                return trim($pdf->getText());
        }
    } catch (\Throwable $e) {
        $error = "Gagal membaca isi dokumen: " . $e->getMessage();
        return "";
    }
    return "";
}

// simpan hasil ke tabel arsip, dipakai oleh mode teks maupun mode dokumen
function simpanRiwayat(PDO $pdo, string $namaDokumen, ?string $pathFile, string $isiAsli, string $isiHasil, string $dari, string $ke): void
{
    try {
        $stmtKategori = $pdo->prepare("SELECT id FROM kategori WHERE nama_kategori = ?");
        $stmtKategori->execute(["Konversi Bahasa"]);
        $kategoriId = $stmtKategori->fetchColumn();

        if ($kategoriId) {
            $stmt = $pdo->prepare(
                "INSERT INTO arsip (nama_dokumen, kategori_id, file_path, isi_asli, isi_hasil, bahasa_asal, bahasa_tujuan, uploaded_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $namaDokumen,
                $kategoriId,
                $pathFile,
                $isiAsli,
                $isiHasil,
                $dari,
                $ke,
                $_SESSION["user_id"] ?? null,
            ]);
        } else {
            error_log("Kategori 'Konversi Bahasa' belum ada di tabel kategori.");
        }
    } catch (PDOException $e) {
        error_log("Gagal simpan riwayat konversi bahasa: " . $e->getMessage());
    }
}

// ==========================================
// MODE 1: TERJEMAHKAN TEKS LANGSUNG
// ==========================================
$hasil = "";
$error = "";
$teks = "";
$dari = "id";
$ke = "en";

// ==========================================
// MODE 2: TERJEMAHKAN DOKUMEN
// ==========================================
$hasilDok = "";
$isiAsliDok = "";
$errorDok = "";
$namaFileDok = "";
$dariDok = "id";
$keDok = "en";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "teks") {
    $teks = trim($_POST["teks"] ?? "");
    $dari = $_POST["dari"] ?? "id";
    $ke = $_POST["ke"] ?? "en";

    if (!array_key_exists($dari, $bahasa_list) || !array_key_exists($ke, $bahasa_list)) {
        $error = "Pilihan bahasa tidak valid.";
    } elseif ($teks === "") {
        $error = "Teks tidak boleh kosong.";
    } elseif ($dari === $ke) {
        $error = "Bahasa asal dan tujuan tidak boleh sama.";
    } elseif (mb_strlen($teks) > MAX_KARAKTER_TEKS) {
        $error = "Teks terlalu panjang. Maksimal " . MAX_KARAKTER_TEKS . " karakter per permintaan.";
    } else {
        $hasil = panggilGoogleTranslate($teks, $dari, $ke, $error);
        if (!$error && $hasil !== "") {
            simpanRiwayat($pdo, mb_substr($teks, 0, 50) . (mb_strlen($teks) > 50 ? "..." : ""), null, $teks, $hasil, $dari, $ke);
        } elseif (!$error && $hasil === "") {
            $error = "Gagal menerjemahkan. Coba lagi.";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "dokumen" && isset($_FILES["dokumen"])) {
    $dariDok = $_POST["dari_dok"] ?? "id";
    $keDok = $_POST["ke_dok"] ?? "en";

    $file = $_FILES["dokumen"];
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

    if (!array_key_exists($dariDok, $bahasa_list) || !array_key_exists($keDok, $bahasa_list)) {
        $errorDok = "Pilihan bahasa tidak valid.";
    } elseif ($dariDok === $keDok) {
        $errorDok = "Bahasa asal dan tujuan tidak boleh sama.";
    } elseif ($file["error"] !== UPLOAD_ERR_OK) {
        $errorDok = "Gagal mengupload file.";
    } elseif (!in_array($ext, EKSTENSI_DOKUMEN_DIIZINKAN)) {
        $errorDok = "Format tidak didukung. Gunakan .txt, .docx, atau .pdf.";
    } elseif ($file["size"] > MAX_UKURAN_DOKUMEN) {
        $errorDok = "Ukuran file terlalu besar (maks. 5MB).";
    } else {
        $namaFileDok = $file["name"];
        $isiAsliDok = ekstrakTeksDokumen($file["tmp_name"], $ext, $errorDok);

        if (!$errorDok && trim($isiAsliDok) === "") {
            $errorDok = "Tidak ada teks yang terbaca dari dokumen ini (mungkin dokumen kosong atau berupa hasil scan/gambar).";
        } elseif (!$errorDok && mb_strlen($isiAsliDok) > MAX_KARAKTER_DOKUMEN) {
            $errorDok = "Teks dalam dokumen terlalu panjang (maks. " . MAX_KARAKTER_DOKUMEN . " karakter, dokumen ini "
                . mb_strlen($isiAsliDok) . " karakter). Coba pakai dokumen yang lebih pendek.";
        }

        if (!$errorDok) {
            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newName = "dokumen_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            $target = $uploadDir . $newName;
            move_uploaded_file($file["tmp_name"], $target);

            $hasilDok = terjemahkanTeksPanjang($isiAsliDok, $dariDok, $keDok, $errorDok);

            if (!$errorDok && $hasilDok !== "") {
                simpanRiwayat($pdo, $namaFileDok, $target, $isiAsliDok, $hasilDok, $dariDok, $keDok);
            } elseif (!$errorDok && $hasilDok === "") {
                $errorDok = "Gagal menerjemahkan isi dokumen. Coba lagi.";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "hapus_riwayat") {
    $idHapus = (int) ($_POST["id"] ?? 0);
    if ($idHapus > 0) {
        try {
            $stmt = $pdo->prepare(
                "DELETE arsip FROM arsip
                 JOIN kategori ON arsip.kategori_id = kategori.id
                 WHERE arsip.id = ? AND kategori.nama_kategori = 'Konversi Bahasa'"
            );
            $stmt->execute([$idHapus]);
        } catch (PDOException $e) {
            error_log("Gagal hapus riwayat: " . $e->getMessage());
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "ubah_riwayat") {
    $idUbah = (int) ($_POST["id"] ?? 0);
    $hasilBaru = trim($_POST["isi_hasil_baru"] ?? "");
    if ($idUbah > 0 && $hasilBaru !== "") {
        try {
            $stmt = $pdo->prepare(
                "UPDATE arsip a
                 JOIN kategori k ON a.kategori_id = k.id
                 SET a.isi_hasil = ?
                 WHERE a.id = ? AND k.nama_kategori = 'Konversi Bahasa'"
            );
            $stmt->execute([$hasilBaru, $idUbah]);
        } catch (PDOException $e) {
            error_log("Gagal ubah riwayat: " . $e->getMessage());
        }
    }
}

// ambil 10 riwayat terjemahan terakhir (teks maupun dokumen)
$riwayat = [];
try {
    $stmt = $pdo->query(
        "SELECT arsip.* FROM arsip
         JOIN kategori ON arsip.kategori_id = kategori.id
         WHERE kategori.nama_kategori = 'Konversi Bahasa'
         ORDER BY arsip.created_at DESC LIMIT 10"
    );
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Gagal ambil riwayat konversi bahasa: " . $e->getMessage());
}
?>

<div class="panel">
    <label>Mau terjemahin teks langsung, atau upload dokumen?</label>
    <div style="display:flex; gap:10px;">
        <button type="button" class="btn-action" id="btnPilihTeks" onclick="pilihMode('teks')">
            <i class="fa fa-pen"></i> Teks
        </button>
        <button type="button" class="btn-action" id="btnPilihDokumen" style="background:#334155; color:#fff;" onclick="pilihMode('dokumen')">
            <i class="fa fa-file-import"></i> Dokumen
        </button>
    </div>
</div>

<div id="modeTeks" style="display:none;">

<div class="panel">
    <form method="POST">
        <input type="hidden" name="aksi" value="teks">
        <label>Teks yang mau diterjemahkan (maks. <?= MAX_KARAKTER_TEKS ?> karakter)</label>
        <textarea name="teks" rows="5" maxlength="<?= MAX_KARAKTER_TEKS ?>" required><?= htmlspecialchars($teks) ?></textarea>

        <label>Dari Bahasa</label>
        <select name="dari">
            <?php foreach ($bahasa_list as $kode => $nama): ?>
                <option value="<?= $kode ?>" <?= $dari === $kode ? "selected" : "" ?>><?= $nama ?></option>
            <?php endforeach; ?>
        </select>

        <label>Ke Bahasa</label>
        <select name="ke">
            <?php foreach ($bahasa_list as $kode => $nama): ?>
                <option value="<?= $kode ?>" <?= $ke === $kode ? "selected" : "" ?>><?= $nama ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-action"><i class="fa fa-language"></i> Terjemahkan</button>
    </form>
</div>

<?php if ($error): ?>
<div class="panel">
    <p class="note" style="color:#fca5a5;"><?= htmlspecialchars($error) ?></p>
</div>
<?php endif; ?>

<?php if ($hasil): ?>
<div class="panel">
    <label>Hasil Terjemahan</label>
    <div class="result-box"><?= htmlspecialchars($hasil) ?></div>
</div>
<?php endif; ?>

</div>

<div id="modeDokumen" style="display:none;">

<!-- ========================================== -->
<!-- MODE DOKUMEN                                -->
<!-- ========================================== -->
<div class="panel">
    <label>Upload dokumen (.txt, .docx, .pdf — maks. 5MB, maks. <?= MAX_KARAKTER_DOKUMEN ?> karakter teks)</label>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="dokumen">
        <input type="file" name="dokumen" accept=".txt,.docx,.pdf" required>

        <label>Dari Bahasa</label>
        <select name="dari_dok">
            <?php foreach ($bahasa_list as $kode => $nama): ?>
                <option value="<?= $kode ?>" <?= $dariDok === $kode ? "selected" : "" ?>><?= $nama ?></option>
            <?php endforeach; ?>
        </select>

        <label>Ke Bahasa</label>
        <select name="ke_dok">
            <?php foreach ($bahasa_list as $kode => $nama): ?>
                <option value="<?= $kode ?>" <?= $keDok === $kode ? "selected" : "" ?>><?= $nama ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-action"><i class="fa fa-file-import"></i> Terjemahkan Dokumen</button>
    </form>
</div>

<?php if ($errorDok): ?>
<div class="panel">
    <p class="note" style="color:#fca5a5;"><?= htmlspecialchars($errorDok) ?></p>
</div>
<?php endif; ?>

<?php if ($hasilDok): ?>
<div class="panel">
    <label>Teks Asli dari "<?= htmlspecialchars($namaFileDok) ?>"</label>
    <div class="result-box"><?= htmlspecialchars($isiAsliDok) ?></div>

    <label style="margin-top:15px;">Hasil Terjemahan</label>
    <div class="result-box" id="hasilDokBox"><?= htmlspecialchars($hasilDok) ?></div>

    <button type="button" class="btn-action" style="margin-top:10px;" onclick="unduhHasilDokumen()">
        <i class="fa fa-download"></i> Unduh Hasil (.txt)
    </button>
</div>
<script>
function unduhHasilDokumen() {
    const teks = document.getElementById('hasilDokBox').textContent;
    const blob = new Blob([teks], { type: 'text/plain;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'terjemahan_' + Date.now() + '.txt';
    a.click();
}
</script>
<?php endif; ?>

</div>

<script>
function pilihMode(mode) {
    const modeTeks = document.getElementById('modeTeks');
    const modeDokumen = document.getElementById('modeDokumen');
    const btnTeks = document.getElementById('btnPilihTeks');
    const btnDokumen = document.getElementById('btnPilihDokumen');

    if (mode === 'teks') {
        modeTeks.style.display = 'block';
        modeDokumen.style.display = 'none';
        btnTeks.style.background = '#38bdf8';
        btnTeks.style.color = '#000';
        btnDokumen.style.background = '#334155';
        btnDokumen.style.color = '#fff';
    } else {
        modeTeks.style.display = 'none';
        modeDokumen.style.display = 'block';
        btnDokumen.style.background = '#38bdf8';
        btnDokumen.style.color = '#000';
        btnTeks.style.background = '#334155';
        btnTeks.style.color = '#fff';
    }
}

// Kalau abis submit form (ada hasil/error), otomatis tampilkan mode yang barusan dipakai
<?php if ($error || $hasil): ?>
    pilihMode('teks');
<?php elseif ($errorDok || $hasilDok): ?>
    pilihMode('dokumen');
<?php else: ?>
    pilihMode('teks'); // default pertama kali buka halaman
<?php endif; ?>
</script>

<div class="panel">
    <label>Riwayat Terjemahan Terakhir</label>
    <?php if (empty($riwayat)): ?>
        <p class="note">Belum ada riwayat.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Sumber</th><th>Teks Asli</th><th>Hasil</th><th>Bahasa</th><th>Waktu</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $r): ?>
                <tr>
                    <td><?= $r["file_path"] ? "📄 Dokumen" : "✍️ Teks" ?></td>
                    <td><?= htmlspecialchars(mb_substr($r["isi_asli"], 0, 60)) ?><?= mb_strlen($r["isi_asli"]) > 60 ? "..." : "" ?></td>
                    <td id="hasilTampil<?= $r["id"] ?>"><?= htmlspecialchars(mb_substr($r["isi_hasil"], 0, 60)) ?><?= mb_strlen($r["isi_hasil"]) > 60 ? "..." : "" ?></td>
                    <td><?= htmlspecialchars($bahasa_list[$r["bahasa_asal"]] ?? $r["bahasa_asal"]) ?> → <?= htmlspecialchars($bahasa_list[$r["bahasa_tujuan"]] ?? $r["bahasa_tujuan"]) ?></td>
                    <td><?= htmlspecialchars($r["created_at"]) ?></td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn-action" style="padding:6px 10px; font-size:12px;" onclick="toggleUbah(<?= $r["id"] ?>)">
                            <i class="fa fa-pen"></i> Ubah
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin mau hapus riwayat ini?');">
                            <input type="hidden" name="aksi" value="hapus_riwayat">
                            <input type="hidden" name="id" value="<?= $r["id"] ?>">
                            <button type="submit" class="btn-action" style="padding:6px 10px; font-size:12px; background:#ef4444; color:#fff;">
                                <i class="fa fa-trash"></i> Hapus
                            </button>
                        </form>
                    </td>
                </tr>
                <tr id="barisUbah<?= $r["id"] ?>" style="display:none;">
                    <td colspan="6">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="aksi" value="ubah_riwayat">
                            <input type="hidden" name="id" value="<?= $r["id"] ?>">
                            <label>Ubah hasil terjemahan</label>
                            <textarea name="isi_hasil_baru" rows="3"><?= htmlspecialchars($r["isi_hasil"]) ?></textarea>
                            <button type="submit" class="btn-action"><i class="fa fa-save"></i> Simpan Perubahan</button>
                            <button type="button" class="btn-action" style="background:#334155; color:#fff;" onclick="toggleUbah(<?= $r["id"] ?>)">Batal</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <script>
        function toggleUbah(id) {
            const baris = document.getElementById('barisUbah' + id);
            baris.style.display = baris.style.display === 'none' ? 'table-row' : 'none';
        }
        </script>
    <?php endif; ?>
</div>

<p class="note" style="margin-top:-10px;">Menggunakan layanan terjemahan Google Translate.</p>

<?php include "footer.php"; ?>