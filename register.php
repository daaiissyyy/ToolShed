<?php
require 'db.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    if (strlen($user) < 3 || strlen($pass) < 6) {
        $error = "Username needs 3+ characters and password needs 6+ characters.";
    } else {
        // check if username is already taken
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$user]);

        if ($check->fetch()) {
            $error = "That username is already taken, sorry!";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT); // never store plain-text passwords
            $stmt = $conn->prepare("INSERT INTO users(username, password) VALUES(?, ?)");
            $stmt->execute([$user, $hashed]);
            $success = "You're all set! You can log in now.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join the Shed - ToolShed</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>🧰 ToolShed</h1>
            <p class="tagline">Utilize your resources, save the planet a little.</p>

            <h2>Create an account</h2>

            <?php if ($error): ?>
                <p class="msg msg-error">⚠️ <?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="msg msg-success">🎉 <?= htmlspecialchars($success) ?> <a href="login.php">Go to login</a></p>
            <?php endif; ?>

            <form method="POST">
                <input type="text" name="username" placeholder="Pick a username" required>
                <input type="password" name="password" placeholder="Pick a password (6+ chars)" required>
                <button type="submit">Sign me up</button>
            </form>

            <p class="switch">Already have an account? <a href="login.php">Log in here</a></p>
        </div>
    </div>
</body>
</html>
