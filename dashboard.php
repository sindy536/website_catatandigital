<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header('Location: login.php');
    exit;
}

// Set timezone ke Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

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

// Ambil kata kunci pencarian jika ada
$search = trim($_GET['search'] ?? '');
$show_search_results = !empty($search);

// Jika ada pencarian, ambil hasil pencarian, jika tidak ambil 6 catatan terbaru
if ($show_search_results) {
    try {
        $sql = "SELECT n.*, c.nama_kategori 
                FROM notes n 
                LEFT JOIN categories c ON n.id_kategori = c.id_kategori 
                WHERE n.id_user = :user_id AND n.status = 'aktif'
                AND (n.judul LIKE :search OR n.konten LIKE :search)
                ORDER BY n.tanggal_buat DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':search' => '%' . $search . '%'
        ]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Error mencari catatan: " . $e->getMessage());
    }
} else {
    try {
        $sql = "SELECT n.*, c.nama_kategori 
                FROM notes n 
                LEFT JOIN categories c ON n.id_kategori = c.id_kategori 
                WHERE n.id_user = :user_id AND n.status = 'aktif'
                ORDER BY n.tanggal_buat DESC
                LIMIT 6";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Error mengambil catatan: " . $e->getMessage());
    }
}

// Hitung total catatan
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notes WHERE id_user = :user_id AND status = 'aktif'");
    $stmt->execute([':user_id' => $user_id]);
    $total_notes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    $total_notes = 0;
}

// Hitung total kategori
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $total_categories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    $total_categories = 0;
}

// Function untuk format waktu relatif (REAL TIME)
function time_ago($datetime) {
    if (empty($datetime)) return 'Tidak diketahui';
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) return 'Tidak diketahui';
    
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 10) return 'Baru saja';
    if ($diff < 60) return floor($diff) . ' detik yang lalu';
    
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' menit yang lalu';
    }
    
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' jam yang lalu';
    }
    
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        if ($days == 1) return 'Kemarin';
        return $days . ' hari yang lalu';
    }
    
    if ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' minggu yang lalu';
    }
    
    if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' bulan yang lalu';
    }
    
    $years = floor($diff / 31536000);
    return $years . ' tahun yang lalu';
}

// Function untuk greeting berdasarkan waktu REAL TIME
function get_greeting() {
    $hour = (int)date('H');
    
    if ($hour >= 5 && $hour < 11) {
        return 'Selamat pagi';
    } elseif ($hour >= 11 && $hour < 15) {
        return 'Selamat siang';
    } elseif ($hour >= 15 && $hour < 18) {
        return 'Selamat sore';
    } else {
        return 'Selamat malam';
    }
}

