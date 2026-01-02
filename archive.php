<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Asia/Jakarta');

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $note_id = $_POST['note_id'] ?? 0;
    
    if ($_POST['action'] == 'restore' && $note_id) {
        try {
            $stmt = $pdo->prepare("UPDATE notes SET status = 'aktif', tanggal_ubah = NOW() WHERE id_catatan = :id AND id_user = :user_id");
            $stmt->execute([':id' => $note_id, ':user_id' => $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = 'Catatan berhasil dipulihkan ke daftar aktif!';
            } else {
                $error = 'Catatan tidak ditemukan atau Anda tidak memiliki akses!';
            }
        } catch(PDOException $e) {
            $error = 'Gagal memulihkan catatan: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] == 'delete' && $note_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM notes WHERE id_catatan = :id AND id_user = :user_id");
            $stmt->execute([':id' => $note_id, ':user_id' => $user_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = 'Catatan berhasil dihapus permanen!';
            } else {
                $error = 'Catatan tidak ditemukan atau Anda tidak memiliki akses!';
            }
        } catch(PDOException $e) {
            $error = 'Gagal menghapus catatan: ' . $e->getMessage();
        }
    }
}

$search = trim($_GET['search'] ?? '');

$sql = "SELECT n.*, c.nama_kategori, c.warna as kategori_warna
        FROM notes n 
        LEFT JOIN categories c ON n.id_kategori = c.id_kategori 
        WHERE n.id_user = :user_id AND n.status = 'arsip'";

$params = [':user_id' => $user_id];

if ($search) {
    $sql .= " AND (n.judul LIKE :search OR n.konten LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY n.tanggal_ubah DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $archived_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error mengambil catatan arsip: " . $e->getMessage());
}

$total_archived = count($archived_notes);

function time_ago($datetime) {
    if (empty($datetime)) return 'Tidak diketahui';
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return 'Tidak diketahui';
    
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 10) return 'Baru saja';
    if ($diff < 60) return floor($diff) . ' detik yang lalu';
    if ($diff < 3600) return floor($diff / 60) . ' menit yang lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam yang lalu';
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return ($days == 1) ? 'Kemarin' : $days . ' hari yang lalu';
    }
    if ($diff < 2592000) return floor($diff / 604800) . ' minggu yang lalu';
    if ($diff < 31536000) return floor($diff / 2592000) . ' bulan yang lalu';
    
    return floor($diff / 31536000) . ' tahun yang lalu';
}

