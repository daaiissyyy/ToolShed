<?php
session_start();
require 'db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // password_verify checks against the hash, we never compare plain text
    if ($data && password_verify($pass, $data['password'])) {
        $_SESSION['user_id'] = $data['id'];
        $_SESSION['username'] = $data['username'];
        header("Location: dashboard.php");
        exit; // stop the script so the rest of the page doesn't render after redirecting
    } else {
        $error = "Username or password didn't match our records.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log in - ToolShed</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>🧰 ToolShed</h1>
            <p class="tagline">Utilize your resources, save the planet a little.</p>

            <h2>Welcome back</h2>

            <?php if ($error): ?>
                <p class="msg msg-error">⚠️ <?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Log in</button>
            </form>

            <p class="switch">New here? <a href="register.php">Create an account</a></p>
        </div>
    </div>
</body>
</html>
