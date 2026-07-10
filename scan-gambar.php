<?php
$page_title = "Scan Gambar";
$page_icon_img = "icon/scan-gambar.png";

$msg = "";
$hasilGambar = null;
$allowed = ["jpg", "jpeg", "png", "webp"];

/* HAPUS FILE RIWAYAT */
if (isset($_GET["hapus"])) {
    $fileHapus = basename($_GET["hapus"]);
    $targetHapus = "uploads/" . $fileHapus;

    if (preg_match('/^scan_\d{8}_\d{6}\.(jpg|jpeg|png|webp)$/i', $fileHapus) && file_exists($targetHapus)) {
        unlink($targetHapus);
        header("Location: scan-gambar.php?hapus_sukses=1");
        exit;
    } else {
        $msg = "File tidak ditemukan atau tidak valid untuk dihapus.";
    }
}

/* SIMPAN HASIL SCAN DARI KAMERA (base64) */
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
                $hasilGambar = $target;
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
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["gambar"]) && $_FILES["gambar"]["error"] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES["gambar"]["name"], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        if (!is_dir("uploads")) {
            mkdir("uploads", 0755, true);
        }
        $newName = "scan_" . date("Ymd_His") . "." . $ext;
        $target = "uploads/" . $newName;

        if (move_uploaded_file($_FILES["gambar"]["tmp_name"], $target)) {
            $msg = "Gambar berhasil diupload sebagai <b>$newName</b>";
            $hasilGambar = $target;
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

<?php include "header.php"; ?>

<style>
.mode-switch {
    display:flex;
    gap:10px;
    margin-bottom:20px;
}
.mode-btn {
    flex:1;
    padding:12px;
    border:1px solid #334155;
    background:#0f172a;
    color:#cbd5e1;
    border-radius:10px;
    cursor:pointer;
    font-size:14px;
    text-align:center;
    transition:0.2s;
}
.mode-btn.active {
    background:#38bdf8;
    color:#000;
    border-color:#38bdf8;
    font-weight:bold;
}
.slide-panel { display:none; }
.slide-panel.active { display:block; }

.hasil-inner {
    text-align:center;
}

.hasil-badge {
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:rgba(34,197,94,0.15);
    color:#4ade80;
    padding:6px 16px;
    border-radius:20px;
    font-size:13px;
    font-weight:600;
    margin-bottom:15px;
}

.hasil-inner img {
    max-width:480px;
    width:100%;
    border-radius:12px;
    display:block;
    margin:0 auto;
}

.hasil-download {
    margin-top:16px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 22px;
    background:#38bdf8;
    color:#000;
    border-radius:10px;
    text-decoration:none;
    font-weight:600;
    font-size:14px;
}
.hasil-download:hover {
    background:#0ea5e9;
}
</style>

<div class="panel">
    <div class="mode-switch">
        <div class="mode-btn active" id="btnModeKamera" onclick="pilihMode('kamera')">
            <i class="fa fa-camera"></i> Kamera
        </div>
        <div class="mode-btn" id="btnModeUpload" onclick="pilihMode('upload')">
            <i class="fa fa-upload"></i> Upload dari Komputer
        </div>
    </div>

    <!-- SLIDE: KAMERA -->
    <div class="slide-panel active" id="slideKamera">
        <div style="position:relative; max-width:500px; margin:0 auto;">
            <video id="videoKamera" autoplay playsinline style="width:100%; border-radius:10px; background:#000;"></video>
            <canvas id="canvasFoto" style="width:100%; border-radius:10px; display:none;"></canvas>
        </div>

        <div style="margin-top:12px; display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
            <button type="button" class="btn-action" id="btnNyalakan" onclick="nyalakanKamera()">
                <i class="fa fa-video"></i> Nyalakan Kamera
            </button>
            <button type="button" class="btn-action" id="btnAmbil" onclick="ambilFoto()" style="display:none;">
                <i class="fa fa-camera"></i> Ambil Foto
            </button>
            <button type="button" class="btn-action" id="btnUlang" onclick="ulangiScan()" style="display:none;">
                <i class="fa fa-rotate-left"></i> Ulangi Scan
            </button>
        </div>

        <div style="text-align:center; margin-top:8px;">
            <span id="counterScan" style="color:#94a3b8; font-size:13px;">Sesi ini: 0 kali scan</span>
        </div>

        <div style="text-align:center;">
            <form method="POST" id="formSimpanKamera" style="margin-top:12px; display:none;">
                <input type="hidden" name="gambar_base64" id="inputBase64">
                <button type="submit" class="btn-action">
                    <i class="fa fa-save"></i> Simpan Hasil Scan
                </button>
            </form>
        </div>
    </div>

    <!-- SLIDE: UPLOAD -->
    <div class="slide-panel" id="slideUpload">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="gambar" accept="image/*" required>
            <button type="submit" class="btn-action"><i class="fa fa-upload"></i> Upload &amp; Simpan</button>
        </form>
    </div>

    <?php if ($msg && !$hasilGambar): ?>
        <p class="note" style="margin-top:15px;"><?= $msg ?></p>
    <?php endif; ?>
    <?php if (isset($_GET["hapus_sukses"])): ?>
        <p class="note" style="margin-top:15px;">File berhasil dihapus.</p>
    <?php endif; ?>
</div>

<?php if ($hasilGambar): ?>
<div class="panel">
    <div class="hasil-inner">
        <div class="hasil-badge">
            <i class="fa fa-check-circle"></i> Berhasil Discan
        </div>
        <img src="<?= htmlspecialchars($hasilGambar) ?>" alt="Hasil gambar yang baru discan">
        <div>
            <a href="<?= htmlspecialchars($hasilGambar) ?>" download class="hasil-download">
                <i class="fa fa-download"></i> Download Hasil
            </a>
        </div>
    </div>
</div>
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
                    <a href="<?= htmlspecialchars($f) ?>" download
                       style="position:absolute; top:6px; left:6px; background:#38bdf8; color:#000; border-radius:6px; width:26px; height:26px; display:flex; align-items:center; justify-content:center; text-decoration:none;">
                        <i class="fa fa-download" style="font-size:12px;"></i>
                    </a>
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

function pilihMode(mode) {
    document.getElementById('btnModeKamera').classList.toggle('active', mode === 'kamera');
    document.getElementById('btnModeUpload').classList.toggle('active', mode === 'upload');
    document.getElementById('slideKamera').classList.toggle('active', mode === 'kamera');
    document.getElementById('slideUpload').classList.toggle('active', mode === 'upload');

    if (mode !== 'kamera') {
        hentikanKamera();
    }
}

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
        document.getElementById('formSimpanKamera').style.display = 'none';
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
    document.getElementById('formSimpanKamera').style.display = 'block';

    jumlahScanSesi++;
    document.getElementById('counterScan').innerText = 'Sesi ini: ' + jumlahScanSesi + ' kali scan';

    hentikanKamera();
}

function ulangiScan() {
    document.getElementById('formSimpanKamera').style.display = 'none';
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