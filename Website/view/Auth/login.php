<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/user.php';

$auth = new AuthController();

// If already logged in, go to index
if ($auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = new User();
    $user->email = trim($_POST['email'] ?? '');
    $user->password = $_POST['password'] ?? '';

    if ($auth->login($user)) {
        header('Location: ../pages/index.php');
        exit;
    } else {
        session_start();
        $error = $_SESSION['errMsg'] ?? 'Invalid email or password';
        unset($_SESSION['errMsg']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TripSync</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../view/css/main.css">
    <style>
        .auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f5f7fa 0%, #e9edf2 100%); padding: 1.5rem; }
        .auth-card { max-width: 440px; width: 100%; background: white; border-radius: 32px; padding: 2rem; box-shadow: 0 20px 35px -12px rgba(0,0,0,0.1); }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .logo-mark { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .auth-title { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
        .error-message { background: #fee2e2; color: #dc2626; padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.875rem; display: none; }
        .success-message { background: #dcfce7; color: #16a34a; padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.875rem; display: none; }
        .debug { background: #f0f0f0; color: #333; padding: 0.5rem; font-size: 0.75rem; margin-top: 1rem; display: none; }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-mark">✈</div>
                <div class="auth-title">Welcome back</div>
                <p class="muted">Sign in to continue planning together</p>
            </div>

            <div id="error-message" class="error-message"></div>
            <div id="success-message" class="success-message"></div>

            <form id="login-form">
                <div class="field" style="margin-bottom: 1.25rem;">
                    <label class="field-label" for="email">Email address</label>
                    <input type="email" id="email" name="email" class="input input--full" required autocomplete="email">
                </div>

                <div class="field" style="margin-bottom: 1.5rem;">
                    <label class="field-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="input input--full" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn--primary btn--full" style="margin-bottom: 1rem;">Log in</button>

                <p class="muted" style="text-align: center; font-size: 0.875rem;">
                    Don't have an account? 
                    <a href="register.php" style="color: #2563eb; text-decoration: none; font-weight: 500;">Create account</a>
                </p>
            </form>
            <div id="debug-info" class="debug"></div>
        </div>
    </div>

    
</body>
</html>
