<?php
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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nomor_telepon = trim($_POST['nomor_telepon'] ?? '');
    $foto_profil = NULL;
    
    // Validasi
    if (!$nama_lengkap) {
        $error = 'Nama lengkap harus diisi!';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid!';
    } elseif (!$password_input) {
        $error = 'Password harus diisi!';
    } elseif (strlen($password_input) < 8) {
        $error = 'Password minimal 8 karakter!';
    } elseif ($password_input !== $confirm_password) {
        $error = 'Password dan konfirmasi tidak cocok!';
    } elseif (!$nomor_telepon) {
        $error = 'Nomor telepon harus diisi!';
    } else {
        // Proses upload foto
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['size'] > 0) {
            $file = $_FILES['foto_profil'];
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_ext, $allowed_ext)) {
                $error = 'Format file hanya boleh: JPG, JPEG, PNG, GIF!';
            } elseif ($file['size'] > $max_size) {
                $error = 'Ukuran file maksimal 5MB!';
            } else {
                // Buat folder jika belum ada
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                
                // Generate nama file unik
                $foto_name = uniqid() . '_' . $email . '.' . $file_ext;
                $foto_path = 'uploads/' . $foto_name;
                
                // Cek dan hapus file lama jika ada
                if (move_uploaded_file($file['tmp_name'], $foto_path)) {
                    $foto_profil = $foto_name;
                } else {
                    $error = 'Gagal upload foto!';
                }
            }
        }
        
        // Jika tidak ada error dari upload
        if (!$error) {
            // Cek email sudah terdaftar
            try {
                $stmt = $pdo->prepare("SELECT id_user FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                
                if ($stmt->rowCount() > 0) {
                    $error = 'Email sudah terdaftar!';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
                    
                    // Insert ke database
                    $stmt = $pdo->prepare("
                        INSERT INTO users (nama_lengkap, email, password, nomor_telepon, foto_profil, tanggal_bergabung, status)
                        VALUES (:nama_lengkap, :email, :password, :nomor_telepon, :foto_profil, NOW(), 'aktif')
                    ");
                    
                    $stmt->execute([
                        ':nama_lengkap' => $nama_lengkap,
                        ':email' => $email,
                        ':password' => $hashed_password,
                        ':nomor_telepon' => $nomor_telepon,
                        ':foto_profil' => $foto_profil
                    ]);
                    
                    $success = 'Akun berhasil dibuat! Silakan login.';
                    
                    // Clear form
                    $nama_lengkap = '';
                    $email = '';
                    $nomor_telepon = '';
                }
            } catch(PDOException $e) {
                $error = 'Gagal membuat akun: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Aplikasi Catatan Digital</title>
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

        .register-container {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
        }

        .register-card {
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

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);
            color: white;
            border-left: 4px solid #ff3b30;
        }

        .alert-error::before {
            content: '⚠️';
        }

        .alert-success::before {
            content: '✓';
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

        label .required {
            color: #FF3B30;
            margin-left: 3px;
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

        .password-requirement {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .password-requirement.met {
            color: #28a745;
        }

        .photo-upload {
            margin-bottom: 18px;
        }

        .photo-label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .photo-upload-area {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9ff;
        }

        .photo-upload-area:hover {
            background: #f0f4ff;
            border-color: #764ba2;
        }

        .photo-upload-area.has-file {
            border-color: #28a745;
            background: #f0fdf4;
        }

        .photo-upload-icon {
            font-size: 32px;
            margin-bottom: 8px;
            display: block;
        }

        .photo-upload-text {
            font-size: 12px;
            color: #666;
        }

        .photo-preview {
            margin-top: 12px;
            text-align: center;
            display: none;
        }

        .photo-preview img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .photo-preview-name {
            font-size: 12px;
            color: #28a745;
            margin-top: 8px;
            font-weight: 500;
        }

        .photo-remove {
            font-size: 11px;
            color: #FF3B30;
            cursor: pointer;
            margin-top: 8px;
            text-decoration: underline;
        }

        .photo-remove:hover {
            text-decoration: none;
        }

        #fotoInput {
            display: none;
        }
            margin-top: 25px;
            display: flex;
            gap: 10px;
        }

        .btn {
            flex: 1;
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

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            font-size: 14px;
        }

        .btn-secondary:hover {
            background: #f8f9ff;
        }

        .footer-section {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #666;
        }

        .footer-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-link:hover {
            text-decoration: underline;
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
            .register-card {
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

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="logo-section">
                <div class="logo-icon">📝</div>
                <h1>Catatan Digital</h1>
                <p class="subtitle">Buat akun baru sekarang!</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="registerForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap<span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            id="nama_lengkap" 
                            name="nama_lengkap" 
                            placeholder="Masukkan nama lengkap"
                            value="<?php echo htmlspecialchars($nama_lengkap ?? ''); ?>"
                            required
                            autofocus
                        >
                        <span class="input-icon">👤</span>
                    </div>
                </div>
                
                <div class="photo-upload">
                    <label class="photo-label">📷 Foto Profil (Opsional)</label>
                    <div class="photo-upload-area" id="photoUploadArea" onclick="document.getElementById('fotoInput').click()">
                        <span class="photo-upload-icon">📸</span>
                        <div class="photo-upload-text">
                            Klik atau drag & drop foto di sini<br>
                            <small>(JPG, PNG, GIF - Max 5MB)</small>
                        </div>
                    </div>
                    <input 
                        type="file" 
                        id="fotoInput" 
                        name="foto_profil" 
                        accept="image/*"
                        onchange="handlePhotoUpload(event)"
                    >
                    <div class="photo-preview" id="photoPreview">
                        <img id="photoImg" src="" alt="Preview">
                        <div class="photo-preview-name" id="photoName"></div>
                        <div class="photo-remove" onclick="removePhoto()">Hapus foto</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email<span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="nama@email.com"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            required
                        >
                        <span class="input-icon">✉️</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="nomor_telepon">Nomor Telepon<span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input 
                            type="tel" 
                            id="nomor_telepon" 
                            name="nomor_telepon" 
                            placeholder="+62 812-xxxx-xxxx"
                            value="<?php echo htmlspecialchars($nomor_telepon ?? ''); ?>"
                            required
                        >
                        <span class="input-icon">📱</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password<span class="required">*</span></label>
                    <div class="input-wrapper password-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Minimal 8 karakter"
                            minlength="8"
                            required
                            onkeyup="checkPassword()"
                        >
                        <span class="input-icon">🔒</span>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            👁️
                        </button>
                    </div>
                    <div class="password-requirement" id="passwordReq">
                        ○ Minimal 8 karakter
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password<span class="required">*</span></label>
                    <div class="input-wrapper password-wrapper">
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Ulangi password"
                            minlength="8"
                            required
                            onkeyup="checkPassword()"
                        >
                        <span class="input-icon">🔒</span>
                        <button type="button" class="toggle-password" onclick="toggleConfirmPassword()">
                            👁️
                        </button>
                    </div>
                    <div class="password-requirement" id="matchReq">
                        ○ Password cocok
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        ✨ Daftar
                    </button>
                    <a href="login.php" class="btn btn-secondary">
                        ← Masuk
                    </a>
                </div>
                
                <div class="footer-section">
                    Sudah punya akun? <a href="login.php" class="footer-link">Masuk di sini</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = event.target;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        function toggleConfirmPassword() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const toggleBtn = event.target;
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                confirmPasswordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        function checkPassword() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const passwordReq = document.getElementById('passwordReq');
            const matchReq = document.getElementById('matchReq');

            if (password.length >= 8) {
                passwordReq.classList.add('met');
                passwordReq.innerHTML = '✓ Minimal 8 karakter';
            } else {
                passwordReq.classList.remove('met');
                passwordReq.innerHTML = '○ Minimal 8 karakter';
            }

            if (confirmPassword && password === confirmPassword && password.length >= 8) {
                matchReq.classList.add('met');
                matchReq.innerHTML = '✓ Password cocok';
            } else {
                matchReq.classList.remove('met');
                matchReq.innerHTML = '○ Password cocok';
            }
        }

        function handlePhotoUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedExt = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];

            if (!allowedExt.includes(file.type)) {
                alert('Format file hanya boleh: JPG, JPEG, PNG, GIF!');
                document.getElementById('fotoInput').value = '';
                return;
            }

            if (file.size > maxSize) {
                alert('Ukuran file maksimal 5MB!');
                document.getElementById('fotoInput').value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const photoPreview = document.getElementById('photoPreview');
                const photoImg = document.getElementById('photoImg');
                const photoName = document.getElementById('photoName');
                const uploadArea = document.getElementById('photoUploadArea');

                photoImg.src = e.target.result;
                photoName.textContent = file.name;
                photoPreview.style.display = 'block';
                uploadArea.classList.add('has-file');
            };
            reader.readAsDataURL(file);
        }

        function removePhoto() {
            document.getElementById('fotoInput').value = '';
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('photoUploadArea').classList.remove('has-file');
        }

        // Drag and drop
        const uploadArea = document.getElementById('photoUploadArea');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#764ba2';
            uploadArea.style.background = '#f0f4ff';
        });

        uploadArea.addEventListener('dragleave', () => {
            if (!document.getElementById('fotoInput').files.length) {
                uploadArea.style.borderColor = '#667eea';
                uploadArea.style.background = '#f8f9ff';
            }
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('fotoInput').files = files;
                handlePhotoUpload({ target: { files: files } });
            }
            uploadArea.style.borderColor = '#667eea';
            uploadArea.style.background = '#f8f9ff';
        });

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi tidak cocok!');
                return false;
            }

            const submitBtn = this.querySelector('.btn-primary');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Mendaftar...';
        });
    </script>
</body>
</html>