<?php
session_start();
require "config.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $user["username"];
        $_SESSION["nama_lengkap"] = $user["nama_lengkap"];
        $_SESSION["user_id"] = $user["id"];
        header("Location: admin.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Arsip Digital</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
* { box-sizing: border-box; }

body {
    margin:0;
    font-family:'Segoe UI', sans-serif;
    background:#0f172a;
    color:#fff;
    height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
}

.login-box {
    background:#1e293b;
    padding:40px;
    border-radius:15px;
    width:100%;
    max-width:360px;
    box-shadow:0 10px 30px rgba(0,0,0,0.4);
}

.login-box h2 {
    text-align:center;
    color:#38bdf8;
    margin-bottom:5px;
}

.login-box p.subtitle {
    text-align:center;
    color:#94a3b8;
    margin-top:0;
    margin-bottom:25px;
    font-size:14px;
}

.input-group {
    margin-bottom:18px;
}

.input-group label {
    display:block;
    margin-bottom:6px;
    color:#cbd5e1;
    font-size:14px;
}

.input-group input {
    width:100%;
    padding:12px;
    border-radius:8px;
    border:1px solid #334155;
    background:#0f172a;
    color:#fff;
    font-size:14px;
}

.input-group input:focus {
    outline:none;
    border-color:#38bdf8;
}

.btn-login {
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    background:#38bdf8;
    color:#000;
    font-weight:bold;
    font-size:15px;
    cursor:pointer;
    margin-top:10px;
}

.btn-login:hover {
    background:#0ea5e9;
}

.error-msg {
    background:rgba(239,68,68,0.15);
    border:1px solid #ef4444;
    color:#fca5a5;
    padding:10px;
    border-radius:8px;
    font-size:13px;
    margin-bottom:15px;
    text-align:center;
}

.hint {
    text-align:center;
    margin-top:20px;
    font-size:12px;
    color:#64748b;
}
</style>
</head>
<body>

<div class="login-box">
    <h2><i class="fa fa-database"></i> ARSIP PRO</h2>
    <p class="subtitle">Silakan login untuk melanjutkan</p>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Masukkan username" required autofocus>
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Masukkan password" required>
        </div>
        <button type="submit" class="btn-login">
            <i class="fa fa-right-to-bracket"></i> Login
        </button>
    </form>

    <p class="hint">Demo login: admin / admin123</p>
</div>

</body>
</html>
