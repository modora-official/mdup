<?php
session_start();
set_time_limit(0);
ini_set('memory_limit', '512M');

// ==========================================
// 1. KONFIGURASI UTAMA
// ==========================================
$PASSWORD_AKSES = "rahasia123";
$DIREKTORI_UPLOAD = __DIR__ . '/uploads/';

// Masukkan link RAW dari file index.php di repository GitHub Anda
// Contoh: https://raw.githubusercontent.com/username/repo/main/index.php
$GITHUB_RAW_URL = ""; 

// ==========================================
// 2. INISIALISASI FOLDER
// ==========================================
if (!file_exists($DIREKTORI_UPLOAD)) {
    mkdir($DIREKTORI_UPLOAD, 0755, true);
}

// ==========================================
// 3. LOGIKA OTENTIKASI & SESI
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD_AKSES) {
        $_SESSION['login'] = true;
    } else {
        $error_msg = "Password salah!";
    }
}

$pesan = "";

// ==========================================
// 4. FITUR AUTO-UPDATE DARI GITHUB
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_SESSION['login'])) {
    if (empty($GITHUB_RAW_URL)) {
        $pesan = "<div class='alert error'><i class='fa-solid fa-triangle-exclamation'></i> URL Raw GitHub belum dikonfigurasi pada kode sumber.</div>";
    } else {
        $ch_update = curl_init($GITHUB_RAW_URL);
        curl_setopt($ch_update, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_update, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_update, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $kode_baru = curl_exec($ch_update);
        $http_code = curl_getinfo($ch_update, CURLINFO_HTTP_CODE);
        curl_close($ch_update);

        if ($http_code == 200 && !empty($kode_baru)) {
            // Script ini akan menimpa dirinya sendiri dengan kode baru dari GitHub
            if (file_put_contents(__FILE__, $kode_baru)) {
                $pesan = "<div class='alert success'><i class='fa-solid fa-circle-check'></i> Sistem berhasil diperbarui ke versi terbaru dari GitHub!</div>";
            } else {
                $pesan = "<div class='alert error'><i class='fa-solid fa-xmark'></i> Gagal menyimpan pembaruan. Pastikan izin file (CHMOD) di VPS mengizinkan penulisan (write).</div>";
            }
        } else {
            $pesan = "<div class='alert error'><i class='fa-solid fa-plug-circle-xmark'></i> Gagal terhubung ke GitHub. Periksa kembali tautan URL Raw Anda.</div>";
        }
    }
}

// ==========================================
// 5. LOGIKA LEECHING / REMOTE UPLOAD
// ==========================================
function getFilenameFromUrl($url, $header) {
    if (preg_match('/filename="(.*?)"/', $header, $matches)) {
        return $matches[1];
    }
    $basename = basename(parse_url($url, PHP_URL_PATH));
    return $basename ? $basename : 'downloaded_file_' . time();
}

if (isset($_SESSION['login']) && isset($_POST['url_download'])) {
    $url_input = trim($_POST['url_download']);
    $url_target = $url_input;
    
    if (strpos($url_input, 'mediafire.com') !== false) {
        $ch_mf = curl_init($url_input);
        curl_setopt($ch_mf, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_mf, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch_mf, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch_mf, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch_mf);
        curl_close($ch_mf);
        
        if (preg_match('/id="downloadButton" href="(.*?)"/', $html, $matches)) {
            $url_target = $matches[1];
        } else {
            $pesan = "<div class='alert error'><i class='fa-solid fa-triangle-exclamation'></i> Gagal mengekstrak direct link dari MediaFire.</div>";
        }
    }

    if (empty($pesan)) {
        $ch = curl_init($url_target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $header_data = curl_exec($ch);
        $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $nama_file = getFilenameFromUrl($final_url, $header_data);
        $nama_file = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $nama_file);
        $target_file = $DIREKTORI_UPLOAD . $nama_file;

        $fp = fopen($target_file, 'w+');
        if ($fp) {
            $ch = curl_init($final_url);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $sukses = curl_exec($ch);
            $error_curl = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($sukses) {
                $pesan = "<div class='alert success'><i class='fa-solid fa-circle-check'></i> File berhasil ditarik ke server: <strong>" . htmlspecialchars($nama_file) . "</strong></div>";
            } else {
                unlink($target_file);
                $pesan = "<div class='alert error'><i class='fa-solid fa-circle-xmark'></i> Gagal mendownload: $error_curl</div>";
            }
        } else {
            $pesan = "<div class='alert error'><i class='fa-solid fa-folder-open'></i> Gagal menulis file. Periksa izin folder di VPS.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nixy VPS Core</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-base: #09090b;
            --bg-card: #18181b;
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --accent-primary: #3b82f6;
            --accent-hover: #2563eb;
            --accent-warning: #f59e0b;
            --accent-warning-hover: #d97706;
            --border-color: #27272a;
            --status-success: #10b981;
            --status-error: #ef4444;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .core-container {
            background-color: var(--bg-card);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            width: 100%;
            max-width: 480px;
            border: 1px solid var(--border-color);
        }

        .header-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .header-section i {
            font-size: 42px;
            color: var(--accent-primary);
            margin-bottom: 12px;
        }

        .header-section h2 {
            margin: 0;
            font-weight: 600;
            font-size: 24px;
            letter-spacing: -0.5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            color: var(--text-secondary);
        }

        input[type="password"],
        input[type="url"],
        input[type="text"] {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background-color: var(--bg-base);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 15px;
            outline: none;
            transition: all 0.2s ease;
        }

        input:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background-color: var(--accent-primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn:hover {
            background-color: var(--accent-hover);
        }

        .btn-update {
            background-color: var(--accent-warning);
            margin-top: 15px;
        }

        .btn-update:hover {
            background-color: var(--accent-warning-hover);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            margin-top: 15px;
        }

        .btn-outline:hover {
            background-color: var(--bg-base);
            color: var(--text-primary);
        }

        .alert-box {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.5;
        }

        .alert-box i {
            margin-top: 2px;
        }

        .alert.error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--status-error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert.success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--status-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .meta-text {
            font-size: 13px;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="core-container">
    <?php if (!isset($_SESSION['login'])): ?>
        <div class="header-section">
            <i class="fa-solid fa-server"></i>
            <h2>System Authentication</h2>
        </div>
        
        <?php if (isset($error_msg)) echo "<div class='alert-box alert error'><i class='fa-solid fa-circle-exclamation'></i> <div>$error_msg</div></div>"; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Masukan Kunci Akses" required>
                </div>
            </div>
            <button type="submit" class="btn"><i class="fa-solid fa-arrow-right-to-bracket"></i> Verifikasi</button>
        </form>

    <?php else: ?>
        <div class="header-section">
            <i class="fa-solid fa-bolt"></i>
            <h2>File Leeching Engine</h2>
        </div>

        <?php if (!empty($pesan)) echo $pesan; ?>

        <form method="POST" action="">
            <div class="form-group">
                <div class="input-wrapper">
                    <i class="fa-solid fa-link"></i>
                    <input type="url" name="url_download" placeholder="Masukkan Target URL (Dukungan: MediaFire & Direct)" required autocomplete="off">
                </div>
            </div>
            <button type="submit" class="btn" onclick="this.innerHTML='<i class=\'fa-solid fa-circle-notch fa-spin\'></i> Memproses Permintaan...';"><i class="fa-solid fa-cloud-arrow-down"></i> Mulai Penarikan File</button>
        </form>
        
        <a href="?action=update" class="btn btn-update"><i class="fa-solid fa-code-merge"></i> Sinkronisasi Kode dari GitHub</a>
        <a href="?action=logout" class="btn btn-outline"><i class="fa-solid fa-power-off"></i> Akhiri Sesi</a>

        <p class="meta-text"><i class="fa-solid fa-shield-halved"></i> Koneksi dienkripsi dan diproses di latar belakang.</p>
    <?php endif; ?>
</div>

</body>
</html>
