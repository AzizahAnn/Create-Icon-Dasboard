<?php
$page_title = "Lihat Data";
$page_icon = "fa-table";
include "includes/header.php";

// Data simulasi -- nanti ganti dengan query ke database
$data_arsip = [
    ["id" => 1, "nama" => "SK Pengangkatan Pegawai", "kategori" => "SK", "tanggal" => "2026-05-12"],
    ["id" => 2, "nama" => "Laporan Keuangan Q1",     "kategori" => "Laporan", "tanggal" => "2026-04-02"],
    ["id" => 3, "nama" => "Surat Undangan Rapat",     "kategori" => "Surat", "tanggal" => "2026-06-20"],
    ["id" => 4, "nama" => "Notulen Rapat Bulanan",    "kategori" => "Notulen", "tanggal" => "2026-06-25"],
    ["id" => 5, "nama" => "Kontrak Kerjasama Vendor",  "kategori" => "Kontrak", "tanggal" => "2026-03-15"],
];
?>

<div class="panel">
    <label>Daftar Arsip (data simulasi)</label>
    <table>
        <thead>
            <tr><th>ID</th><th>Nama Dokumen</th><th>Kategori</th><th>Tanggal</th></tr>
        </thead>
        <tbody>
            <?php foreach ($data_arsip as $row): ?>
            <tr>
                <td><?= $row["id"] ?></td>
                <td><?= htmlspecialchars($row["nama"]) ?></td>
                <td><?= htmlspecialchars($row["kategori"]) ?></td>
                <td><?= htmlspecialchars($row["tanggal"]) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="note">Data ini masih simulasi. Nanti kalau database sudah dibuat, tabel ini akan menampilkan data arsip yang sebenarnya.</p>
</div>

<?php include "includes/footer.php"; ?>
