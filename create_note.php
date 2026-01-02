<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header('Location: login.php');
    exit;
}

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

// Ambil ID user dari email yang login
$user_id = null;
try {
    $stmt = $pdo->prepare("SELECT id_user FROM users WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['user_email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $user_id = $user['id_user'];
    } else {
        die("User tidak ditemukan di database!");
    }
} catch(PDOException $e) {
    die("Error mengambil data user: " . $e->getMessage());
}

// Ambil kategori dari tabel categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT id_kategori, nama_kategori FROM categories ORDER BY nama_kategori");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = 'Gagal mengambil data kategori: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if ($title && $category && $content) {
        try {
            // Insert ke database
            $sql = "INSERT INTO notes (id_user, id_kategori, judul, konten, tanggal_buat, tanggal_ubah, status, is_penting) 
                    VALUES (:id_user, :kategori, :judul, :konten, NOW(), NOW(), 'aktif', 0)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':id_user' => $user_id,
                ':kategori' => $category,
                ':judul' => $title,
                ':konten' => $content
            ]);
            
            if ($result) {
                $success = 'Catatan berhasil disimpan!';
                $_POST = [];
            }
            
        } catch(PDOException $e) {
            $error = 'Gagal menyimpan catatan: ' . $e->getMessage();
        }
    } else {
        $error = 'Semua field harus diisi!';
    }
}

$user_name = htmlspecialchars(ucfirst($_SESSION['user_name'] ?? 'User'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Catatan - Catatan Digital</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 100;
            height: 70px;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 50px;
            flex: 1;
        }

        .navbar h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            white-space: nowrap;
        }

        .navbar-menu {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .menu-btn {
            padding: 10px 20px;
            background: transparent;
            color: #555;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .menu-btn:hover,
        .menu-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .user-menu {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: #f8f9fa;
            border-radius: 50px;
            transition: all 0.3s;
        }

        .user-info:hover {
            background: #e9ecef;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
        }

        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .navbar-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #555;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .navbar-btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .main-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 50px;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        .form-header {
            margin-bottom: 40px;
        }

        .form-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #666;
            font-size: 15px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .form-group {
            margin-bottom: 30px;
        }

        label {
            display: block;
            color: #1a1a1a;
            font-weight: 700;
            margin-bottom: 12px;
            font-size: 15px;
        }

        label .required {
            color: #f5576c;
            margin-left: 3px;
        }

        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            transition: all 0.3s;
            background: #fafafa;
        }

        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 300px;
            line-height: 1.7;
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            padding-right: 45px;
        }

        .helper-text {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .char-counter {
            text-align: right;
            font-size: 13px;
            color: #999;
            margin-top: 8px;
            font-weight: 500;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #555;
            padding: 14px 28px;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        @media (max-width: 1200px) {
            .navbar-left {
                gap: 30px;
            }
            
            .menu-btn {
                padding: 10px 16px;
                font-size: 13px;
            }
        }

        @media (max-width: 968px) {
            .navbar {
                padding: 15px 20px;
                height: auto;
                flex-wrap: wrap;
            }

            .navbar-left {
                width: 100%;
                gap: 15px;
                flex-direction: column;
                align-items: flex-start;
            }

            .navbar-menu {
                width: 100%;
                overflow-x: auto;
                padding-bottom: 5px;
            }

            .navbar h1 {
                font-size: 20px;
            }

            .user-menu {
                width: 100%;
                justify-content: flex-end;
            }

            .main-container {
                padding: 20px 15px;
            }

            .form-container {
                padding: 30px 25px;
            }

            .form-header h2 {
                font-size: 26px;
            }

            .button-group {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }

            textarea {
                min-height: 250px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <h1>📝 Catatan Digital</h1>
            <div class="navbar-menu">
                <a href="dashboard.php" class="menu-btn">🏠 Dashboard</a>
                <a href="create_note.php" class="menu-btn active">✨ Buat Catatan</a>
                <a href="view_notes.php" class="menu-btn">📋 Lihat Semua</a>
                <a href="manage_category.php" class="menu-btn">🏷️ Kelola Kategori</a>
                <a href="archive_notes.php" class="menu-btn">📦 Arsip</a>
            </div>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper($user_name[0]); ?></div>
                <div class="user-name"><?php echo $user_name; ?></div>
            </div>
            <a href="profile.php" class="navbar-btn">👤 Profil</a>
            <a href="logout.php" class="navbar-btn">🚪 Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="form-container">
            <div class="form-header">
                <h2>✍️ Tulis Catatan Baru</h2>
                <p>Buat catatan untuk meningkatkan produktivitas dan mengorganisir pemikiranmu</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✓ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ✗ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="noteForm">
                <div class="form-group">
                    <label for="title">
                        Judul Catatan<span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="title" 
                        name="title" 
                        placeholder="Masukkan judul catatan yang menarik"
                        maxlength="100"
                        value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                        required
                    >
                    <div class="helper-text">
                        💡 Gunakan judul yang deskriptif dan mudah diingat
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="category">
                        Kategori<span class="required">*</span>
                    </label>
                    <select id="category" name="category" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id_kategori']); ?>"
                                <?php echo (isset($_POST['category']) && $_POST['category'] == $cat['id_kategori']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nama_kategori']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="helper-text">
                        🏷️ Pilih kategori untuk memudahkan pencarian nanti
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="content">
                        Konten Catatan<span class="required">*</span>
                    </label>
                    <textarea 
                        id="content" 
                        name="content" 
                        placeholder="Mulai menulis catatan Anda di sini...&#10;&#10;Tips: Gunakan paragraf untuk memisahkan ide-ide berbeda"
                        required
                    ><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                    <div class="char-counter">
                        <span id="charCount">0</span> karakter
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="location.href='dashboard.php'">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        💾 Simpan Catatan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const contentTextarea = document.getElementById('content');
        const charCount = document.getElementById('charCount');
        
        contentTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length.toLocaleString('id-ID');
        });
        
        charCount.textContent = contentTextarea.value.length.toLocaleString('id-ID');
        
        document.getElementById('noteForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const category = document.getElementById('category').value;
            const content = document.getElementById('content').value.trim();
            
            if (!title || !category || !content) {
                e.preventDefault();
                alert('Semua field harus diisi!');
                return false;
            }
            
            if (content.length < 10) {
                e.preventDefault();
                alert('Konten catatan minimal 10 karakter!');
                return false;
            }
        });
    </script>
</body>
</html>