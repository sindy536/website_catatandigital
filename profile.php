<?php
session_start();

if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_id'])) {
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

// Ambil data user dari database
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch(PDOException $e) {
    die("Gagal mengambil data user: " . $e->getMessage());
}

// Hitung total catatan user
$total_notes = 0;
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM catatan WHERE id_user = :id");
    $count_stmt->execute([':id' => $_SESSION['user_id']]);
    $total_notes = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    // Ignore error
}

// Hitung total kategori yang digunakan user
$total_categories = 0;
try {
    $cat_stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_kategori) as total FROM catatan WHERE id_user = :id");
    $cat_stmt->execute([':id' => $_SESSION['user_id']]);
    $total_categories = $cat_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    // Ignore error
}

// Path foto profil
$foto_profil_path = '';
if (!empty($user_data['foto_profil'])) {
    // Asumsi foto disimpan di folder 'uploads/profile/'
    $foto_profil_path = 'uploads/profile/' . $user_data['foto_profil'];
    
    // Cek apakah file benar-benar ada
    if (!file_exists($foto_profil_path)) {
        $foto_profil_path = '';
    }
}

// Susun array user_info dari database
$user_info = [
    'name' => strtoupper($_SESSION['user_name']),
    'email' => $user_data['email'],
    'phone' => $user_data['phone'] ?? '+62 812-3456-7890',
    'join_date' => isset($user_data['created_at']) ? date('d F Y', strtotime($user_data['created_at'])) : date('d F Y'),
    'total_notes' => $total_notes,
    'total_categories' => $total_categories,
    'last_login' => date('d M Y, H:i'),
    'foto_profil' => $foto_profil_path
];

