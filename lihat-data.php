<?php
$page_title = "Lihat Data";
$page_icon_img = "icon/lihat-data.png";
include "header.php";

require "config.php";

// Ambil parameter pencarian/filter (opsional)
$cari = $_GET["cari"] ?? "";

// Query gabungan arsip + kategori + users, biar tampil nama kategori & nama pengunggah (bukan cuma id)
$sql = "SELECT arsip.*, kategori.nama_kategori, users.nama_lengkap AS nama_pengunggah
        FROM arsip
        LEFT JOIN kategori ON arsip.kategori_id = kategori.id
        LEFT JOIN users ON arsip.uploaded_by = users.id";

if ($cari !== "") {
    $sql .= " WHERE arsip.judul LIKE :cari OR arsip.nomor_arsip LIKE :cari OR arsip.tag LIKE :cari";
}

$sql .= " ORDER BY arsip.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($cari !== "") {
    $stmt->bindValue(":cari", "%$cari%");
}
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="panel">
    <form method="GET" style="display:flex; gap:10px;">
        <input type="text" name="cari" placeholder="Cari judul, nomor arsip, atau tag..."
               value="<?= htmlspecialchars($cari) ?>"
               style="flex:1; padding:10px; border-radius:8px; border:1px solid #334155; background:#0f172a; color:#fff;">
        <button type="submit" class="btn-action"><i class="fa fa-search"></i> Cari</button>
        <?php if ($cari !== ""): ?>
            <a href="lihat-data.php" class="btn btn-gray" style="display:flex; align-items:center; padding:0 15px; color:#fff; text-decoration:none; background:#334155; border-radius:10px;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="panel">
    <label>Data Arsip (Total: <?= count($data) ?> record)</label>

    <?php if (empty($data)): ?>
        <p class="note">Tidak ada data arsip ditemukan.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; margin-top:10px;">
            <thead>
                <tr style="background:#0f172a; text-align:left;">
                    <th style="padding:10px; border-bottom:1px solid #334155;">Judul</th>
                    <th style="padding:10px; border-bottom:1px solid #334155;">No. Arsip</th>
                    <th style="padding:10px; border-bottom:1px solid #334155;">Kategori</th>
                    <th style="padding:10px; border-bottom:1px solid #334155;">Jenis</th>
                    <th style="padding:10px; border-bottom:1px solid #334155;">Tgl Dokumen</th>
                    <th style="padding:10px; border-bottom:1px solid #334155;">Pengunggah</th>
                    <th style="padding:10px; border-bottom:1px solid #334155;">Status</th>
                    <th style="padding:10px; border-bottom:1px solid #334155;">Sifat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <tr style="border-bottom:1px solid #1e293b;">
                    <td style="padding:10px;"><?= htmlspecialchars($row["judul"]) ?></td>
                    <td style="padding:10px;"><?= htmlspecialchars($row["nomor_arsip"] ?? "-") ?></td>
                    <td style="padding:10px;"><?= htmlspecialchars($row["nama_kategori"] ?? "-") ?></td>
                    <td style="padding:10px;"><?= htmlspecialchars($row["jenis_arsip"] ?? "-") ?></td>
                    <td style="padding:10px;"><?= htmlspecialchars($row["tanggal_dokumen"] ?? "-") ?></td>
                    <td style="padding:10px;"><?= htmlspecialchars($row["nama_pengunggah"] ?? "-") ?></td>
                    <td style="padding:10px;">
                        <span style="padding:4px 10px; border-radius:20px; font-size:12px;
                            background:<?= $row["status"] === "Aktif" ? "#166534" : "#334155" ?>;">
                            <?= htmlspecialchars($row["status"] ?? "-") ?>
                        </span>
                    </td>
                    <td style="padding:10px;">
                        <span style="padding:4px 10px; border-radius:20px; font-size:12px;
                            background:<?= $row["sifat"] === "Rahasia" ? "#7f1d1d" : ($row["sifat"] === "Penting" ? "#78350f" : "#334155") ?>;">
                            <?= htmlspecialchars($row["sifat"] ?? "-") ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php include "footer.php"; ?>