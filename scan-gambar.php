<?php
$page_title = "Scan Gambar";
$page_icon = "fa-camera";
include "header.php";

$msg = "";
$allowed = ["jpg", "jpeg", "png", "webp"];

/* HAPUS FILE RIWAYAT */
if (isset($_GET["hapus"])) {
    $fileHapus = basename($_GET["hapus"]); // basename biar aman dari path traversal
    $targetHapus = "uploads/" . $fileHapus;
 
    // Validasi: hanya boleh hapus file yang memang pola scan_*
    if (preg_match('/^scan_\d{8}_\d{6}\.(jpg|jpeg|png|webp)$/i', $fileHapus) && file_exists($targetHapus)) {
        unlink($targetHapus);
        header("Location: scan-gambar.php?hapus_sukses=1");
        exit;
    } else {
        $msg = "File tidak ditemukan atau tidak valid untuk dihapus.";
    }
}
/* SIMPAN HASIL SCAN DARI KAMERA (base64)*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["gambar_base64"])) {
    $dataUrl = $_POST["gambar_base64"];

    if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $dataUrl, $type)) {
        $ekstensi = $type[1] === "jpeg" ? "jpg" : $type[1];
        $dataBersih = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $dataDecode = base64_decode($dataBersih);

        if ($dataDecode !== false) {
            if (!is_dir("uploads")) {
                mkdir("uploads", 0755, true);
            }
            $newName = "scan_" . date("Ymd_His") . "." . $ekstensi;
            $target = "uploads/" . $newName;

            if (file_put_contents($target, $dataDecode)) {
                $msg = "Gambar dari kamera berhasil disimpan sebagai <b>$newName</b>";
            } else {
                $msg = "Gagal menyimpan gambar ke server.";
            }
        } else {
            $msg = "Data gambar tidak valid.";
        }
    } else {
        $msg = "Format gambar dari kamera tidak dikenali.";
    }
}

/* SIMPAN HASIL SCAN DARI UPLOAD FILE */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["gambar"])) {
    $ext = strtolower(pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "webp"];
    if (in_array($ext, $allowed)) {
        if (!is_dir("uploads")) {
            mkdir("uploads", 0755, true);
        }
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
$totalScan = count($files);
?>

<div class="panel">
    <label>Ambil Gambar via Kamera</label>

    <div style="position:relative; max-width:500px;">
        <video id="videoKamera" autoplay playsinline style="width:100%; border-radius:10px; background:#000;"></video>
        <canvas id="canvasFoto" style="width:100%; border-radius:10px; display:none;"></canvas>
    </div>

    <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <button type="button" class="btn-action" id="btnNyalakan" onclick="nyalakanKamera()">
            <i class="fa fa-video"></i> Nyalakan Kamera
        </button>
        <button type="button" class="btn-action" id="btnAmbil" onclick="ambilFoto()" style="display:none;">
            <i class="fa fa-camera"></i> Ambil Foto
        </button>
        <button type="button" class="btn-action" id="btnUlang" onclick="ulangiScan()" style="display:none;">
            <i class="fa fa-rotate-left"></i> Ulangi Scan
        </button>
        <span id="counterScan" style="color:#94a3b8; font-size:13px;">Sesi ini: 0 kali scan</span>
    </div>

    <form method="POST" id="formSimpanamera" style="margin-top:12px; display:none;">
        <input type="hidden" name="gambar_base64" id="inputBase64">
        <button type="submit" class="btn-action">
            <i class="fa fa-save"></i> Simpan Hasil Scan
        </button>
    </form>
</div>

<div class="panel">
    <label>Atau Upload Gambar dari Komputer</label>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="gambar" accept="image/*" required>
        <button type="submit" class="btn-action"><i class="fa fa-upload"></i> Upload &amp; Simpan</button>
    </form>

 <?php if ($msg): ?>
        <p class="note"><?= $msg ?></p>
    <?php endif; ?>
    <?php if (isset($_GET["hapus_sukses"])): ?>
        <p class="note">File berhasil dihapus.</p>
    <?php endif; ?>

<div class="panel">
    <label>Riwayat Hasil Scan (Total: <?= $totalScan ?> file)</label>
    <?php if (empty($files)): ?>
        <p class="note">Belum ada gambar yang discan.</p>
    <?php else: ?>
        <div class="file-list" style="display:flex; flex-wrap:wrap; gap:15px;">
            <?php foreach ($files as $f): ?>
                <div style="position:relative; width:150px;">
                    <img src="<?= htmlspecialchars($f) ?>" alt="scan" style="width:100%; border-radius:8px; display:block;">
                    <a href="scan-gambar.php?hapus=<?= urlencode(basename($f)) ?>"
                       onclick="return confirm('Yakin mau hapus file ini?');"
                       style="position:absolute; top:6px; right:6px; background:#ef4444; color:#fff; border-radius:6px; width:26px; height:26px; display:flex; align-items:center; justify-content:center; text-decoration:none;">
                        <i class="fa fa-trash" style="font-size:12px;"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
let streamKamera = null;
let jumlahScanSesi = 0;

async function nyalakanKamera() {
    try {
        streamKamera = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
        const video = document.getElementById('videoKamera');
        video.srcObject = streamKamera;
        video.style.display = 'block';
        document.getElementById('canvasFoto').style.display = 'none';

        document.getElementById('btnNyalakan').style.display = 'none';
        document.getElementById('btnAmbil').style.display = 'inline-block';
        document.getElementById('btnUlang').style.display = 'none';
        document.getElementById('formSimpan').style.display = 'none';
    } catch (err) {
        alert('Tidak bisa mengakses kamera. Pastikan izin kamera sudah diberikan.\nDetail: ' + err.message);
    }
}

function ambilFoto() {
    const video = document.getElementById('videoKamera');
    const canvas = document.getElementById('canvasFoto');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    const dataUrl = canvas.toDataURL('image/png');
    document.getElementById('inputBase64').value = dataUrl;

    video.style.display = 'none';
    canvas.style.display = 'block';

    document.getElementById('btnAmbil').style.display = 'none';
    document.getElementById('btnUlang').style.display = 'inline-block';
    document.getElementById('formSimpan').style.display = 'block';

    jumlahScanSesi++;
    document.getElementById('counterScan').innerText = 'Sesi ini: ' + jumlahScanSesi + ' kali scan';

    hentikanKamera();
}

function ulangiScan() {
    document.getElementById('formSimpan').style.display = 'none';
    document.getElementById('btnUlang').style.display = 'none';
    nyalakanKamera();
}

function hentikanKamera() {
    if (streamKamera) {
        streamKamera.getTracks().forEach(track => track.stop());
        streamKamera = null;
    }
}
</script>

<?php include "footer.php"; ?>
