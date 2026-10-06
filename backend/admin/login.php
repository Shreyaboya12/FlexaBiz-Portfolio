<?php

require_once __DIR__ . '/../config/bootstrap.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email and password.';
    } else {
        try {
            $user = verifyAdminLogin(database(), $email, $password);
            if ($user === null) {
                $error = 'Invalid email or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_email'] = $user['email'];
                header('Location: /admin');
                exit;
            }
        } catch (PDOException $exception) {
            error_log('Portfolio admin login database error: ' . $exception->getMessage());
            $error = 'The admin database is unavailable. Check the database configuration and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Login</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f3f7;
            display: grid;
            place-items: center;
            min-height: 100vh;
        }
        .panel {
            width: min(92vw, 440px);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 30px 50px rgba(15, 23, 42, 0.08);
        }
        h1 { margin: 0 0 12px; font-size: 2rem; color: #111827; }
        p { color: #4b5563; margin-bottom: 20px; }
        label { display: block; font-size: 0.9rem; color: #374151; margin-bottom: 8px; }
        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            margin-bottom: 16px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 1rem;
        }
        button {
            width: 100%;
            border: none;
            background: #4f46e5;
            color: white;
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 1rem;
            cursor: pointer;
        }
        .error {
            margin-bottom: 16px;
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px;
            font-size: 0.92rem;
        }
    </style>
</head>
<body>
    <div class="panel">
        <h1>Portfolio Admin</h1>
        <p>Sign in to manage your showcase.</p>
        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="POST" action="/admin/login">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="username" required />
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required />
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
