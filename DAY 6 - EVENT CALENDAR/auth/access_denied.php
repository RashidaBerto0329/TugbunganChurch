<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied — Our Lady of Peace Parish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500&family=Cormorant+Garamond:ital,wght@0,400;1,400&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --gold: #c9a227; --navy: #0d2455; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(155deg, #06122e 0%, #0d2455 50%, #0d3d3d 100%);
            padding: 24px;
        }
        .box {
            background: #fff;
            border-radius: 16px;
            padding: 52px 44px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 0 0 1px rgba(201,162,39,0.2), 0 24px 60px rgba(0,0,0,0.4);
        }
        .icon-wrap {
            width: 68px; height: 68px;
            border-radius: 50%;
            background: #fff5f5;
            border: 2px solid #fecaca;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            font-size: 1.8rem;
            color: #dc2626;
        }
        h1 {
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            letter-spacing: 0.06em;
            color: var(--navy);
            margin-bottom: 10px;
        }
        p {
            font-size: 0.85rem;
            color: #6b7280;
            line-height: 1.65;
            margin-bottom: 28px;
        }
        .divider {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 28px;
        }
        .divider span { flex:1; height:1px; background:#ede8de; }
        .divider i { color: rgba(201,162,39,0.4); font-size: 0.65rem; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 26px;
            background: linear-gradient(135deg, var(--navy), #1a3d7a);
            color: #fff;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            font-size: 0.75rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(13,36,85,0.3);
            transition: transform 0.18s, box-shadow 0.18s;
        }
        .btn-back:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(13,36,85,0.4); }
        .user-note {
            margin-top: 20px;
            font-size: 0.76rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Role-aware back destination
$back_url   = '/church/login.php';
$back_label = 'Back to Login';

if (!empty($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'parishioner') {
        $back_url   = '/church/portal/index.php';
        $back_label = 'Back to Portal';
    } else {
        $back_url   = '/church/dashboard.php';
        $back_label = 'Back to Dashboard';
    }
}
?>
    <div class="box">
        <div class="icon-wrap">
            <i class="fas fa-lock"></i>
        </div>
        <h1>Access Restricted</h1>
        <p>
            You don't have permission to view this page.<br>
            Please contact the system administrator if you believe this is a mistake.
        </p>
        <div class="divider">
            <span></span><i class="fas fa-cross"></i><span></span>
        </div>
        <a href="<?= htmlspecialchars($back_url) ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            <?= htmlspecialchars($back_label) ?>
        </a>
        <?php if (!empty($_SESSION['user_name'])): ?>
        <p class="user-note">
            Logged in as: <?= htmlspecialchars($_SESSION['user_name']) ?>
            (<?= htmlspecialchars($_SESSION['user_role']) ?>)
        </p>
        <?php endif; ?>
    </div>
</body>
</html>