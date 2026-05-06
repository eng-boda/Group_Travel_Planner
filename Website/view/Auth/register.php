<?php
require_once __DIR__ . '/../../controller/AuthController.php';
require_once __DIR__ . '/../../model/User.php';

$auth = new AuthController();

if ($auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user           = new User();
    $user->name     = trim($_POST['name'] ?? '');
    $user->email    = trim($_POST['email'] ?? '');
    $user->password = $_POST['password'] ?? '';

    $result = $auth->register($user);

    if (is_array($result) && $result['success']) {
        header('Location: login.php?registered=1');
        exit;
    } else {
        $error = is_array($result) ? $result['message'] : 'Registration failed due to an unexpected error.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TripSync</title>
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
        .error-message { background: #fee2e2; color: #dc2626; padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-mark">✈</div>
                <div class="auth-title">Join TripSync</div>
                <p class="muted">Start collaborating on amazing trips</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="field" style="margin-bottom: 1.25rem;">
                    <label class="field-label" for="name">Full name</label>
                    <input type="text" id="name" name="name" class="input" style="width:100%;" required autocomplete="name"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div class="field" style="margin-bottom: 1.25rem;">
                    <label class="field-label" for="email">Email address</label>
                    <input type="email" id="email" name="email" class="input" style="width:100%;" required autocomplete="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="field" style="margin-bottom: 1.5rem;">
                    <label class="field-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="input" style="width:100%;" required autocomplete="new-password">
                    <p class="muted" style="font-size:0.7rem;margin-top:0.25rem;">Password must be at least 6 characters</p>
                </div>

                <button type="submit" class="btn btn--primary" style="width:100%;margin-bottom:1rem;">Create account</button>

                <p class="muted" style="text-align:center;font-size:0.875rem;">
                    Already have an account?
                    <a href="login.php" style="color:#2563eb;text-decoration:none;font-weight:500;">Log in</a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>