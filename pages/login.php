<?php
// login.php - Diseño Moderno V5.2 (Show Password Toggle)
$__ep_debug_log = __DIR__ . '/../logs/login_debug.log';
if (!is_dir(dirname($__ep_debug_log))) { @mkdir(dirname($__ep_debug_log), 0775, true); }
function ep_login_log($msg) {
    global $__ep_debug_log;
    @file_put_contents($__ep_debug_log, '['.date('c').'] '.$msg.PHP_EOL, FILE_APPEND);
}

try {
    require_once __DIR__ . '/../core/db/connection.php';
} catch (Throwable $e) {
    ep_login_log('DB bootstrap error: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error');
}

try {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
} catch (Throwable $e) {
    ep_login_log('Session start error: ' . $e->getMessage());
}

ep_login_log('login.php loaded');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    if (!empty($user) && !empty($pass)) {
        // Buscar usuario
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$user]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            // Usuario existe, verificamos contraseña
            if (password_verify($pass, $userData['password'])) {
                // Login Exitoso
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['username'] = $userData['username'];
                $_SESSION['role'] = $userData['role'];
                
                header("Location: index.php");
                exit;
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "El usuario no existe.";
        }
    } else {
        $error = "Por favor complete todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>
    if (typeof window.Capacitor === 'undefined') {
        window.Capacitor = { triggerEvent: function() { return true; } };
    }
    window.BASE_PATH = '/electroplan';
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b1120">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ElectroPlan">
    <title>Login | Brightronix</title>
    <link rel="manifest" href="../manifest.webmanifest">
    <link rel="icon" href="/assets/pwa-icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/pwa-icon-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Paleta Dark Mode (Deep Matte) de style.css */
            --bg-body: #1b212d;
            --bg-card: #242a38;
            --bg-input: #151a23;
            --primary: #fb5a3a;
            --primary-hover: #e14e32;
            --text-white: #ffffff;
            --text-gray: #94a3b8;
            --text-muted: #58657a;
            --border-subtle: #2f384a;
            --radius-box: 20px;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-white);
            font-family: 'Poppins', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body.theme-light {
            --bg-body: #e2e8f0;
            --bg-card: #ffffff;
            --bg-input: #f8fafc;
            --text-white: #0f172a;
            --text-gray: #64748b;
            --text-muted: #94a3b8;
            --border-subtle: #e2e8f0;
        }
