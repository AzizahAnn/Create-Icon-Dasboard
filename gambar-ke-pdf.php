<?php
$page_title = "Ubah Gambar ke PDF";
$page_icon_img = "icon/impor-gambar-ke-pdf.png";

require "config.php";

$username = $_SESSION["username"] ?? "Admin";

$upload_dir = "uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ==== PASTIKAN KATEGORI "Ubah Gambar ke PDF" ADA DI DATABASE ====
$kategoriId = null;
try {
    $stmtKategori = $pdo->prepare("SELECT id FROM kategori WHERE nama_kategori = ? LIMIT 1");
    $stmtKategori->execute(["Ubah Gambar ke PDF"]);
    $kategoriId = $stmtKategori->fetchColumn();

    if (!$kategoriId) {
        $insertKategori = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $insertKategori->execute(["Ubah Gambar ke PDF"]);
        $kategoriId = $pdo->lastInsertId();
    }
} catch (Exception $e) {
    // Diamkan, akan ditangani saat proses simpan
}

// ==== AJAX: SIMPAN PDF HASIL KONVERSI KE RIWAYAT ====
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["ajax_action"] ?? "") === "simpan" && isset($_FILES["pdf_file"])) {
    header("Content-Type: application/json");

    if ($_FILES["pdf_file"]["error"] !== UPLOAD_ERR_OK) {
        echo json_encode(["ok" => false, "message" => "Gagal mengunggah file PDF ke server."]);
        exit;
    }

    $newName = "hasil_konversi_" . date("Ymd_His") . "_" . substr(md5(uniqid()), 0, 6) . ".pdf";
    $target  = $upload_dir . $newName;

    if (!move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target)) {
        echo json_encode(["ok" => false, "message" => "Gagal menyimpan file PDF di server."]);
        exit;
    }

    try {
        $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmtUser->execute([$username]);
        $userId = $stmtUser->fetchColumn();
        $userId = $userId ?: null;

        $ukuranKb = round(filesize($target) / 1024, 1);

        $stmt = $pdo->prepare("INSERT INTO arsip
            (judul, kategori_id, jenis_arsip, file_path, format_file, ukuran_file, uploaded_by, status, sifat)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'Biasa')");
        $stmt->execute([
            $newName,
            $kategoriId,
            "Dokumen",
            $target,
            "pdf",
            $ukuranKb,
            $userId,
        ]);

        echo json_encode([
            "ok"         => true,
            "id"         => $pdo->lastInsertId(),
            "judul"      => $newName,
            "file_path"  => $target,
            "ukuran"     => $ukuranKb,
            "created_at" => date("Y-m-d H:i:s"),
        ]);
    } catch (Exception $e) {
        echo json_encode(["ok" => false, "message" => "File tersimpan, tapi gagal dicatat ke arsip: " . $e->getMessage()]);
    }
    exit;
}

// ==== AJAX: HAPUS RIWAYAT (FILE + RECORD DATABASE) ====
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["ajax_action"] ?? "") === "hapus") {
    header("Content-Type: application/json");

    $hapusId = (int) ($_POST["id"] ?? 0);
    if ($hapusId <= 0) {
        echo json_encode(["ok" => false, "message" => "ID tidak valid."]);
        exit;
    }

    try {
        $stmtCek = $pdo->prepare("SELECT file_path FROM arsip WHERE id = ? AND kategori_id = ?");
        $stmtCek->execute([$hapusId, $kategoriId]);
        $rowHapus = $stmtCek->fetch();

        if ($rowHapus) {
            if (!empty($rowHapus["file_path"]) && file_exists($rowHapus["file_path"])) {
                unlink($rowHapus["file_path"]);
            }
            $stmtDel = $pdo->prepare("DELETE FROM arsip WHERE id = ?");
            $stmtDel->execute([$hapusId]);
            echo json_encode(["ok" => true]);
        } else {
            echo json_encode(["ok" => false, "message" => "Data riwayat tidak ditemukan."]);
        }
    } catch (Exception $e) {
        echo json_encode(["ok" => false, "message" => "Gagal menghapus: " . $e->getMessage()]);
    }
    exit;
}

// ==== AMBIL RIWAYAT PDF YANG SUDAH DIBUAT ====
$riwayat = [];
if ($kategoriId) {
    try {
        $stmtRiwayat = $pdo->prepare("SELECT id, judul, file_path, ukuran_file, created_at FROM arsip WHERE kategori_id = ? ORDER BY created_at DESC");
        $stmtRiwayat->execute([$kategoriId]);
        $riwayat = $stmtRiwayat->fetchAll();
    } catch (Exception $e) {
        // Diamkan kalau gagal ambil riwayat
    }
}

include "header.php";
?>

<div class="panel">
    <label>Pilih satu atau beberapa gambar</label>
    <input type="file" id="fileInput" accept="image/*" multiple>
    <button class="btn-action" id="btnConvert"><i class="fa fa-file-export"></i> Konversi ke PDF</button>
    <p class="note" id="statusText"></p>
