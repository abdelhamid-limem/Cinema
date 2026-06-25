<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }

require_once 'DbConnector.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $email && $password) {
        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $db = new DbConnector('localhost', 'root', 'root', 'CINEMA_DB');

            $check = $db->prepare('SELECT id FROM users WHERE email = :email OR username = :username');
            $check->execute([':email' => $email, ':username' => $username]);

            if ($check->fetch()) {
                $error = 'Username or email already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare(
                    'INSERT INTO users (username, email, password, role) VALUES (:u, :e, :p, "client")'
                );
                $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash]);
                $success = 'Account created! You can now <a href="login.php">sign in</a>.';
            }
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register — Cinema X</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-logo">CINEMA<span>X</span></div>
        <p class="login-subtitle">Create your account</p>

        <?php if ($error):   ?><div class="alert alert-error"  ><?php echo $error;   ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="john_doe" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:8px;">
                Create Account
            </button>
        </form>

        <p style="text-align:center; margin-top:20px; font-size:0.82rem; color:var(--muted);">
            Already have an account? <a href="login.php" style="color:var(--accent);">Sign in</a>
        </p>
    </div>
</div>
</body>
</html>