$user_name = htmlspecialchars(ucfirst($_SESSION['user_name'] ?? 'User'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsip Catatan - Catatan Digital</title>
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
        }

        .navbar-btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .main-container {
            max-width: 1200px;
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
            margin-bottom: 35px;
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

        .search-section {
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        input[type="text"] {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafafa;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
        }

        .clear-search {
            padding: 14px 24px;
            background: #f0f0f0;
            color: #666;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .clear-search:hover {
            background: #e0e0e0;
        }

        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 25px;
            padding: 18px 24px;
            background: linear-gradient(135deg, #f0f4ff 0%, #e8f0ff 100%);
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .results-count {
            color: #333;
            font-weight: 600;
            font-size: 15px;
        }

        .notes-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .note-item {
            background: white;
            padding: 28px;
            border-radius: 16px;
            border: 2px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            gap: 20px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .note-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--category-color, linear-gradient(180deg, #667eea 0%, #764ba2 100%));
        }

        .note-item:hover {
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
            transform: translateY(-4px);
        }

        .note-info {
            flex: 1;
        }

        .note-info h3 {
            color: #1a1a1a;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .note-meta {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .note-category {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--category-color, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .note-date {
            color: #999;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .note-content {
            color: #555;
            font-size: 15px;
            line-height: 1.7;
            margin-top: 12px;
        }

        .note-actions {
            display: flex;
            flex-direction: row;
            gap: 12px;
            width: 100%;
            justify-content: flex-end;
        }

        .btn-small {
            padding: 10px 18px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
            text-decoration: none;
            flex-shrink: 0;
        }

        .btn-restore {
            background: linear-gradient(135deg, #34C759 0%, #28a745 100%);
            color: white;
        }

        .btn-restore:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(52, 199, 89, 0.3);
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, #FF3B30 0%, #cc2e26 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 59, 48, 0.3);
        }

        .no-notes {
            text-align: center;
            padding: 80px 20px;
            color: #999;
        }

        .no-notes-icon {
            font-size: 80px;
            margin-bottom: 24px;
            opacity: 0.8;
        }

        .no-notes h3 {
            color: #666;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .no-notes p {
            color: #999;
            font-size: 15px;
            line-height: 1.6;
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

            .search-form {
                flex-direction: column;
            }

            .results-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .note-item {
                flex-direction: column;
            }

            .note-actions {
                flex-direction: row;
                width: 100%;
                flex-wrap: wrap;
            }

            .btn-small {
                flex: 1;
                min-width: 100px;
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
                <a href="archive_notes.php" class="menu-btn active">📦 Arsip</a>
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
                <h2>📦 Catatan yang Diarsipkan</h2>
                <p>Kelola catatan yang telah diarsipkan</p>
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
            
            <div class="search-section">
                <form method="GET" class="search-form">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="🔍 Cari catatan di arsip..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                    <button type="submit" class="search-btn">Cari</button>
                    <?php if ($search): ?>
                        <a href="archive_notes.php" class="clear-search">✕ Hapus</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if ($total_archived > 0): ?>
                <div class="results-info">
                    <span class="results-count">
                        📊 Menampilkan <?php echo $total_archived; ?> catatan arsip
                        <?php if ($search): ?>
                            untuk "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="notes-list">
                    <?php foreach ($archived_notes as $note): 
                        $category_color = $note['kategori_warna'] ?? '#667eea';
                    ?>
                        <div class="note-item" style="--category-color: <?php echo htmlspecialchars($category_color); ?>">
                            <div class="note-info">
                                <h3><?php echo htmlspecialchars($note['judul']); ?></h3>
                                <div class="note-meta">
                                    <span class="note-category" style="background: <?php echo htmlspecialchars($category_color); ?>">
                                        🏷️ <?php echo htmlspecialchars($note['nama_kategori'] ?? 'Tanpa Kategori'); ?>
                                    </span>
                                    <span class="note-date">
                                        🕒 Diarsipkan <?php echo time_ago($note['tanggal_ubah']); ?>
                                    </span>
                                </div>
                                <p class="note-content">
                                    <?php 
                                    $content = htmlspecialchars($note['konten']);
                                    echo strlen($content) > 150 ? substr($content, 0, 150) . '...' : $content;
                                    ?>
                                </p>
                            </div>
                            <div class="note-actions">
                                <form method="POST" style="display: contents;">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id_catatan']; ?>">
                                    <button type="submit" name="action" value="restore" class="btn-small btn-restore"
                                            onclick="return confirm('Pulihkan catatan ini ke daftar aktif?')">
                                        ♻️ Pulihkan
                                    </button>
                                </form>
                                <form method="POST" style="display: contents;">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id_catatan']; ?>">
                                    <button type="submit" name="action" value="delete" class="btn-small btn-delete"
                                            onclick="return confirm('⚠️ PERINGATAN!\n\nCatatan akan dihapus PERMANEN dan tidak dapat dipulihkan!\n\nYakin ingin melanjutkan?')">
                                        🗑️ Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-notes">
                    <div class="no-notes-icon">📭</div>
                    <h3>
                        <?php if ($search): ?>
                            Tidak Ada Hasil
                        <?php else: ?>
                            Arsip Kosong
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?php if ($search): ?>
                            Tidak ditemukan catatan arsip untuk "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            Belum ada catatan yang diarsipkan
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        <?php if ($search): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const searchTerm = '<?php echo addslashes($search); ?>';
            const noteElements = document.querySelectorAll('.note-info h3, .note-content');
            
            noteElements.forEach(el => {
                const text = el.textContent;
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                if (regex.test(text)) {
                    el.innerHTML = text.replace(regex, '<mark style="background: #ffeb3b; padding: 2px 4px; border-radius: 3px;">$1</mark>');
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>