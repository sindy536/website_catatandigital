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

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // ADD CATEGORY
    if ($_POST['action'] == 'add') {
        $category_name = trim($_POST['category_name'] ?? '');
        $category_color = $_POST['category_color'] ?? '#007AFF';
        
        if ($category_name) {
            try {
                // ✅ PERBAIKAN: Cek apakah kategori sudah ada UNTUK USER INI
                $stmt = $pdo->prepare("SELECT id_kategori FROM categories WHERE nama_kategori = :nama AND id_user = :user_id");
                $stmt->execute([
                    ':nama' => $category_name,
                    ':user_id' => $user_id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "Kategori '$category_name' sudah ada!";
                } else {
                    // ✅ PERBAIKAN: Insert kategori dengan id_user
                    $stmt = $pdo->prepare("INSERT INTO categories (nama_kategori, warna, id_user) VALUES (:nama, :warna, :user_id)");
                    $stmt->execute([
                        ':nama' => $category_name,
                        ':warna' => $category_color,
                        ':user_id' => $user_id
                    ]);
                    
                    $success = "Kategori '$category_name' berhasil ditambahkan!";
                }
            } catch(PDOException $e) {
                $error = 'Gagal menambah kategori: ' . $e->getMessage();
            }
        } else {
            $error = 'Nama kategori harus diisi!';
        }
    }
    
    // EDIT CATEGORY
    elseif ($_POST['action'] == 'edit') {
        $category_id = $_POST['category_id'] ?? '';
        $new_name = trim($_POST['category_name'] ?? '');
        $category_color = $_POST['category_color'] ?? '#007AFF';
        
        if ($new_name && $category_id) {
            try {
                // ✅ PERBAIKAN: Verifikasi kategori milik user ini sebelum edit
                $stmt = $pdo->prepare("SELECT id_kategori FROM categories WHERE id_kategori = :id AND id_user = :user_id");
                $stmt->execute([
                    ':id' => $category_id,
                    ':user_id' => $user_id
                ]);
                
                if ($stmt->rowCount() == 0) {
                    $error = 'Kategori tidak ditemukan atau bukan milik Anda!';
                } else {
                    // Update kategori
                    $stmt = $pdo->prepare("UPDATE categories SET nama_kategori = :nama, warna = :warna WHERE id_kategori = :id AND id_user = :user_id");
                    $stmt->execute([
                        ':nama' => $new_name,
                        ':warna' => $category_color,
                        ':id' => $category_id,
                        ':user_id' => $user_id
                    ]);
                    
                    $success = "Kategori berhasil diupdate!";
                }
            } catch(PDOException $e) {
                $error = 'Gagal mengupdate kategori: ' . $e->getMessage();
            }
        } else {
            $error = 'Data tidak lengkap!';
        }
    }
    
    // DELETE CATEGORY
    elseif ($_POST['action'] == 'delete') {
        $category_id = $_POST['category_id'] ?? '';
        
        if ($category_id) {
            try {
                // ✅ PERBAIKAN: Verifikasi kategori milik user ini
                $stmt = $pdo->prepare("SELECT id_kategori FROM categories WHERE id_kategori = :id AND id_user = :user_id");
                $stmt->execute([
                    ':id' => $category_id,
                    ':user_id' => $user_id
                ]);
                
                if ($stmt->rowCount() == 0) {
                    $error = 'Kategori tidak ditemukan atau bukan milik Anda!';
                } else {
                    // ✅ PERBAIKAN: Cek jumlah catatan dalam kategori ini (hanya milik user)
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notes WHERE id_kategori = :id_kategori AND id_user = :user_id");
                    $stmt->execute([
                        ':id_kategori' => $category_id,
                        ':user_id' => $user_id
                    ]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $note_count = $result['count'];
                    
                    if ($note_count > 0) {
                        // Set kategori catatan menjadi NULL sebelum hapus
                        $stmt = $pdo->prepare("UPDATE notes SET id_kategori = NULL WHERE id_kategori = :id_kategori AND id_user = :user_id");
                        $stmt->execute([
                            ':id_kategori' => $category_id,
                            ':user_id' => $user_id
                        ]);
                    }
                    
                    // Hapus kategori
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id_kategori = :id_kategori AND id_user = :user_id");
                    $stmt->execute([
                        ':id_kategori' => $category_id,
                        ':user_id' => $user_id
                    ]);
                    
                    $success = "Kategori berhasil dihapus!";
                }
            } catch(PDOException $e) {
                $error = 'Gagal menghapus kategori: ' . $e->getMessage();
            }
        }
    }
}

// ✅ PERBAIKAN: Ambil HANYA kategori milik user yang login
try {
    $sql = "SELECT 
                c.id_kategori,
                c.nama_kategori,
                c.warna,
                c.deskripsi,
                COUNT(n.id_catatan) as count
            FROM categories c
            LEFT JOIN notes n ON c.id_kategori = n.id_kategori AND n.status = 'aktif' AND n.id_user = :user_id
            WHERE c.id_user = :user_id
            GROUP BY c.id_kategori
            ORDER BY c.nama_kategori";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error mengambil kategori: " . $e->getMessage());
}

// Hitung total
$total_categories = count($categories);
$total_notes = array_sum(array_column($categories, 'count'));

// Icon untuk kategori
$category_icons = [
    'Pekerjaan' => '💼',
    'Pribadi' => '👤',
    'Pelajaran' => '📖',
    'Lainnya' => '📌',
    'default' => '🏷️'
];

