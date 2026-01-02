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

$success = '';
$error = '';

// Ambil ID catatan dari URL
$note_id = $_GET['id'] ?? null;

if (!$note_id || !is_numeric($note_id)) {
    header('Location: view_notes.php');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul = trim($_POST['judul'] ?? '');
    $konten = trim($_POST['konten'] ?? '');
    $kategori = $_POST['kategori'] ?? null;
    
    if (empty($judul)) {
        $error = 'Judul catatan harus diisi!';
    } elseif (empty($konten)) {
        $error = 'Konten catatan harus diisi!';
    } else {
        try {
            // Verifikasi catatan milik user ini
            $stmt = $pdo->prepare("SELECT id_catatan FROM notes WHERE id_catatan = :id AND id_user = :user_id");
            $stmt->execute([':id' => $note_id, ':user_id' => $user_id]);
            
            if ($stmt->rowCount() == 0) {
                $error = 'Catatan tidak ditemukan atau bukan milik Anda!';
            } else {
                // Update catatan - SESUAI DATABASE: hanya kolom 'konten'
                $sql = "UPDATE notes SET 
                        judul = :judul, 
                        konten = :konten,
                        id_kategori = :kategori,
                        tanggal_ubah = NOW()
                        WHERE id_catatan = :id AND id_user = :user_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':judul' => $judul,
                    ':konten' => $konten,
                    ':kategori' => $kategori ?: null,
                    ':id' => $note_id,
                    ':user_id' => $user_id
                ]);
                
                $success = 'Catatan berhasil diperbarui!';
            }
        } catch(PDOException $e) {
            $error = 'Gagal mengupdate catatan: ' . $e->getMessage();
        }
    }
}

// Ambil data catatan (hanya milik user yang login)
try {
    $stmt = $pdo->prepare("
        SELECT 
            n.id_catatan,
            n.judul,
            n.konten,
            n.id_kategori,
            n.tanggal_buat,
            c.nama_kategori
        FROM notes n
        LEFT JOIN categories c ON n.id_kategori = c.id_kategori
        WHERE n.id_catatan = :id AND n.id_user = :user_id
    ");
    
    $stmt->execute([
        ':id' => $note_id,
        ':user_id' => $user_id
    ]);
    
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        header('Location: view_notes.php');
        exit;
    }
} catch(PDOException $e) {
    die("Error mengambil catatan: " . $e->getMessage());
}

// Ambil daftar kategori milik user
try {
    $stmt = $pdo->prepare("SELECT id_kategori, nama_kategori, warna FROM categories WHERE id_user = :user_id ORDER BY nama_kategori");
    $stmt->execute([':user_id' => $user_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categories = [];
}

$user_name = htmlspecialchars(ucfirst($_SESSION['user_name'] ?? 'User'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Catatan - <?php echo htmlspecialchars($note['judul']); ?></title>
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

        .content-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #666;
            font-size: 15px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
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
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #1a1a1a;
            font-weight: 700;
            font-size: 15px;
        }

        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafafa;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        .form-group textarea {
            width: 100%;
            min-height: 300px;
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafafa;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            resize: vertical;
            line-height: 1.7;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .char-counter {
            text-align: right;
            font-size: 13px;
            color: #999;
            margin-top: 6px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 16px 28px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
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
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .note-info-box {
            background: #f8f9fa;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #666;
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

            .content-container {
                padding: 25px 20px;
            }

            .page-header h2 {
                font-size: 26px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
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
                <a href="create_note.php" class="menu-btn">✨ Buat Catatan</a>
                <a href="view_notes.php" class="menu-btn active">📋 Lihat Semua</a>
                <a href="manage_category.php" class="menu-btn">🏷️ Kelola Kategori</a>
                <a href="archive.php" class="menu-btn">📦 Arsip Catatan</a>
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
        <div class="content-container">
            <div class="page-header">
                <h2>✏️ Edit Catatan</h2>
                <p>Perbarui informasi catatan Anda</p>
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

            <div class="note-info-box">
                📅 Dibuat pada: <?php echo date('d F Y, H:i', strtotime($note['tanggal_buat'])); ?>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="judul">📝 Judul Catatan *</label>
                    <input 
                        type="text" 
                        id="judul" 
                        name="judul" 
                        value="<?php echo htmlspecialchars($note['judul']); ?>"
                        placeholder="Masukkan judul catatan..."
                        maxlength="200"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="kategori">🏷️ Kategori</label>
                    <select id="kategori" name="kategori">
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id_kategori']; ?>" 
                                    <?php echo ($note['id_kategori'] == $cat['id_kategori']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nama_kategori']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="konten">📄 Isi Catatan *</label>
                    <textarea 
                        id="konten" 
                        name="konten" 
                        placeholder="Tulis catatan Anda di sini..."
                        required
                    ><?php echo htmlspecialchars($note['konten']); ?></textarea>
                    <div class="char-counter">
                        <span id="charCount">0</span> karakter
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        💾 Simpan Perubahan
                    </button>
                    <a href="view_notes.php" class="btn btn-secondary">
                        ← Kembali ke Daftar Catatan
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Character counter
        const textarea = document.getElementById('konten');
        const charCount = document.getElementById('charCount');
        
        function updateCharCount() {
            charCount.textContent = textarea.value.length.toLocaleString('id-ID');
        }
        
        textarea.addEventListener('input', updateCharCount);
        updateCharCount();

        // Confirm before leaving if form is modified
        let formModified = false;
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                formModified = true;
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (formModified) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        form.addEventListener('submit', () => {
            formModified = false;
        });
    </script>
</body>
</html>