$user_name = htmlspecialchars(ucfirst($_SESSION['user_name'] ?? 'User'));
$greeting = get_greeting();
$current_time = date('l, d F Y - H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Catatan Digital</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header-section {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 50px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        .greeting-title {
            font-size: 36px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .greeting-subtitle {
            color: #666;
            font-size: 16px;
            font-weight: 400;
        }

        .current-time {
            color: #999;
            font-size: 13px;
            margin-top: 8px;
            font-weight: 500;
        }

        .search-section {
            margin: 40px 0 0 0;
        }

        .search-form {
            display: flex;
            gap: 12px;
            margin-bottom: 0;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input-wrapper input {
            width: 100%;
            padding: 14px 18px 14px 42px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafafa;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        .search-input-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 35px;
            border-radius: 20px;
            color: white;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -75px;
            right: -75px;
            transition: all 0.4s;
        }

        .stat-card:hover::before {
            transform: scale(1.5);
        }

        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .stat-card-content {
            position: relative;
            z-index: 1;
        }

        .stat-icon {
            font-size: 36px;
            margin-bottom: 20px;
            display: block;
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1;
        }

        .stat-label {
            font-size: 15px;
            opacity: 0.95;
            font-weight: 500;
        }

        .section-title {
            color: white;
            font-size: 28px;
            font-weight: 800;
            margin: 50px 0 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-results-info {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8f0ff 100%);
            color: #333;
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            font-weight: 600;
        }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .note-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 20px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 2px solid transparent;
        }

        .note-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .note-card:nth-child(2)::before {
            background: linear-gradient(90deg, #f5576c 0%, #f093fb 100%);
        }

        .note-card:nth-child(3)::before {
            background: linear-gradient(90deg, #ffa751 0%, #ffe259 100%);
        }

        .note-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
            border-color: #667eea;
        }

        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .note-card h3 {
            color: #1a1a1a;
            font-size: 20px;
            font-weight: 700;
            flex: 1;
            line-height: 1.4;
        }

        .note-category {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 12px;
        }

        .note-card:nth-child(2) .note-category {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
        }

        .note-card:nth-child(3) .note-category {
            background: linear-gradient(135deg, #ffa751 0%, #ffe259 100%);
        }

        .note-content {
            margin: 20px 0;
            color: #555;
            font-size: 15px;
            line-height: 1.7;
        }

        .note-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .note-date {
            color: #999;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .note-action {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-size: 18px;
            color: white;
            font-weight: bold;
            text-decoration: none;
        }

        .note-action:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .fab {
            position: fixed;
            bottom: 40px;
            right: 40px;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 32px;
            cursor: pointer;
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.4);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 300;
        }

        .fab:hover {
            transform: scale(1.15) rotate(90deg);
            box-shadow: 0 16px 56px rgba(102, 126, 234, 0.5);
        }

        .fab:active {
            transform: scale(0.95) rotate(90deg);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            margin-bottom: 40px;
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 24px;
            opacity: 0.8;
        }

        .empty-state h3 {
            color: #1a1a1a;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #666;
            font-size: 15px;
            margin-bottom: 32px;
        }

        .btn-create {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-create:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
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

            .header-section {
                padding: 30px;
            }

            .greeting-title {
                font-size: 28px;
            }

            .main-container {
                padding: 20px 15px;
            }

            .search-form {
                flex-direction: column;
            }

            .notes-grid {
                grid-template-columns: 1fr;
            }

            .fab {
                bottom: 25px;
                right: 25px;
                width: 60px;
                height: 60px;
                font-size: 28px;
            }

            .stat-card {
                padding: 25px;
            }

            .stat-number {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-left">
            <h1>📝 Catatan Digital</h1>
            <div class="navbar-menu">
                <a href="dashboard.php" class="menu-btn active">🏠 Dashboard</a>
                <a href="create_note.php" class="menu-btn">✨ Buat Catatan</a>
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
        <div class="header-section">
            <div class="greeting-title"><?php echo $greeting; ?>, <?php echo $user_name; ?> 👋</div>
            <div class="greeting-subtitle">Mari membuat catatan hari ini dan tingkatkan produktivitasmu</div>
            <div class="current-time">📅 <?php echo $current_time; ?></div>

            <div class="search-section">
                <form method="GET" class="search-form">
                    <div class="search-input-wrapper">
                        <span class="search-icon">🔍</span>
                        <input 
                            type="text" 
                            name="search"
                            placeholder="Cari catatan berdasarkan judul atau konten..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>
                    <button type="submit" class="search-btn">Cari</button>
                    <?php if ($show_search_results): ?>
                        <a href="dashboard.php" class="clear-search">✕ Hapus</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="stats-grid">
                <div class="stat-card" onclick="location.href='view_notes.php'">
                    <div class="stat-card-content">
                        <span class="stat-icon">📝</span>
                        <div class="stat-number"><?php echo $total_notes; ?></div>
                        <div class="stat-label">Total Catatan</div>
                    </div>
                </div>
                <div class="stat-card" onclick="location.href='manage_category.php'">
                    <div class="stat-card-content">
                        <span class="stat-icon">🏷️</span>
                        <div class="stat-number"><?php echo $total_categories; ?></div>
                        <div class="stat-label">Kategori</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($show_search_results): ?>
            <div class="search-results-info">
                🔍 Hasil Pencarian untuk: <strong><?php echo htmlspecialchars($search); ?></strong> (<?php echo count($notes); ?> catatan ditemukan)
            </div>
            <h2 class="section-title">📌 Hasil Pencarian</h2>
        <?php else: ?>
            <h2 class="section-title">📌 Catatan Terbaru</h2>
        <?php endif; ?>
        
        <?php if (count($notes) > 0): ?>
            <div class="notes-grid">
                <?php foreach ($notes as $note): ?>
                    <div class="note-card" onclick="location.href='view_note_detail.php?id=<?php echo $note['id_catatan']; ?>'">
                        <div class="note-header">
                            <h3><?php echo htmlspecialchars($note['judul']); ?></h3>
                            <span class="note-category">
                                <?php echo htmlspecialchars($note['nama_kategori'] ?? 'Tanpa Kategori'); ?>
                            </span>
                        </div>
                        <p class="note-content">
                            <?php 
                            $content = htmlspecialchars($note['konten']);
                            echo strlen($content) > 120 ? substr($content, 0, 120) . '...' : $content;
                            ?>
                        </p>
                        <div class="note-footer">
                            <div class="note-date">
                                🕒 <?php echo time_ago($note['tanggal_buat']); ?>
                            </div>
                            <a href="view_note_detail.php?id=<?php echo $note['id_catatan']; ?>" 
                               class="note-action" 
                               onclick="event.stopPropagation()"
                               title="Lihat detail">→</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>
                    <?php if ($show_search_results): ?>
                        Tidak Ada Hasil Pencarian
                    <?php else: ?>
                        Belum Ada Catatan
                    <?php endif; ?>
                </h3>
                <p>
                    <?php if ($show_search_results): ?>
                        Tidak ditemukan catatan yang cocok dengan pencarian "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    <?php else: ?>
                        Mulai dengan membuat catatan pertamamu dan tingkatkan produktivitas!
                    <?php endif; ?>
                </p>
                <?php if (!$show_search_results): ?>
                    <a href="create_note.php" class="btn-create">
                        ✍️ Buat Catatan Pertama
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <button class="fab" onclick="location.href='create_note.php'" title="Buat Catatan Baru">+</button>
</body>
</html>