$user_name = htmlspecialchars(ucfirst($_SESSION['user_name'] ?? 'User'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Catatan Digital</title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .content-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 28px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 6px;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 14px;
            color: white;
        }

        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            opacity: 0.95;
            font-weight: 500;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .add-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .add-form h3 {
            color: white;
            margin-bottom: 16px;
            font-size: 18px;
            font-weight: 700;
        }

        .form-row {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }

        .form-row input[type="text"] {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.95);
            transition: all 0.3s;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        .form-row input[type="text"]:focus {
            outline: none;
            background: white;
            border-color: white;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }

        .color-picker-wrapper {
            position: relative;
            flex: 0 0 50px;
        }

        .color-picker-wrapper input[type="color"] {
            width: 50px;
            height: 44px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            cursor: pointer;
            background: white;
            padding: 4px;
        }

        .color-picker-wrapper input[type="color"]:hover {
            border-color: white;
        }

        .btn-add {
            background: white;
            color: #667eea;
            padding: 12px 22px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .category-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .category-list-header h3 {
            color: #1a1a1a;
            font-size: 18px;
            font-weight: 700;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
        }

        .category-item {
            background: white;
            padding: 18px;
            border-radius: 12px;
            border: 2px solid #f0f0f0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .category-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--category-color);
        }

        .category-item:hover {
            border-color: var(--category-color);
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .category-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .category-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: var(--category-color);
            color: white;
        }

        .category-details {
            flex: 1;
        }

        .category-name {
            color: #1a1a1a;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .category-count {
            color: #999;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .category-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }

        .btn-small {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, #FF3B30 0%, #cc2e26 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 59, 48, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 60px;
            margin-bottom: 18px;
            opacity: 0.8;
        }

        .empty-state h3 {
            color: #666;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 28px;
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #1a1a1a;
            font-size: 20px;
            font-weight: 700;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .close-modal:hover {
            color: #333;
            background: #f0f0f0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #1a1a1a;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input[type="color"] {
            width: 100%;
            height: 50px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            padding: 5px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 22px;
        }

        .btn-modal {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #555;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                flex-direction: column;
            }

            .color-picker-wrapper {
                flex: 1;
            }

            .category-grid {
                grid-template-columns: 1fr;
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
                <a href="view_notes.php" class="menu-btn">📋 Lihat Semua</a>
                <a href="manage_category.php" class="menu-btn active">🏷️ Kelola Kategori</a>
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
                <h2>🗂️ Kelola Kategori</h2>
                <p>Atur dan organisir kategori catatan Anda</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_categories; ?></div>
                    <div class="stat-label">Total Kategori</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_notes; ?></div>
                    <div class="stat-label">Total Catatan</div>
                </div>
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
            
            <div class="add-form">
                <h3>✨ Tambah Kategori Baru</h3>
                <form method="POST">
                    <div class="form-row">
                        <input 
                            type="text" 
                            name="category_name" 
                            placeholder="Masukkan nama kategori"
                            maxlength="50"
                            required
                        >
                        <div class="color-picker-wrapper">
                            <input 
                                type="color" 
                                name="category_color" 
                                value="#007AFF"
                                title="Pilih warna"
                            >
                        </div>
                        <button type="submit" name="action" value="add" class="btn-add">
                            ➕ Tambah
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="category-list-header">
                <h3>📋 Daftar Kategori (<?php echo $total_categories; ?>)</h3>
            </div>
            
            <?php if (count($categories) > 0): ?>
                <div class="category-grid">
                    <?php foreach ($categories as $category): 
                        $color = $category['warna'] ?? '#007AFF';
                        $icon = $category_icons[$category['nama_kategori']] ?? $category_icons['default'];
                    ?>
                        <div class="category-item" style="--category-color: <?php echo htmlspecialchars($color); ?>">
                            <div class="category-info">
                                <div class="category-icon" style="background: <?php echo htmlspecialchars($color); ?>">
                                    <?php echo $icon; ?>
                                </div>
                                <div class="category-details">
                                    <div class="category-name">
                                        <?php echo htmlspecialchars($category['nama_kategori']); ?>
                                    </div>
                                    <div class="category-count">
                                        📝 <?php echo $category['count']; ?> catatan
                                    </div>
                                </div>
                            </div>
                            <div class="category-actions">
                                <button class="btn-small btn-edit" 
                                        onclick="editCategory(<?php echo $category['id_kategori']; ?>, '<?php echo htmlspecialchars($category['nama_kategori'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($color); ?>')">
                                    ✏️ Edit
                                </button>
                                <button class="btn-small btn-delete" 
                                        onclick="deleteCategory(<?php echo $category['id_kategori']; ?>, '<?php echo htmlspecialchars($category['nama_kategori'], ENT_QUOTES); ?>', <?php echo $category['count']; ?>)">
                                    🗑️ Hapus
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Belum Ada Kategori</h3>
                    <p>Mulai dengan menambahkan kategori pertama Anda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Kategori</h3>
                <button class="close-modal" onclick="closeModal()">×</button>
            </div>
            <form method="POST">
                <input type="hidden" id="categoryId" name="category_id">
                <div class="form-group">
                    <label>Nama Kategori</label>
                    <input 
                        type="text" 
                        id="editName" 
                        name="category_name"
                        required
                    >
                </div>
                <div class="form-group">
                    <label>Warna</label>
                    <input 
                        type="color" 
                        id="editColor" 
                        name="category_color"
                    >
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal()" class="btn-modal btn-cancel">
                        Batal
                    </button>
                    <button type="submit" name="action" value="edit" class="btn-modal btn-save">
                        💾 Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form (hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="deleteId" name="category_id">
    </form>

    <script>
        function editCategory(id, name, color) {
            document.getElementById('categoryId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editColor').value = color;
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function deleteCategory(id, name, count) {
            let message = `Yakin ingin menghapus kategori "${name}"?`;
            if (count > 0) {
                message += `\n\nPeringatan: Ada ${count} catatan dalam kategori ini!`;
            }
            
            if (confirm(message)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>