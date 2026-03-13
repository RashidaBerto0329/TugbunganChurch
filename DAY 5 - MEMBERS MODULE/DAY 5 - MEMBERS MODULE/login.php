<?php
// church/login.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in? Redirect by role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'parishioner') {
        header('Location: /church/portal/index.php');
    } else {
        header('Location: /church/dashboard.php');
    }
    exit;
}

$error   = '';
$success = '';

if (isset($_GET['logged_out'])) {
    $success = 'You have been logged out successfully.';
}
if (isset($_GET['registered'])) {
    $success = 'Account created successfully. You may now log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/db.php';

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, name, role, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            session_regenerate_id(true);

            // Role-based redirect
            if ($user['role'] === 'parishioner') {
                header('Location: /church/portal/index.php');
            } else {
                header('Location: /church/dashboard.php');
            }
            exit;
        } else {
            $error = 'Incorrect email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Our Lady of Peace Parish</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Cinzel:wght@400;500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/church/assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #0a1a40;
            overflow: hidden;
        }

        .login-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: linear-gradient(
                160deg,
                #06122e 0%,
                #0d2455 30%,
                #1a4d8f 58%,
                #2d7aaa 78%,
                #1a6060 100%
            );
        }
        .login-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 50% 110%,
                rgba(13,110,110,0.35) 0%, transparent 70%);
        }

        .rays {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .ray {
            position: absolute;
            top: -10%;
            width: 1px;
            height: 130%;
            background: linear-gradient(to bottom,
                rgba(201,162,39,0.18) 0%,
                rgba(201,162,39,0.04) 50%,
                transparent 100%);
            transform-origin: top center;
            animation: sway 12s ease-in-out infinite;
        }
        .ray:nth-child(1) { left: 20%; transform: rotate(-8deg);  animation-delay: 0s;   width: 2px; }
        .ray:nth-child(2) { left: 35%; transform: rotate(-3deg);  animation-delay: 2s;   opacity: 0.6; }
        .ray:nth-child(3) { left: 50%; transform: rotate(0deg);   animation-delay: 0.8s; width: 3px; opacity: 0.8; }
        .ray:nth-child(4) { left: 65%; transform: rotate(4deg);   animation-delay: 3.5s; opacity: 0.5; }
        .ray:nth-child(5) { left: 80%; transform: rotate(9deg);   animation-delay: 1.5s; width: 2px; }
        @keyframes sway {
            0%, 100% { opacity: 0.7; }
            50%       { opacity: 0.3; }
        }

        .login-page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 900px;
            min-height: 520px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(201,162,39,0.2),
                0 32px 80px rgba(0,0,0,0.5),
                0 0 60px rgba(13,110,110,0.15);
        }

        /* LEFT PANEL */
        .login-left {
            flex: 1.1;
            background: linear-gradient(155deg, #0d2455 0%, #0a1a40 50%, #0d3d3d 100%);
            padding: 52px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            border-right: 1px solid rgba(201,162,39,0.15);
        }
        .login-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 100% 80% at 50% 0%,
                rgba(201,162,39,0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        .left-seal-wrap {
            position: relative;
            margin-bottom: 28px;
        }
        .left-seal-glow {
            position: absolute;
            inset: -18px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,162,39,0.2) 0%, transparent 70%);
            animation: pulse-glow 4s ease-in-out infinite;
        }
        @keyframes pulse-glow {
            0%, 100% { transform: scale(1);    opacity: 0.5; }
            50%       { transform: scale(1.15); opacity: 1;   }
        }
        .left-seal {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(201,162,39,0.45);
            box-shadow:
                0 0 0 8px rgba(201,162,39,0.06),
                0 8px 32px rgba(0,0,0,0.4);
            position: relative;
            z-index: 1;
        }

        .left-diocese {
            font-family: 'Cinzel', serif;
            font-size: 0.6rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(201,162,39,0.7);
            margin-bottom: 10px;
        }
        .left-name {
            font-family: 'Cinzel', serif;
            font-size: 1.15rem;
            font-weight: 500;
            color: #fff;
            line-height: 1.25;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }
        .left-name em {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            color: rgba(201,162,39,0.85);
            font-size: 0.88em;
            display: block;
            letter-spacing: 0.06em;
        }
        .left-parish {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.4);
            letter-spacing: 0.08em;
            margin-bottom: 28px;
        }

        .left-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 160px;
            margin-bottom: 28px;
        }
        .left-divider span {
            flex: 1;
            height: 1px;
            background: rgba(201,162,39,0.3);
        }
        .left-divider i {
            color: rgba(201,162,39,0.5);
            font-size: 0.7rem;
        }

        .left-tagline {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 0.95rem;
            color: rgba(255,255,255,0.45);
            line-height: 1.6;
            max-width: 200px;
        }

        .left-bottom {
            position: absolute;
            bottom: 20px;
            left: 0; right: 0;
            text-align: center;
        }
        .left-stars {
            font-size: 0.5rem;
            letter-spacing: 0.4em;
            color: rgba(201,162,39,0.3);
        }

        /* RIGHT PANEL */
        .login-right {
            flex: 1;
            background: #fff;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-heading { margin-bottom: 24px; }
        .form-heading h1 {
            font-family: 'Cinzel', serif;
            font-size: 1.3rem;
            font-weight: 500;
            color: #0d2455;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }
        .form-heading p {
            font-size: 0.82rem;
            color: #9ca3af;
            line-height: 1.5;
            margin-bottom: 0;
        }

        .login-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-radius: 8px;
            padding: 11px 14px;
            margin-bottom: 22px;
            font-size: 0.82rem;
        }
        .login-alert i { margin-top: 1px; flex-shrink: 0; }
        .login-alert.error {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-left: 3px solid #ef4444;
            color: #b91c1c;
            animation: shake 0.35s ease;
        }
        .login-alert.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 3px solid #22c55e;
            color: #166534;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-5px); }
            40%       { transform: translateX(5px); }
            60%       { transform: translateX(-3px); }
            80%       { transform: translateX(3px); }
        }

        .field-group { margin-bottom: 20px; }
        .field-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #374151;
            margin-bottom: 7px;
        }
        .field-wrap { position: relative; }
        .field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #c4b89a;
            font-size: 0.85rem;
            pointer-events: none;
            transition: color 0.18s;
        }
        .field-input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 1.5px solid #e5e0d8;
            border-radius: 9px;
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            background: #fdfcf9;
            color: #1a1a2e;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
        }
        .field-input:focus {
            border-color: #c9a227;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(201,162,39,0.12);
        }
        .field-input::placeholder { color: #c0bbb0; }
        .field-wrap:focus-within .field-icon { color: #c9a227; }

        .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #c4b89a;
            font-size: 0.85rem;
            padding: 4px;
            transition: color 0.18s;
        }
        .toggle-pw:hover { color: #9a7820; }

        .login-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #0d2455 0%, #1a3d7a 100%);
            color: #fff;
            border: none;
            border-radius: 9px;
            font-family: 'Cinzel', serif;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(13,36,85,0.35);
            transition: transform 0.18s, box-shadow 0.18s, background 0.18s;
            margin-top: 8px;
            position: relative;
            overflow: hidden;
        }
        .login-submit::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
            transition: left 0.45s ease;
        }
        .login-submit:hover::before { left: 100%; }
        .login-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 28px rgba(13,36,85,0.45);
            background: linear-gradient(135deg, #162d5c 0%, #1e4a90 100%);
        }
        .login-submit:active { transform: translateY(0); }

        .form-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
        }
        .back-link, .register-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.78rem;
            color: #b0a898;
            transition: color 0.18s;
            text-decoration: none;
        }
        .back-link:hover { color: #9a7820; }
        .register-link { color: #2a6fa8; }
        .register-link:hover { color: #0d2455; }

        @media (max-width: 680px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 420px;
                min-height: unset;
            }
            .login-left {
                padding: 32px 24px;
                border-right: none;
                border-bottom: 1px solid rgba(201,162,39,0.15);
            }
            .left-seal  { width: 80px; height: 80px; }
            .left-name  { font-size: 0.95rem; }
            .left-tagline { display: none; }
            .login-right { padding: 32px 24px; }
            body { overflow: auto; }
        }
    </style>
</head>
<body>

<div class="login-bg"></div>
<div class="rays">
    <div class="ray"></div><div class="ray"></div><div class="ray"></div>
    <div class="ray"></div><div class="ray"></div>
</div>
<canvas id="starfield"></canvas>

<div class="login-page">
    <div class="login-wrapper">

        <!-- LEFT PANEL -->
        <div class="login-left">
            <div class="left-seal-wrap">
                <div class="left-seal-glow"></div>
                <img src="/church/assets/img/church_logo.png"
                     alt="Parish Seal"
                     class="left-seal"
                     onerror="this.style.display='none';document.getElementById('seal-fallback').style.display='flex';">
                <div id="seal-fallback"
                     style="display:none;width:110px;height:110px;border-radius:50%;
                            background:linear-gradient(135deg,#1a5296,#0d2d6b);
                            border:2px solid rgba(201,162,39,0.45);
                            align-items:center;justify-content:center;
                            box-shadow:0 0 0 8px rgba(201,162,39,0.06),0 8px 32px rgba(0,0,0,0.4);
                            position:relative;z-index:1;">
                    <i class="fas fa-anchor" style="color:#e0c060;font-size:2.2rem;"></i>
                </div>
            </div>

            <p class="left-diocese">Diocese of Zamboanga · Est. 1979</p>

            <h2 class="left-name">
                Our Lady of Peace
                <em>and Good Voyage</em>
            </h2>

            <p class="left-parish">Tugbungan, Zamboanga City</p>

            <div class="left-divider">
                <span></span>
                <i class="fas fa-anchor"></i>
                <span></span>
            </div>

            <p class="left-tagline">
                Parish Information<br>Management System
            </p>

            <div class="left-bottom">
                <p class="left-stars">✦ &nbsp; ✦ &nbsp; ✦ &nbsp; ✦ &nbsp; ✦</p>
            </div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="login-right">

            <div class="form-heading">
                <h1>Welcome Back</h1>
                <p>Sign in to your account to continue.</p>
            </div>

            <?php if ($success): ?>
            <div class="login-alert success">
                <i class="fas fa-circle-check"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="login-alert error">
                <i class="fas fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="/church/login.php" autocomplete="off">

                <div class="field-group">
                    <label class="field-label" for="email">Email Address</label>
                    <div class="field-wrap">
                        <i class="fas fa-envelope field-icon"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="field-input"
                            placeholder="you@email.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            autofocus>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="password">Password</label>
                    <div class="field-wrap">
                        <i class="fas fa-lock field-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="field-input"
                            placeholder="••••••••"
                            required>
                        <button type="button"
                                class="toggle-pw"
                                data-toggle-password="#password"
                                tabindex="-1"
                                aria-label="Show/hide password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-submit">
                    <i class="fas fa-arrow-right-to-bracket" style="margin-right:8px;"></i>
                    Sign In
                </button>

            </form>

            <div class="form-footer">
                <a href="/church/index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
                <a href="/church/portal/register.php" class="register-link">
                    <i class="fas fa-user-plus"></i> Create an account
                </a>
            </div>

        </div>
    </div>
</div>

<script src="/church/assets/js/main.js"></script>
</body>
</html>