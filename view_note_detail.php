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

// Handle Delete Action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    try {
        // Verifikasi catatan milik user ini
        $stmt = $pdo->prepare("SELECT id_catatan FROM notes WHERE id_catatan = :id AND id_user = :user_id");
        $stmt->execute([':id' => $delete_id, ':user_id' => $user_id]);
        
        if ($stmt->rowCount() > 0) {
            // Delete catatan
            $stmt = $pdo->prepare("DELETE FROM notes WHERE id_catatan = :id AND id_user = :user_id");
            $stmt->execute([':id' => $delete_id, ':user_id' => $user_id]);
            header('Location: view_notes.php?deleted=1');
            exit;
        }
    } catch(PDOException $e) {
        $error = "Gagal menghapus catatan: " . $e->getMessage();
    }
}

// Ambil ID catatan dari URL
$note_id = $_GET['id'] ?? null;

if (!$note_id || !is_numeric($note_id)) {
    header('Location: view_notes.php');
    exit;
}

// Ambil detail catatan (hanya milik user yang login)
try {
    $stmt = $pdo->prepare("
        SELECT 
            n.id_catatan,
            n.judul,
            n.konten,
            n.tanggal_buat,
            n.tanggal_ubah,
            n.id_kategori,
            c.nama_kategori,
            c.warna as kategori_warna
        FROM notes n
        LEFT JOIN categories c ON n.id_kategori = c.id_kategori
        WHERE n.id_catatan = :id AND n.id_user = :user_id AND n.status = 'aktif'
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

$user_name = htmlspecialchars(ucfirst($_SESSION['user_name'] ?? 'User'));

function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit yang lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam yang lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari yang lalu';
    if ($diff < 2592000) return floor($diff / 604800) . ' minggu yang lalu';
    return date('d M Y', $time);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($note['judul']); ?> - Catatan Digital</title>
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

        .note-header {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }

        .note-title {
            font-size: 36px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 16px;
            line-height: 1.3;
        }

        .note-meta {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .note-category {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--category-color, #667eea);
            color: white;
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .note-date {
            color: #999;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .note-updated {
            color: #999;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .note-content {
            color: #333;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 35px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }

        .btn-action {
            flex: 1;
            min-width: 140px;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-edit {
            background: linear-gradient(135deg, #34C759 0%, #28a745 100%);
            color: white;
        }

        .btn-edit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(52, 199, 89, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #FF3B30 0%, #cc2e26 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 59, 48, 0.4);
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

            .note-title {
                font-size: 28px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                min-width: 100%;
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
                <a href="archive_notes.php" class="menu-btn">📦 Arsip Catatan</a>
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
        <div class="content-container" style="--category-color: <?php echo htmlspecialchars($note['kategori_warna'] ?? '#667eea'); ?>">
            <div class="note-header">
                <h1 class="note-title"><?php echo htmlspecialchars($note['judul']); ?></h1>
                
                <div class="note-meta">
                    <span class="note-category" style="background: <?php echo htmlspecialchars($note['kategori_warna'] ?? '#667eea'); ?>">
                        🏷️ <?php echo htmlspecialchars($note['nama_kategori'] ?? 'Tanpa Kategori'); ?>
                    </span>
                    <span class="note-date">
                        📅 Dibuat: <?php echo time_ago($note['tanggal_buat']); ?>
                    </span>
                    <?php if ($note['tanggal_ubah'] && $note['tanggal_ubah'] != $note['tanggal_buat']): ?>
                    <span class="note-updated">
                        ✏️ Diubah: <?php echo time_ago($note['tanggal_ubah']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="note-content">
                <?php 
                $content = $note['konten'] ?? '';
                if (empty($content)) {
                    echo '<em style="color: #999;">Tidak ada konten catatan</em>';
                } else {
                    echo nl2br(htmlspecialchars($content));
                }
                ?>
            </div>

            <div class="action-buttons">
                <a href="edit_note.php?id=<?php echo $note['id_catatan']; ?>" class="btn-action btn-edit">
                    ✏️ Edit
                </a>
                
                <a href="?delete=<?php echo $note['id_catatan']; ?>" 
                   class="btn-action btn-delete"
                   onclick="return confirm('⚠️ PERINGATAN!\n\nYakin ingin menghapus catatan ini secara PERMANEN?\n\nCatatan yang dihapus tidak dapat dipulihkan!')">
                    🗑️ Hapus
                </a>
            </div>
        </div>
    </div>
</body>
</html>