</div>

<div class="panel">
    <label>Riwayat PDF yang Sudah Dibuat</label>
    <table class="riwayat-table" id="riwayatTable">
        <thead>
            <tr>
                <th></th>
                <th>Nama File</th>
                <th>Ukuran</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="riwayatBody">
            <?php foreach ($riwayat as $r): ?>
            <tr data-id="<?= (int) $r['id'] ?>">
                <td><i class="fa fa-file-pdf" style="font-size:22px;color:#ef4444;"></i></td>
                <td><?= htmlspecialchars($r['judul']) ?></td>
                <td style="color:#94a3b8;"><?= htmlspecialchars($r['ukuran_file']) ?> KB</td>
                <td style="color:#94a3b8;"><?= htmlspecialchars($r['created_at']) ?></td>
                <td>
                    <a href="<?= htmlspecialchars($r['file_path']) ?>" download class="link-download" style="margin:0;">
                        <i class="fa fa-download"></i> Download
                    </a>
                    &nbsp;|&nbsp;
                    <a href="#" class="link-hapus" data-id="<?= (int) $r['id'] ?>" onclick="return hapusRiwayat(this);">
                        <i class="fa fa-trash"></i> Hapus
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="riwayat-empty" id="riwayatEmpty" style="<?= count($riwayat) > 0 ? 'display:none;' : '' ?>">
        Belum ada riwayat PDF yang dibuat.
    </p>
</div>

<style>
.riwayat-table { width:100%; border-collapse:collapse; margin-top:10px; }
.riwayat-table thead tr { text-align:left; color:#94a3b8; font-size:13px; border-bottom:1px solid #334155; }
.riwayat-table th, .riwayat-table td { padding:10px 8px; }
.riwayat-table tbody tr { border-bottom:1px solid #1e293b; }
.riwayat-empty { color:#94a3b8; font-size:13px; }
.link-download { color:#38bdf8; text-decoration:none; }
.link-download:hover { text-decoration:underline; }
.link-hapus { color:#f87171; text-decoration:none; }
.link-hapus:hover { text-decoration:underline; }
</style>

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

    const namaFile = 'hasil_konversi_' + Date.now() + '.pdf';

    // Download ke perangkat pengguna seperti biasa
    pdf.save(namaFile);
    statusText.textContent = "Selesai! File PDF sudah terdownload. Menyimpan ke riwayat...";

    // Kirim juga ke server supaya masuk riwayat
    try {
        const pdfBlob = pdf.output('blob');
        const formData = new FormData();
        formData.append('ajax_action', 'simpan');
        formData.append('pdf_file', pdfBlob, namaFile);

        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.ok) {
            tambahBarisRiwayat(data);
            statusText.textContent = "Selesai! File PDF sudah terdownload dan tersimpan di riwayat.";
        } else {
            statusText.textContent = "File terdownload, tapi gagal disimpan ke riwayat: " + (data.message || '');
        }
    } catch (err) {
        statusText.textContent = "File terdownload, tapi gagal menghubungi server untuk menyimpan riwayat.";
    }
});

function tambahBarisRiwayat(data) {
    document.getElementById('riwayatEmpty').style.display = 'none';

    const tbody = document.getElementById('riwayatBody');
    const tr = document.createElement('tr');
    tr.setAttribute('data-id', data.id);
    tr.innerHTML = `
        <td><i class="fa fa-file-pdf" style="font-size:22px;color:#ef4444;"></i></td>
        <td>${escapeHtml(data.judul)}</td>
        <td style="color:#94a3b8;">${escapeHtml(String(data.ukuran))} KB</td>
        <td style="color:#94a3b8;">${escapeHtml(data.created_at)}</td>
        <td>
            <a href="${escapeHtml(data.file_path)}" download class="link-download" style="margin:0;">
                <i class="fa fa-download"></i> Download
            </a>
            &nbsp;|&nbsp;
            <a href="#" class="link-hapus" data-id="${data.id}" onclick="return hapusRiwayat(this);">
                <i class="fa fa-trash"></i> Hapus
            </a>
        </td>
    `;
    tbody.prepend(tr);
}

async function hapusRiwayat(el) {
    if (!confirm('Yakin mau hapus riwayat ini? File tidak bisa dikembalikan.')) return false;

    const id = el.getAttribute('data-id');
    const formData = new FormData();
    formData.append('ajax_action', 'hapus');
    formData.append('id', id);

    try {
        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.ok) {
            const row = el.closest('tr');
            row.remove();
            if (document.querySelectorAll('#riwayatBody tr').length === 0) {
                document.getElementById('riwayatEmpty').style.display = '';
            }
        } else {
            alert('Gagal menghapus: ' + (data.message || ''));
        }
    } catch (err) {
        alert('Gagal menghubungi server.');
    }
    return false;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

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