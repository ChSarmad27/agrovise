<?php
/**
 * AGROVISE - Admin login
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: ' . landingPage());
    exit;
}

$error = '';

// --- Brute-force lockout: 5 failed tries -> 15-minute cool-off per session ---
$LOCK_TRIES = 5;
$LOCK_SECONDS = 900;
$fails = $_SESSION['login_fails'] ?? 0;
$lockUntil = $_SESSION['login_lock_until'] ?? 0;
$lockedNow = ($fails >= $LOCK_TRIES && time() < $lockUntil);

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF: the login form carries a token even before authentication
    $sent = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        $error = 'Security token expired. Please try again.';
    } elseif ($lockedNow) {
        $mins = ceil(($lockUntil - time()) / 60);
        $error = "Too many failed attempts. Please try again in about {$mins} minute(s).";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Prevent session fixation: issue a fresh session ID on login
                    session_regenerate_id(true);
                    unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);

                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    // Role-based access: cache role + granted modules for this session
                    $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                    $perms = json_decode($user['permissions'] ?? '', true);
                    $_SESSION['admin_permissions'] = is_array($perms) ? $perms : [];
                    setFlashMessage('success', 'Welcome back, ' . $user['username'] . '!');
                    header('Location: ' . landingPage());
                    exit;
                } else {
                    // Count the failure; arm the lock on the 5th
                    $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
                    if ($_SESSION['login_fails'] >= $LOCK_TRIES) {
                        $_SESSION['login_lock_until'] = time() + $LOCK_SECONDS;
                    }
                    $error = 'Invalid username or password.';
                }
            } catch (PDOException $e) {
                error_log('[AGROVISE LOGIN] ' . $e->getMessage());
                $error = 'Database error. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — AGROVISE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ============================================================
           AGROVISE — Harvest Editorial · Admin Login
           Matches the public site's design language. Self-contained.
           ============================================================ */
        :root {
            --ink: #0f1a0e;
            --ink-soft: #162414;
            --ivory: #f2efe6;
            --ivory-deep: #e9e4d6;
            --gold: #c2a04f;
            --text-dark: #23291f;
            --text-dark-muted: #5c6355;
            --text-light: #f2efe6;
            --text-light-muted: rgba(242, 239, 230, 0.62);
            --hairline-dark: rgba(15, 26, 14, 0.16);
            --hairline-light: rgba(242, 239, 230, 0.18);
            --serif: 'Cormorant Garamond', Georgia, serif;
            --sans: 'Jost', 'Segoe UI', sans-serif;
            --ease: cubic-bezier(0.22, 1, 0.36, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--sans);
            font-weight: 300;
            background: var(--ivory);
            color: var(--text-dark);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            min-height: 100svh;
        }
        a { color: inherit; text-decoration: none; }
        ::selection { background: var(--gold); color: var(--ink); }

        .micro {
            font-size: 0.66rem;
            font-weight: 500;
            letter-spacing: 0.34em;
            text-transform: uppercase;
        }

        .login-shell {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            min-height: 100svh;
        }

        /* ---------- left: cinematic photo panel ---------- */
        .panel-media {
            position: relative;
            background: var(--ink);
            color: var(--text-light);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(2rem, 4vw, 3.5rem);
        }
        .panel-media .slides,
        .panel-media .slides img {
            position: absolute;
            inset: -3%;
        }
        .panel-media .slides img {
            width: 106%;
            height: 106%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 2.2s ease;
            transform: translate(var(--mx, 0), var(--my, 0)) scale(1.04);
            will-change: transform;
        }
        .panel-media .slides img.active { opacity: 1; }
        .panel-media::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(15,26,14,0.62) 0%, rgba(15,26,14,0.15) 45%, rgba(15,26,14,0.78) 100%);
            pointer-events: none;
        }
        .panel-media > * { position: relative; z-index: 2; }

        .media-top { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
        .brand-word {
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.42em;
            text-transform: uppercase;
        }
        .brand-word span { color: var(--gold); }
        .media-top .micro { color: var(--text-light-muted); }

        .media-caption { max-width: 30ch; }
        .media-caption .kicker {
            display: flex; align-items: center; gap: 18px;
            color: var(--gold);
            margin-bottom: 18px;
        }
        .media-caption .kicker .rule { width: 56px; height: 1px; background: var(--gold); opacity: 0.7; }
        .media-caption h2 {
            font-family: var(--serif);
            font-weight: 500;
            font-size: clamp(2.1rem, 3.6vw, 3.4rem);
            line-height: 1.05;
            letter-spacing: -0.01em;
        }
        .media-caption h2 em { font-style: italic; color: var(--gold); }
        .media-caption p {
            margin-top: 16px;
            font-size: 0.88rem;
            color: var(--text-light-muted);
            letter-spacing: 0.04em;
        }

        /* ---------- right: editorial form panel ---------- */
        .panel-form {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(2.5rem, 6vw, 5.5rem);
            position: relative;
        }
        .form-inner { width: min(400px, 100%); margin-inline: auto; }

        .form-head { margin-bottom: 44px; }
        .form-head .micro { color: var(--gold); display: flex; align-items: center; gap: 16px; }
        .form-head .micro .rule { width: 44px; height: 1px; background: var(--gold); }
        .form-head h1 {
            font-family: var(--serif);
            font-weight: 500;
            font-size: clamp(2.2rem, 4vw, 3rem);
            line-height: 1.05;
            margin-top: 16px;
        }
        .form-head h1 em { font-style: italic; color: var(--gold); }
        .form-head p { margin-top: 12px; font-size: 0.85rem; color: var(--text-dark-muted); letter-spacing: 0.05em; }

        .alert-error {
            border: 1px solid rgba(150, 40, 27, 0.35);
            border-left: 2px solid #96281b;
            background: rgba(150, 40, 27, 0.06);
            color: #7c2115;
            font-size: 0.8rem;
            letter-spacing: 0.06em;
            padding: 14px 18px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        .field { margin-bottom: 34px; position: relative; }
        .field label {
            display: block;
            font-size: 0.62rem;
            font-weight: 500;
            letter-spacing: 0.32em;
            text-transform: uppercase;
            color: var(--text-dark-muted);
            margin-bottom: 10px;
        }
        .field input {
            width: 100%;
            background: transparent;
            border: 0;
            border-bottom: 1px solid var(--hairline-dark);
            padding: 10px 2px 12px;
            font-family: var(--sans);
            font-weight: 400;
            font-size: 1rem;
            letter-spacing: 0.04em;
            color: var(--text-dark);
            transition: border-color 0.4s ease;
        }
        .field input::placeholder { color: rgba(92, 99, 85, 0.45); font-weight: 300; }
        .field input:focus { outline: none; }
        .field .underline {
            position: absolute;
            left: 0; bottom: 0;
            width: 100%; height: 1px;
            background: var(--gold);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.55s var(--ease);
        }
        .field input:focus ~ .underline { transform: scaleX(1); }
        .field input:focus-visible { outline: none; } /* underline is the focus indicator */
        .field:focus-within label { color: var(--gold); }

        .btn-line {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 17px 34px;
            background: var(--ink);
            border: 1px solid var(--ink);
            color: var(--ivory);
            font-family: var(--sans);
            font-size: 0.7rem;
            font-weight: 500;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.45s var(--ease), color 0.45s var(--ease), letter-spacing 0.45s var(--ease);
        }
        .btn-line i { font-size: 0.62rem; transition: transform 0.45s var(--ease); }
        .btn-line:hover { background: transparent; color: var(--ink); letter-spacing: 0.36em; }
        .btn-line:hover i { transform: translateX(6px); }
        .btn-line:focus-visible { outline: 2px solid var(--gold); outline-offset: 3px; }

        .form-foot {
            margin-top: 40px;
            padding-top: 26px;
            border-top: 1px solid var(--hairline-dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .form-foot .hint { font-size: 0.72rem; color: var(--text-dark-muted); letter-spacing: 0.08em; }
        .link-line {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.64rem;
            font-weight: 500;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            position: relative;
            padding-bottom: 5px;
            color: var(--text-dark);
        }
        .link-line::after {
            content: '';
            position: absolute;
            left: 0; bottom: 0;
            width: 100%; height: 1px;
            background: currentColor;
            transform: scaleX(0.35);
            transform-origin: left;
            transition: transform 0.5s var(--ease);
        }
        .link-line:hover::after { transform: scaleX(1); }
        .link-line i { font-size: 0.58rem; }

        /* ---------- entrance reveals ---------- */
        .rv { opacity: 0; transform: translateY(26px); animation: rvIn 0.9s var(--ease) forwards; }
        .rv.d1 { animation-delay: 0.1s; } .rv.d2 { animation-delay: 0.2s; }
        .rv.d3 { animation-delay: 0.3s; } .rv.d4 { animation-delay: 0.4s; }
        @keyframes rvIn { to { opacity: 1; transform: none; } }

        /* ---------- responsive ---------- */
        @media (max-width: 880px) {
            .login-shell { grid-template-columns: 1fr; }
            .panel-media { min-height: 38svh; }
            .media-caption p { display: none; }
            .panel-form { padding: 2.5rem 1.5rem 3.5rem; }
        }

        /* ---------- reduced motion ---------- */
        @media (prefers-reduced-motion: reduce) {
            *, ::before, ::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            .rv { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    <div class="login-shell">

        <!-- Left: cinematic panel -->
        <aside class="panel-media" id="panelMedia">
            <div class="slides" aria-hidden="true">
                <img class="active" src="https://images.unsplash.com/photo-1500382017468-9049fed747ef?q=80&w=1600&auto=format&fit=crop" alt="">
                <img src="https://images.unsplash.com/photo-1574323347407-f5e1ad6d020b?q=80&w=1600&auto=format&fit=crop" alt="">
                <img src="https://images.unsplash.com/photo-1500937386664-56d1dfef3854?q=80&w=1600&auto=format&fit=crop" alt="">
            </div>

            <div class="media-top rv">
                <a href="../index.php" class="brand-word">AGRO<span>VISE</span></a>
                <span class="micro">Est. Premium Agriculture</span>
            </div>

            <div class="media-caption rv d1">
                <div class="kicker">
                    <span class="rule"></span>
                    <span class="micro">The Back Office</span>
                </div>
                <h2>Tend the fields,<br>mind the <em>ledger</em>.</h2>
                <p>Products, purchasing, packing, invoicing and banking — the whole harvest, accounted for.</p>
            </div>
        </aside>

        <!-- Right: form panel -->
        <main class="panel-form">
            <div class="form-inner">
                <div class="form-head">
                    <span class="micro rv"><span class="rule"></span>Admin Access</span>
                    <h1 class="rv d1">Welcome <em>back</em>.</h1>
                    <p class="rv d2">Sign in to the AGROVISE management system.</p>
                </div>

                <?php if ($error): ?>
                <div class="alert-error" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="rv d3">
                    <?php echo csrfField(); ?>
                    <div class="field">
                        <label for="username">Username or Email</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username or email" required autofocus autocomplete="username">
                        <span class="underline" aria-hidden="true"></span>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <span class="underline" aria-hidden="true"></span>
                    </div>

                    <button type="submit" class="btn-line">
                        Enter the House <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="form-foot rv d4">
                    <span class="hint">Default: admin / admin123</span>
                    <a href="../index.php" class="link-line"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Website</a>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const finePointer = window.matchMedia('(pointer: fine)').matches;

        /* ---- slide crossfade ---- */
        const imgs = document.querySelectorAll('.slides img');
        if (imgs.length > 1 && !reduceMotion) {
            let idx = 0;
            setInterval(() => {
                imgs[idx].classList.remove('active');
                idx = (idx + 1) % imgs.length;
                imgs[idx].classList.add('active');
            }, 7000);
        }

        /* ---- cursor parallax on the photo panel ---- */
        if (finePointer && !reduceMotion) {
            const panel = document.getElementById('panelMedia');
            let raf = null;
            panel.addEventListener('mousemove', (e) => {
                if (raf) return;
                raf = requestAnimationFrame(() => {
                    const r = panel.getBoundingClientRect();
                    const x = (e.clientX - r.left) / r.width - 0.5;
                    const y = (e.clientY - r.top) / r.height - 0.5;
                    imgs.forEach(img => {
                        img.style.setProperty('--mx', (x * -14) + 'px');
                        img.style.setProperty('--my', (y * -10) + 'px');
                    });
                    raf = null;
                });
            });
            panel.addEventListener('mouseleave', () => {
                imgs.forEach(img => {
                    img.style.removeProperty('--mx');
                    img.style.removeProperty('--my');
                });
            });
        }
    });
    </script>
</body>
</html>
