<?php
session_start();

if (empty($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"] ?? "Admin";

// $page_title dan $page_icon diisi dari file yang include ini
$page_title = $page_title ?? "Halaman";
$page_icon = $page_icon ?? "fa-file";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> - Arsip Digital</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include "sidebar.php"; ?>

<div class="main">

    <!-- TOPBAR HALAMAN -->
    <div class="topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fa <?= htmlspecialchars($page_icon) ?>" style="font-size:22px; color:#38bdf8;"></i>
            <h2 style="margin:0;"><?= htmlspecialchars($page_title) ?></h2>
        </div>
        <div>
            <span class="user-info"><i class="fa fa-user-circle"></i> <?= htmlspecialchars($username) ?></span>
            <a href="logout.php" class="btn btn-logout"><i class="fa fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div style="margin-top:20px;">