// Proses upload foto profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'upload_photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_photo']['name'];
            $filetype = $_FILES['profile_photo']['type'];
            $filesize = $_FILES['profile_photo']['size'];
            
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $error = 'Format file harus JPG, PNG, atau GIF!';
            } elseif ($filesize > 2 * 1024 * 1024) { // Max 2MB
                $error = 'Ukuran file maksimal 2MB!';
            } else {
                // Buat folder jika belum ada
                $upload_dir = 'uploads/profile/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Hapus foto lama jika ada
                if (!empty($user_data['foto_profil'])) {
                    $old_photo = $upload_dir . $user_data['foto_profil'];
                    if (file_exists($old_photo)) {
                        unlink($old_photo);
                    }
                }
                
                // Generate nama file unik
                $new_filename = uniqid() . '_' . $user_data['email'] . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                    try {
                        // Update database
                        $update_stmt = $pdo->prepare("UPDATE users SET foto_profil = :foto WHERE id_user = :id");
                        $update_stmt->execute([
                            ':foto' => $new_filename,
                            ':id' => $_SESSION['user_id']
                        ]);
                        
                        $user_data['foto_profil'] = $new_filename;
                        $user_info['foto_profil'] = $destination;
                        $success = 'Foto profil berhasil diupload!';
                    } catch(PDOException $e) {
                        $error = 'Gagal update foto profil: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Gagal upload foto!';
                }
            }
        } else {
            $error = 'Silakan pilih foto terlebih dahulu!';
        }
    } elseif ($_POST['action'] == 'remove_photo') {
        // Hapus foto profil
        if (!empty($user_data['foto_profil'])) {
            $photo_path = 'uploads/profile/' . $user_data['foto_profil'];
            if (file_exists($photo_path)) {
                unlink($photo_path);
            }
            
            try {
                $update_stmt = $pdo->prepare("UPDATE users SET foto_profil = NULL WHERE id_user = :id");
                $update_stmt->execute([':id' => $_SESSION['user_id']]);
                
                $user_data['foto_profil'] = '';
                $user_info['foto_profil'] = '';
                $success = 'Foto profil berhasil dihapus!';
            } catch(PDOException $e) {
                $error = 'Gagal hapus foto profil: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] == 'change_password') {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!$old_password || !$new_password || !$confirm_password) {
            $error = 'Semua field password harus diisi!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Password baru dan konfirmasi tidak cocok!';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password minimal 8 karakter!';
        } else {
            $password_match = false;
            
            if (password_verify($old_password, $user_data['password'])) {
                $password_match = true;
            } elseif ($old_password === $user_data['password']) {
                $password_match = true;
            }
            
            if (!$password_match) {
                $error = 'Password lama salah!';
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id_user = :id");
                    $update_stmt->execute([
                        ':password' => $hashed_password,
                        ':id' => $_SESSION['user_id']
                    ]);
                    
                    $user_data['password'] = $hashed_password;
                    $success = 'Password berhasil diubah!';
                } catch(PDOException $e) {
                    $error = 'Gagal update password: ' . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] == 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (!$name || !$phone) {
            $error = 'Semua field harus diisi!';
        } else {
            try {
                $check_column = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
                $phone_exists = $check_column->rowCount() > 0;
                
                if ($phone_exists) {
                    $update_stmt = $pdo->prepare("UPDATE users SET phone = :phone WHERE id_user = :id");
                    $update_stmt->execute([
                        ':phone' => $phone,
                        ':id' => $_SESSION['user_id']
                    ]);
                    
                    $user_info['phone'] = $phone;
                    $user_data['phone'] = $phone;
                }
                
                $_SESSION['user_name'] = strtolower($name);
                $user_info['name'] = strtoupper($name);
                
                $success = 'Profil berhasil diperbarui!';
            } catch(PDOException $e) {
                $error = 'Gagal update profil: ' . $e->getMessage();
            }
        }
    }
}

$user_name = htmlspecialchars(ucfirst($_SESSION['user_name']));
$initials = strtoupper(substr($user_info['name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Aplikasi Catatan Digital</title>
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
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        }

        .navbar-btn:hover,
        .navbar-btn.active {
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
            font-size: 28px;
            font-weight: 800;
            color: #1a1a1a;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
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
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .profile-section {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .profile-pic-wrapper {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 20px;
        }

        .profile-pic {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 56px;
            font-weight: bold;
            border: 5px solid white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            overflow: hidden;
        }

        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-pic-badge {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .profile-pic-badge:hover {
            transform: scale(1.1);
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 6px;
        }

        .profile-email {
            color: #666;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-box {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8f0ff 100%);
            padding: 24px;
            border-radius: 16px;
            border: 2px solid #e9ecef;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-box:hover {
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
        }

        .stat-icon {
            font-size: 28px;
            margin-bottom: 12px;
        }

        .stat-number {
            font-size: 26px;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h3 {
            color: #333;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .edit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #666;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #FF3B30 0%, #cc2e26 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 59, 48, 0.3);
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

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 0;
            border-radius: 20px;
            max-width: 450px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h2 {
            color: #333;
            font-size: 20px;
            font-weight: 700;
        }

        .close-btn {
            background: #f0f0f0;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background: #e0e0e0;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label .required {
            color: #FF3B30;
            margin-left: 3px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #fafafa;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
            color: #999;
        }

        .form-group .helper-text {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
        }

        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9ff;
        }

        .upload-area:hover {
            background: #f0f4ff;
            border-color: #764ba2;
        }

        .upload-area.drag-over {
            background: #e8f0ff;
            border-color: #764ba2;
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .upload-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .upload-hint {
            color: #999;
            font-size: 12px;
        }

        .photo-preview {
            margin-top: 20px;
            text-align: center;
        }

        .photo-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            gap: 10px;
        }

        .modal-footer .btn {
            flex: 1;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        @media (max-width: 768px) {
            .navbar-left {
                gap: 20px;
            }

            .navbar-menu {
                display: none;
            }

            .content-container {
                padding: 25px 20px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
            }

            .profile-pic {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .profile-pic-wrapper {
                width: 100px;
                height: 100px;
            }

            .profile-name {
                font-size: 24px;
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
                <a href="manage_category.php" class="menu-btn">🏷️ Kelola Kategori</a>
                <a href="archive_notes.php" class="menu-btn">📦 Arsip</a>
            </div>
        </div>
        <div class="user-menu">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($user_info['foto_profil'])): ?>
                        <img src="<?php echo htmlspecialchars($user_info['foto_profil']); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo strtoupper($user_name[0]); ?>
                    <?php endif; ?>
                </div>
                <div class="user-name"><?php echo $user_name; ?></div>
            </div>
            <a href="profile.php" class="navbar-btn active">👤 Profil</a>
            <a href="logout.php" class="navbar-btn">🚪 Logout</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="content-container">
            <div class="page-header">
                <h2>Profil Pengguna</h2>
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

            <div class="profile-section">
                <div class="profile-pic-wrapper">
                    <div class="profile-pic">
                        <?php if (!empty($user_info['foto_profil'])): ?>
                            <img src="<?php echo htmlspecialchars($user_info['foto_profil']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-pic-badge" onclick="showPhotoModal()" title="Edit Foto">
                        📸
                    </div>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($user_info['name']); ?></div>
                <div class="profile-email">
                    ✉️ <?php echo htmlspecialchars($user_info['email']); ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-icon">📝</div>
                    <div class="stat-number"><?php echo $user_info['total_notes']; ?></div>
                    <div class="stat-label">Total Catatan</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">🏷️</div>
                    <div class="stat-number"><?php echo $user_info['total_categories']; ?></div>
                    <div class="stat-label">Kategori</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">📅</div>
                    <div class="stat-number"><?php echo date('d', strtotime($user_info['join_date'])); ?></div>
                    <div class="stat-label">Hari Bergabung</div>
                </div>
            </div>

            <div class="info-section">
                <div class="section-header">
                    <h3>👤 Informasi Pribadi</h3>
                    <button class="edit-btn" onclick="showEditModal()">
                        ✏️ Edit
                    </button>
                </div>
                <div class="info-item">
                    <span class="info-label">📛 Nama Lengkap</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_info['name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">✉️ Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_info['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">📱 Nomor Telepon</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_info['phone']); ?></span>
                </div>
            </div>

            <div class="info-section">
                <div class="section-header">
                    <h3>📊 Informasi Akun</h3>
                </div>
                <div class="info-item">
                    <span class="info-label">🆔 ID User</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_data['id_user']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">📅 Tanggal Bergabung</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_info['join_date']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">🕒 Login Terakhir</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_info['last_login']); ?></span>
                </div>
            </div>

            <div class="button-group">
                <button class="btn btn-primary" onclick="showPasswordModal()">
                    🔒 Ubah Password
                </button>
                <button class="btn btn-danger" onclick="if(confirm('Yakin ingin logout?')) location.href='logout.php'">
                    🚪 Logout
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Upload Foto -->
    <div id="photoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📸 Upload Foto Profil</h2>
                <button class="close-btn" onclick="closePhotoModal()">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="photoForm">
                <div class="modal-body">
                    <?php if (!empty($user_info['foto_profil'])): ?>
                        <div class="photo-preview">
                            <img src="<?php echo htmlspecialchars($user_info['foto_profil']); ?>" alt="Current Photo" id="currentPhoto">
                            <div style="margin-top: 15px;">
                                <button type="submit" name="action" value="remove_photo" class="btn btn-danger" onclick="return confirm('Hapus foto profil?')">
                                    🗑️ Hapus Foto
                                </button>
                            </div>
                        </div>
                        <div style="margin: 20px 0; text-align: center; color: #999;">atau</div>
                    <?php endif; ?>
                    
                    <div class="upload-area" id="uploadArea" onclick="document.getElementById('photoInput').click()">
                        <div class="upload-icon">📸</div>
                        <div class="upload-text">Klik untuk pilih foto</div>
                        <div class="upload-hint">JPG, PNG, atau GIF (Max 2MB)</div>
                    </div>
                    
                    <input 
                        type="file" 
                        name="profile_photo" 
                        id="photoInput" 
                        accept="image/jpeg,image/png,image/gif"
                        style="display: none;"
                        onchange="previewPhoto(this)"
                    >
                    
                    <div class="photo-preview" id="photoPreview" style="display: none;">
                        <img src="" alt="Preview" id="previewImage">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closePhotoModal()">Batal</button>
                    <button type="submit" name="action" value="upload_photo" class="btn btn-primary" id="uploadBtn" disabled>
                        📤 Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Profil -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Profil</h2>
                <button class="close-btn" onclick="closeEditModal()">×</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Nama Lengkap<span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="<?php echo htmlspecialchars($user_info['name']); ?>"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="phone">Nomor Telepon<span class="required">*</span></label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            value="<?php echo htmlspecialchars($user_info['phone']); ?>"
                            placeholder="+62 812-xxxx-xxxx"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input 
                            type="email" 
                            value="<?php echo htmlspecialchars($user_info['email']); ?>"
                            disabled
                        >
                        <div class="helper-text">Email tidak dapat diubah</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Batal</button>
                    <button type="submit" name="action" value="update_profile" class="btn btn-primary">💾 Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ubah Password -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🔒 Ubah Password</h2>
                <button class="close-btn" onclick="closePasswordModal()">×</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="old_password">Password Lama<span class="required">*</span></label>
                        <input 
                            type="password" 
                            id="old_password" 
                            name="old_password" 
                            placeholder="Masukkan password lama"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="new_password">Password Baru<span class="required">*</span></label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            placeholder="Minimal 8 karakter"
                            minlength="8"
                            required
                        >
                        <div class="helper-text">Minimal 8 karakter untuk keamanan</div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password Baru<span class="required">*</span></label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Ulangi password baru"
                            minlength="8"
                            required
                        >
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closePasswordModal()">Batal</button>
                    <button type="submit" name="action" value="change_password" class="btn btn-primary">💾 Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showPhotoModal() {
            document.getElementById('photoModal').classList.add('show');
        }
        
        function closePhotoModal() {
            document.getElementById('photoModal').classList.remove('show');
            document.getElementById('photoInput').value = '';
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('uploadBtn').disabled = true;
        }

        function showEditModal() {
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        function showPasswordModal() {
            document.getElementById('passwordModal').classList.add('show');
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').classList.remove('show');
        }

        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validasi ukuran file
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB!');
                    input.value = '';
                    return;
                }
                
                // Validasi tipe file
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file harus JPG, PNG, atau GIF!');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('photoPreview').style.display = 'block';
                    document.getElementById('uploadBtn').disabled = false;
                };
                reader.readAsDataURL(file);
            }
        }

        // Drag and drop
        const uploadArea = document.getElementById('uploadArea');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('drag-over');
            });
        });

        uploadArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('photoInput').files = files;
            previewPhoto(document.getElementById('photoInput'));
        });

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        document.querySelector('#passwordModal form').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Password baru dan konfirmasi tidak cocok!');
            }
        });

        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>