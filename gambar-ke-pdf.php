<?php
$page_title = "Ubah Gambar ke PDF";
$page_icon_img = "icon/gambar-ke-pdf.png";
include "header.php";
?>

<div class="panel">
    <label>Pilih satu atau beberapa gambar</label>
    <input type="file" id="fileInput" accept="image/*" multiple>
    <button class="btn-action" id="btnConvert"><i class="fa fa-file-export"></i> Konversi ke PDF</button>
    <p class="note" id="statusText"></p>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
document.getElementById('btnConvert').addEventListener('click', async () => {
    const files = document.getElementById('fileInput').files;
    const statusText = document.getElementById('statusText');

    if (!files.length) {
        statusText.textContent = "Pilih gambar dulu ya.";
        return;
    }

    statusText.textContent = "Memproses...";

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF();

    for (let i = 0; i < files.length; i++) {
        const imgData = await readFileAsDataURL(files[i]);
        const img = await loadImage(imgData);

        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const ratio = Math.min(pageWidth / img.width, pageHeight / img.height);
        const w = img.width * ratio;
        const h = img.height * ratio;

        if (i > 0) pdf.addPage();
        pdf.addImage(imgData, 'JPEG', (pageWidth - w) / 2, (pageHeight - h) / 2, w, h);
    }

    pdf.save('hasil_konversi_' + Date.now() + '.pdf');
    statusText.textContent = "Selesai! File PDF sudah terdownload.";
});

function readFileAsDataURL(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}
</script>

<?php include "footer.php"; ?>