/* Override Bootstrap text-muted con la paleta del proyecto */
.text-muted { color: var(--text-gray) !important; }
body.theme-light .text-muted { color: var(--text-gray) !important; }

        body.theme-light .form-control,
        body.theme-light .btn-eye { background: var(--bg-input); color: #0f172a; border-color: var(--border-subtle); }
        body.theme-light .brand { color: #0f172a; }
        body.theme-light .logo-full { 
            background-color: transparent;
            background-image: url("../assets/logo-text.png");
            -webkit-mask: none;
            mask: none;
        }

        .login-theme-toggle {
            position: fixed; top: 16px; right: 16px; width: 38px; height: 38px; border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.2); background: rgba(17,24,39,.92); color: #fff;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        body.theme-light .login-theme-toggle { border-color: rgba(15,23,42,0.25); background: #fff; color: #0f172a; }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: var(--bg-card);
            border-radius: var(--radius-box);
            padding: 40px;
            border: 1px solid var(--border-subtle);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: white;
        }

        .brand-icon {
            width: 40px; height: 40px;
            background: var(--primary);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .form-label {
            color: var(--text-gray);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            background: var(--bg-input);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            color: var(--text-white);
            padding: 12px 15px;
            font-size: 0.95rem;
        }

        .form-control:focus {
            background: var(--bg-input);
            color: var(--text-white);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(251, 90, 58, 0.2);
            z-index: 2;
        }

        /* Mantiene el color de fondo y texto al usar Autofill del navegador */
        .form-control:-webkit-autofill,
        .form-control:-webkit-autofill:hover, 
        .form-control:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--text-white) !important;
            -webkit-box-shadow: 0 0 0px 1000px var(--bg-input) inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        /* Estilo para el botón de ojo */
        .btn-eye {
            background: var(--bg-input);
            border: 1px solid var(--border-subtle);
            border-left: 0;
            color: var(--text-gray);
            border-radius: 0 12px 12px 0;
            padding: 0 15px;
            transition: 0.2s;
        }
        .btn-eye:hover {
            background: var(--bg-body);
            color: white;
            border-color: var(--border-subtle);
        }

        .input-group-text i {
            color: var(--text-gray);
        }

        .form-control::placeholder {
            color: var(--text-gray);
            opacity: 1; /* Firefox fix */
        }

        .btn-login {
            background: var(--primary);
            color: white;
            padding: 12px;
            border-radius: 50px; /* Pill shape */
            font-weight: 600;
            border: none;
            width: 100%;
            margin-top: 20px;
            transition: 0.3s;
            box-shadow: 0 1px 10px var(--primary);
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .logo-full {
            height: 2.5rem;
            width: 220px;
            background-color: var(--text-white);
            -webkit-mask: url("../assets/logo-text.png") no-repeat center;
            mask: url("../assets/logo-text.png") no-repeat center;
            -webkit-mask-size: contain;
            mask-size: contain;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            transition: all 0.3s ease;
        }

        .app-subtitle {
        font-size: 0.7rem;
        color: var(--text-gray);
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-top: -0.5rem;
        margin-left: 0;
        }
    </style>
</head>
<body>
<script>
(function(){
  try {
    var t = localStorage.getItem('app_theme');
    if (t === 'light') document.body.classList.add('theme-light');
  } catch(e) {}
})();
</script>

<button type="button" id="loginThemeToggle" class="login-theme-toggle" onclick="toggleTheme()" title="Switch Day/Night">
    <i class="fas fa-sun"></i>
</button>

<div class="login-wrapper">
    <div class="login-card">
        <div class="logo-container">
                <div class="logo-full" role="img" aria-label="Brightronix Logo"></div>
                <div class="app-subtitle">Electro Plan</div>
            </div>
        
       <!-- <h5 class="text-center mb-2 fw-bold">Welcome Back</h5> -->

        <?php if($error): ?>
            <div class="alert alert-danger py-2 text-center mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent" style="border-right:0; border-radius: 12px 0 0 12px; border-color: var(--border-subtle);">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" name="username" class="form-control" style="border-left:0; border-radius: 0 12px 12px 0;" placeholder="Enter your username" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent" style="border-right:0; border-radius: 12px 0 0 12px; border-color: var(--border-subtle);">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" name="password" id="loginPass" class="form-control" style="border-left:0; border-right:0; border-radius: 0;" placeholder="••••••••" required>
                    <button type="button" class="btn btn-eye" onclick="togglePassword('loginPass', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                Sign In <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </form>

        <div class="footer-text">
            Brightronix © 2026. All rights reserved.
        </div>
    </div>
</div>

<script>
    function applyTheme(theme) {
        const next = (theme === 'light') ? 'light' : 'dark';
        document.body.classList.toggle('theme-light', next === 'light');
        const btn = document.getElementById('loginThemeToggle');
        const icon = btn ? btn.querySelector('i') : null;
        if (icon) icon.className = next === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        if (btn) btn.title = next === 'light' ? 'Switch to Night Mode' : 'Switch to Day Mode';
        try { localStorage.setItem('app_theme', next); } catch (e) {}
    }

    function toggleTheme() {
        applyTheme(document.body.classList.contains('theme-light') ? 'dark' : 'light');
    }

    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');

        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = "password";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    (function initTheme(){
        let saved = 'dark';
        try { saved = localStorage.getItem('app_theme') || 'dark'; } catch (e) {}
        applyTheme(saved);
    })();
</script>
<script src="../assets/js/pwa-register.js"></script>

</body>
</html>
