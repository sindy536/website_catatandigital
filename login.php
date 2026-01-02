<?php
session_start();

// Koneksi Database
$host = 'localhost';
$dbname = 'db_catatan_digital';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';
    
    if (!$email || !$password_input) {
        $error = 'Email dan password harus diisi!';
    } else {
        try {
            // Cari user berdasarkan email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Cek password: bisa plain text atau hashed
                $password_match = false;
                
                // Coba verify hashed password
                if (password_verify($password_input, $user['password'])) {
                    $password_match = true;
                }
                // Coba plain text password (backward compatibility)
                elseif ($password_input === $user['password']) {
                    $password_match = true;
                    
                    // Hash password lama dan update ke database
                    $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id_user = :id");
                    $update_stmt->execute([
                        ':password' => $hashed_password,
                        ':id' => $user['id_user']
                    ]);
                }
                
                if ($password_match) {
                    // Login berhasil
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = explode('@', $user['email'])[0];
                    $_SESSION['user_id'] = $user['id_user'];
                    
                    header('Location: dashboard.php');
                    exit;
                }
            }
            
            $error = 'Email atau password salah!';
        } catch(PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aplikasi Catatan Digital</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -250px;
            right: -250px;
            animation: float 6s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        h1 {
            color: #333;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
            font-weight: 400;
        }

        .error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .error::before {
            content: '⚠️';
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
            pointer-events: none;
        }

        input {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #fafafa;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input::placeholder {
            color: #aaa;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: #667eea;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0;
            font-size: 13px;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .remember-me label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
        }

        .button-group {
            margin-top: 25px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.5);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            font-size: 14px;
            margin-bottom: 0;
        }

        .btn-secondary:hover {
            background: #f8f9ff;
        }

        .footer-section {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
        }

        .footer-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .footer-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .demo-badge {
            display: inline-block;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 12px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .demo-badge:hover {
            transform: scale(1.05);
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-primary:disabled {
            background: #ccc;
            box-shadow: none;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .logo-icon {
                width: 50px;
                height: 50px;
                font-size: 28px;
            }

            input {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">📝</div>
                <h1>Catatan Digital</h1>
                <p class="subtitle">Selamat datang kembali!</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="nama@email.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                            autofocus
                        >
                        <span class="input-icon">✉️</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper password-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Masukkan password"
                            required
                        >
                        <span class="input-icon">🔒</span>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            👁️
                        </button>
                    </div>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Ingat saya</label>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        🚀 Masuk Sekarang
                    </button>
                    
                    <a href="register.php" class="btn btn-secondary">
                        ✨ Buat Akun
                    </a>
                </div>
                
                <div class="footer-section">
                    <a href="#" class="footer-link" onclick="showForgotPassword(); return false;">
                        Lupa Password?
                    </a>
                </div>

                <div style="text-align: center;">
                    <div class="demo-badge" onclick="fillDemo()">
                        💡 Klik untuk Demo: sindi@email.com / 12345678
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        function showForgotPassword() {
            alert('🔑 Lupa Password?\n\nSilakan hubungi administrator untuk reset password.');
        }

        function fillDemo() {
            document.getElementById('email').value = 'sindi@email.com';
            document.getElementById('password').value = 'password123';
            document.getElementById('email').focus();
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-primary');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Memproses...';
        });

        document.getElementById('email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>