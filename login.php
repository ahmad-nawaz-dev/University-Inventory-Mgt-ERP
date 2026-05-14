<?php
// login.php - Fixed with CDN
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/functions.php';

session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        $pdo = getPDO();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'department' => $user['department'] ?? null
            ];
            
            // Redirect based on role to their specific dashboard
            $redirectUrl = BASE_URL . '/';
            switch ($user['role']) {
                case 'super_admin':
                    $redirectUrl .= 'dashboards/super_admin/index.php';
                    break;
                case 'hod':
                    $redirectUrl .= 'dashboards/hod_dashboard.php';
                    break;
                case 'coordinator':
                    $redirectUrl .= 'dashboards/coordinator_dashboard.php';
                    break;
                case 'store_officer':
                    $redirectUrl .= 'dashboards/store_officer_dashboard.php';
                    break;
                case 'faculty':
                    $redirectUrl .= 'dashboards/faculty_dashboard.php';
                    break;
                case 'clerk':
                    $redirectUrl .= 'dashboards/clerk_dashboard.php';
                    break;
                default:
                    $redirectUrl .= 'dashboards/super_admin/index.php'; // Default fallback
                    break;
            }
            
            header("Location: $redirectUrl");
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Nexus Inventory</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-bg: #0f172a;
            --secondary-bg: #1e293b;
            --accent-blue: #0ea5e9;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-main);
            margin: 0;
            overflow-x: hidden;
            height: 100vh;
        }

        .login-wrapper {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* Left Side: Visual/Branding */
        .login-visual {
            flex: 1.2;
            background: url('assets/img/login-bg.png') no-repeat center center;
            background-size: cover;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 4rem;
        }

        .login-visual::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.2) 100%);
        }

        .visual-content {
            position: relative;
            z-index: 2;
        }

        .visual-content h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #fff;
            letter-spacing: -1px;
        }

        .visual-content p {
            font-size: 1.1rem;
            color: var(--text-muted);
            max-width: 500px;
            line-height: 1.6;
        }

        /* Right Side: Form */
        .login-form-container {
            flex: 0.8;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-bg);
            border-left: 1px solid var(--glass-border);
            padding: 2rem;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2.5rem;
            text-decoration: none;
        }

        .brand-logo i {
            color: var(--accent-blue);
            font-size: 2.2rem;
            filter: drop-shadow(0 0 10px rgba(14, 165, 233, 0.3));
        }

        .brand-logo span {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 1px;
        }

        .form-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .input-wrapper:focus-within {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
            background: rgba(30, 41, 59, 0.8);
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .input-wrapper input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            padding: 0.8rem 1rem 0.8rem 2.8rem;
            font-size: 1rem;
            outline: none;
        }

        .input-wrapper .toggle-password {
            position: absolute;
            right: 1rem;
            left: auto;
            cursor: pointer;
            transition: color 0.3s;
        }

        .input-wrapper .toggle-password:hover {
            color: var(--accent-blue);
        }

        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.4);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(14, 165, 233, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .alert-modern {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .footer-note {
            margin-top: 3rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .login-visual {
                display: none;
            }
            .login-form-container {
                flex: 1;
                border: none;
            }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <!-- Visual Section -->
        <div class="login-visual">
            <div class="visual-content">
                <h2>Nexus Core</h2>
                <p>Enterprise-grade asset management and inventory tracking. Streamlined, secure, and infinitely scalable.</p>
            </div>
        </div>

        <!-- Form Section -->
        <div class="login-form-container">
            <div class="login-card">
                <a href="#" class="brand-logo">
                    <i class="fas fa-cubes-stacked"></i>
                    <span>NEXUS</span>
                </a>

                <div class="form-header">
                    <h1>Welcome Back</h1>
                    <p>Enter your credentials to access the system.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-modern">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="email" placeholder="name@company.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" placeholder="••••••••" required>
                            <i class="fas fa-eye toggle-password" id="toggleIcon" onclick="togglePassword()"></i>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" style="background-color: var(--secondary-bg); border-color: var(--glass-border);">
                            <label class="form-check-label ms-1" for="remember" style="text-transform: none; color: var(--text-muted); font-size: 0.9rem;">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="text-decoration-none" style="color: var(--accent-blue); font-size: 0.9rem; font-weight: 500;">Forgot password?</a>
                    </div>

                    <button type="submit" class="login-btn">
                        Sign In <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </form>

                <div class="footer-note">
                    &copy; <?= date('Y') ?> Nexus Core. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
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
    </script>
</body>
</html>