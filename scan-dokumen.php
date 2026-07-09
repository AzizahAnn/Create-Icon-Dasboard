<?php
$page_title = "Scan Dokumen (Gambar ke Teks)";
$page_icon_img = "icon/scan-dokumen.png";
include "header.php";
?>

<div class="panel">
    <label>Mode Scan</label>
    <div style="margin-bottom:15px;">
        <button class="btn-action" id="btnModeCamera"><i class="fa fa-camera"></i> Pakai Kamera</button>
        <button class="btn-action" id="btnModeUpload" style="background:#334155; color:#fff;"><i class="fa fa-upload"></i> Upload Gambar</button>
    </div>

    <!-- MODE KAMERA -->
    <div id="cameraMode">
        <div style="position:relative; max-width:600px; margin:0 auto;">
            <video id="video" autoplay playsinline style="width:100%; border-radius:10px; display:block;"></video>
            <div id="guideBox" style="position:absolute; border:3px solid #22c55e; top:12%; left:8%; width:84%; height:76%; border-radius:6px; pointer-events:none;"></div>
        </div>
        <p class="note" style="text-align:center;">Posisikan dokumen supaya masuk ke dalam kotak hijau, lalu klik tombol di bawah.</p>
        <div style="text-align:center;">
            <button class="btn-action" id="btnCapture"><i class="fa fa-camera"></i> SCAN DOKUMEN</button>
        </div>
    </div>

    <!-- MODE UPLOAD -->
    <div id="uploadMode" style="display:none;">
        <label>Pilih gambar dokumen (JPG/PNG)</label>
        <input type="file" id="fileInput" accept="image/*">
        <button class="btn-action" id="btnScanFile"><i class="fa fa-magic"></i> Mulai Scan</button>
    </div>

    <p class="note" id="statusText"></p>
</div>

<div class="panel" id="previewPanel" style="display:none;">
    <label>Hasil Scan (diproses jadi hitam-putih)</label>
    <canvas id="canvasResult" style="max-width:100%; border-radius:10px; border:1px solid #334155;"></canvas>
</div>

<div class="panel">
    <label>Hasil Teks Terdeteksi</label>
    <div class="result-box" id="resultText">Belum ada hasil. Scan atau upload dokumen dulu.</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
const cameraMode = document.getElementById('cameraMode');
const uploadMode = document.getElementById('uploadMode');
const btnModeCamera = document.getElementById('btnModeCamera');
const btnModeUpload = document.getElementById('btnModeUpload');
const video = document.getElementById('video');
const statusText = document.getElementById('statusText');
const resultText = document.getElementById('resultText');
const canvasResult = document.getElementById('canvasResult');
const previewPanel = document.getElementById('previewPanel');

let stream = null;

async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
        video.srcObject = stream;
    } catch (err) {
        statusText.textContent = "Tidak bisa mengakses kamera. Pastikan browser diizinkan akses kamera.";
        console.error(err);
    }
}

function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
}

btnModeCamera.addEventListener('click', () => {
    cameraMode.style.display = "block";
    uploadMode.style.display = "none";
    btnModeCamera.style.background = "#38bdf8";
    btnModeUpload.style.background = "#334155";
    startCamera();
});

btnModeUpload.addEventListener('click', () => {
    cameraMode.style.display = "none";
    uploadMode.style.display = "block";
    btnModeUpload.style.background = "#38bdf8";
    btnModeCamera.style.background = "#334155";
    stopCamera();
});

// mulai dengan mode kamera aktif
startCamera();

// Proses gambar: grayscale + threshold biar mirip hasil scan dokumen asli
function prosesJadiScan(canvas) {
    const ctx = canvas.getContext('2d');
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imgData.data;

    for (let i = 0; i < data.length; i += 4) {
        const gray = 0.3 * data[i] + 0.59 * data[i+1] + 0.11 * data[i+2];
        // threshold sederhana biar kontras tinggi kayak hasil scan
        const value = gray > 150 ? 255 : (gray < 90 ? 0 : gray);
        data[i] = data[i+1] = data[i+2] = value;
    }
    ctx.putImageData(imgData, 0, 0);
}

async function jalankanOCR(canvas) {
    statusText.textContent = "Mengenali teks...";
    resultText.textContent = "";
    try {
        const { data: { text } } = await Tesseract.recognize(canvas, 'ind+eng', {
            logger: m => {
                if (m.status === 'recognizing text') {
                    statusText.textContent = "Mengenali teks... " + Math.round(m.progress * 100) + "%";
                }
            }
        });
        resultText.textContent = text.trim() || "(Tidak ada teks terdeteksi, coba foto ulang dengan pencahayaan lebih terang)";
        statusText.textContent = "Selesai!";
    } catch (err) {
        statusText.textContent = "Terjadi error saat memproses gambar.";
        console.error(err);
    }
}

// Tombol capture dari kamera
document.getElementById('btnCapture').addEventListener('click', async () => {
    if (!video.videoWidth) {
        statusText.textContent = "Kamera belum siap, coba tunggu sebentar.";
        return;
    }
    canvasResult.width = video.videoWidth;
    canvasResult.height = video.videoHeight;
    const ctx = canvasResult.getContext('2d');
    ctx.drawImage(video, 0, 0);

    prosesJadiScan(canvasResult);
    previewPanel.style.display = "block";

    await jalankanOCR(canvasResult);
});

// Tombol scan dari file upload
document.getElementById('btnScanFile').addEventListener('click', async () => {
    const fileInput = document.getElementById('fileInput');
    if (!fileInput.files.length) {
        statusText.textContent = "Pilih gambar dulu ya.";
        return;
    }

    const img = new Image();
    img.onload = async () => {
        canvasResult.width = img.width;
        canvasResult.height = img.height;
        const ctx = canvasResult.getContext('2d');
        ctx.drawImage(img, 0, 0);

        prosesJadiScan(canvasResult);
        previewPanel.style.display = "block";

        await jalankanOCR(canvasResult);
    };
    img.src = URL.createObjectURL(fileInput.files[0]);
});
</script>

<?php include "footer.php"; ?>
