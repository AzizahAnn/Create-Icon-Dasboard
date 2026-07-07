<?php
$page_title = "Impor File";
$page_icon = "fa-folder-open";
include "includes/header.php";

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["dokumen"])) {
    $allowed = ["pdf", "doc", "docx", "xls", "xlsx", "txt", "csv", "zip"];
    $ext = strtolower(pathinfo($_FILES["dokumen"]["name"], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $originalName = pathinfo($_FILES["dokumen"]["name"], PATHINFO_FILENAME);
        $newName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName) . "_" . date("Ymd_His") . "." . $ext;
        if (move_uploaded_file($_FILES["dokumen"]["tmp_name"], "uploads/" . $newName)) {
            $msg = "File berhasil diimpor: $newName";
        } else {
            $msg = "Gagal mengimpor file.";
        }
    } else {
        $msg = "Format file tidak didukung (izin: " . implode(", ", $allowed) . ")";
    }
}

$files = glob("uploads/*.{pdf,doc,docx,xls,xlsx,txt,csv,zip}", GLOB_BRACE);
rsort($files);

$icon_map = [
    "pdf" => "fa-file-pdf", "doc" => "fa-file-word", "docx" => "fa-file-word",
    "xls" => "fa-file-excel", "xlsx" => "fa-file-excel", "txt" => "fa-file-lines",
    "csv" => "fa-file-csv", "zip" => "fa-file-zipper"
];
?>

<div class="panel">
    <form method="POST" enctype="multipart/form-data">
        <label>Pilih file dokumen (PDF, Word, Excel, TXT, CSV, ZIP)</label>
        <input type="file" name="dokumen" required>
        <button type="submit" class="btn-action"><i class="fa fa-upload"></i> Impor File</button>
    </form>
    <?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
</div>

<div class="panel">
    <label>Daftar File Diimpor</label>
    <?php if (empty($files)): ?>
        <p class="note">Belum ada file diimpor.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Nama File</th><th>Ukuran</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($files as $f):
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                $icon = $icon_map[$ext] ?? "fa-file";
                $size = round(filesize($f) / 1024, 1) . " KB";
            ?>
                <tr>
                    <td><i class="fa <?= $icon ?>" style="color:#38bdf8; margin-right:8px;"></i><?= htmlspecialchars(basename($f)) ?></td>
                    <td><?= $size ?></td>
                    <td><a href="<?= htmlspecialchars($f) ?>" download style="color:#38bdf8;">Download</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
