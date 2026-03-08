<?php
session_start();

$adminUser = "admin";
$adminPass = "admin123";
$error = "";

if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === $adminUser && $password === $adminPass) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_name'] = $adminUser;
        header("Location: admin.php");
        exit;
    }

    $error = "Invalid username or password";
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Login</title>
<link rel="stylesheet" href="css/style.css?v=20260305">
</head>
<body>
<main class="page">
    <section class="card">
        <h2>Admin Login</h2>
        <?php if ($error !== ''): ?>
            <p class="status-message status-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="POST" class="booking-form">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <button type="submit" class="btn">Login</button>
        </form>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </section>
</main>
</body>
</html>
