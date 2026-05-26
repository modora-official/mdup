<?php
session_start();

// Mencegah batas waktu dan memori untuk file ukuran GB
@ini_set('max_execution_time', '0');
@ini_set('max_input_time', '0');
@ini_set('memory_limit', '-1');
@ini_set('post_max_size', '0');
@ini_set('upload_max_filesize', '0');

// ==========================================
// 1. KONFIGURASI UTAMA
// ==========================================
$PASSWORD_AKSES = "rahasia123";
$DIREKTORI_UPLOAD = __DIR__ . '/uploads/';
$GITHUB_RAW_URL = "https://raw.githubusercontent.com/modora-official/mdup/main/index.php";

if (!file_exists($DIREKTORI_UPLOAD)) {
    mkdir($DIREKTORI_UPLOAD, 0755, true);
}

// ==========================================
// 2. OTENTIKASI
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

function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    elseif ($bytes > 1) return $bytes . ' bytes';
    elseif ($bytes == 1) return $bytes . ' byte';
    else return '0 bytes';
}

// ==========================================
// 3. FITUR AUTO-UPDATE
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_SESSION['login'])) {
    $ch_update = curl_init($GITHUB_RAW_URL);
    curl_setopt($ch_update, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_update, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch_update, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $kode_baru = curl_exec($ch_update);
    $http_code = curl_getinfo($ch_update, CURLINFO_HTTP_CODE);
    curl_close($ch_update);

    if ($http_code == 200 && !empty($kode_baru)) {
        if (file_put_contents(__FILE__, $kode_baru)) {
            $pesan = "<div class='alert success'><i class='fa-solid fa-circle-check'></i> Sistem berhasil disinkronisasi dengan GitHub!</div>";
        } else {
            $pesan = "<div class='alert error'><i class='fa-solid fa-xmark'></i> Gagal menyimpan pembaruan (Periksa hak akses file).</div>";
        }
    } else {
        $pesan = "<div class='alert error'><i class='fa-solid fa-plug-circle-xmark'></i> Gagal mengambil kode dari GitHub.</div>";
    }
}

// ==========================================
// 4. FITUR HAPUS FILE
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['file']) && isset($_SESSION['login'])) {
    $file_to_delete = basename($_GET['file']);
    $target_delete = $DIREKTORI_UPLOAD . $file_to_delete;
    if (file_exists($target_delete) && is_file($target_delete)) {
        unlink($target_delete);
        $pesan = "<div class='alert success'><i class='fa-solid fa-trash-can'></i> File <strong>" . htmlspecialchars($file_to_delete) . "</strong> berhasil dihapus.</div>";
    }
}

// ==========================================
// 5. LOGIKA LEECHING & UPLOAD (AJAX Handler)
// ==========================================
function getFilenameFromUrl($url, $header) {
    if (preg_match('/filename="(.*?)"/', $header, $matches)) return $matches[1];
    $basename = basename(parse_url($url, PHP_URL_PATH));
    return $basename ? $basename : 'downloaded_' . time();
}

if (isset($_SESSION['login']) && isset($_POST['action_type'])) {
    $custom_name = trim($_POST['custom_name']);
    
    // Mode URL Leeching
    if ($_POST['action_type'] == 'url' && !empty($_POST['url_download'])) {
        $url_input = trim($_POST['url_download']);
        $url_target = $url_input;
        
        // Logika Ekstraktor MediaFire yang Diperkuat
        if (strpos($url_input, 'mediafire.com') !== false) {
            $ch_mf = curl_init($url_input);
            curl_setopt($ch_mf, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_mf, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch_mf, CURLOPT_SSL_VERIFYPEER, false);
            // Tambahkan User-Agent browser asli dan izinkan encode GZIP
            curl_setopt($ch_mf, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch_mf, CURLOPT_ENCODING, "");
            $html = curl_exec($ch_mf);
            curl_close($ch_mf);
            
            // Regex agresif: Mencari URL tidak peduli urutan atribut HTML-nya
            if (preg_match('/href="([^"]+)"[^>]*id="downloadButton"/i', $html, $matches) || 
                preg_match('/id="downloadButton"[^>]*href="([^"]+)"/i', $html, $matches)) {
                $url_target = $matches[1];
            } else if (preg_match('/\bhref="([^"]+)"/i', $html, $matches_all)) {
                preg_match_all('/href="([^"]+)"/i', $html, $links);
                foreach($links[1] as $link) {
                    if(strpos($link, 'download') !== false && strpos($link, 'mediafire.com') !== false) {
                        $url_target = $link;
                        break;
                    }
                }
            }

            // Keamanan: Cegah unduhan file palsu jika gagal menembus keamanan Mediafire
            if ($url_target == $url_input) {
                $pesan = "<div class='alert error'><i class='fa-solid fa-triangle-exclamation'></i> Ekstensi gagal! Sistem gagal menembus link asli MediaFire. Pastikan Anda tidak memasukkan link Folder.</div>";
            }
        }

        if (empty($pesan)) {
            $ch = curl_init($url_target);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $header_data = curl_exec($ch);
            $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            $nama_file = !empty($custom_name) ? $custom_name : getFilenameFromUrl($final_url, $header_data);
            $nama_file = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $nama_file);
            
            // Perbaikan jika MediaFire tidak memberikan format ekstensi secara utuh
            if(strpos($url_input, '.apk') !== false && strpos($nama_file, '.apk') === false) {
                $nama_file .= '.apk';
            }

            $target_file = $DIREKTORI_UPLOAD . $nama_file;

            $fp = fopen($target_file, 'w+');
            if ($fp) {
                $ch = curl_init($final_url);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $sukses = curl_exec($ch);
                curl_close($ch);
                fclose($fp);
                
                // Pastikan file tidak *corrupt* atau berupa file HTML nyasar
                if ($sukses && filesize($target_file) > 100000) { 
                    $pesan = "<div class='alert success'><i class='fa-solid fa-circle-check'></i> File <strong>$nama_file</strong> berhasil ditarik ke server! (" . formatSizeUnits(filesize($target_file)) . ")</div>";
                } else if ($sukses) {
                    unlink($target_file); 
                    $pesan = "<div class='alert error'><i class='fa-solid fa-circle-xmark'></i> Gagal! MediaFire memblokir tarikan server atau link kadaluarsa.</div>";
                } else {
                    unlink($target_file);
                    $pesan = "<div class='alert error'><i class='fa-solid fa-circle-xmark'></i> Gagal menarik file ke VPS.</div>";
                }
            }
        }
    } 
    // Mode Direct Upload (Via AJAX)
    else if ($_POST['action_type'] == 'local' && isset($_FILES['file_upload'])) {
        $nama_asli = basename($_FILES['file_upload']['name']);
        $ext = pathinfo($nama_asli, PATHINFO_EXTENSION);
        $nama_file = !empty($custom_name) ? $custom_name . (strpos($custom_name, '.') === false && !empty($ext) ? ".$ext" : "") : $nama_asli;
        $nama_file = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $nama_file);
        
        if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $DIREKTORI_UPLOAD . $nama_file)) {
            echo json_encode(["status" => "success", "msg" => "File berhasil diunggah!"]);
        } else {
            echo json_encode(["status" => "error", "msg" => "Gagal menyimpan file. Pastikan limit server diizinkan."]);
        }
        exit; // Hentikan script untuk respon AJAX
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nixy Advanced Uploader</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-base: #09090b;
            --bg-card: #18181b;
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --accent-primary: #3b82f6;
            --accent-hover: #2563eb;
            --accent-danger: #ef4444;
            --border-color: #27272a;
            --status-success: #10b981;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 800px;
        }

        .card {
            background-color: var(--bg-card);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .header-section { text-align: center; margin-bottom: 30px; }
        .header-section i { font-size: 42px; color: var(--accent-primary); margin-bottom: 12px; display: block; }
        .header-section h2 { margin: 0; font-weight: 600; font-size: 24px; }

        .form-group { margin-bottom: 15px; position: relative; }
        .input-icon { position: absolute; left: 16px; top: 15px; color: var(--text-secondary); }
        
        input[type="password"], input[type="url"], input[type="text"], input[type="file"] {
            width: 100%; padding: 14px 16px 14px 48px; background-color: var(--bg-base);
            border: 1px solid var(--border-color); color: var(--text-primary);
            border-radius: 8px; box-sizing: border-box; font-size: 15px; outline: none;
        }
        input[type="file"] { padding-left: 16px; }
        input:focus { border-color: var(--accent-primary); }

        .btn {
            width: 100%; padding: 14px; background-color: var(--accent-primary); color: #fff;
            border: none; border-radius: 8px; font-size: 15px; font-weight: 600;
            cursor: pointer; display: inline-flex; justify-content: center; align-items: center; gap: 10px;
            text-decoration: none; margin-bottom: 10px; transition: 0.2s;
        }
        .btn:hover { background-color: var(--accent-hover); }
        .btn-outline { background-color: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); }
        .btn-outline:hover { background-color: var(--bg-base); color: var(--text-primary); }
        .btn-danger { background-color: var(--accent-danger); padding: 8px 12px; width: auto; font-size: 13px; }

        .alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; gap: 12px; }
        .alert.error { background-color: rgba(239, 68, 68, 0.1); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert.success { background-color: rgba(16, 185, 129, 0.1); color: var(--status-success); border: 1px solid rgba(16, 185, 129, 0.2); }

        /* Progress Bar Styles */
        .progress-container { display: none; margin-top: 20px; }
        .progress-bar-bg { width: 100%; background-color: var(--bg-base); border-radius: 8px; height: 12px; overflow: hidden; border: 1px solid var(--border-color); }
        .progress-bar-fill { height: 100%; background-color: var(--accent-primary); width: 0%; transition: width 0.2s ease; }
        .progress-stats { display: flex; justify-content: space-between; margin-top: 10px; font-size: 13px; color: var(--text-secondary); }
        .stats-highlight { color: var(--status-success); font-weight: bold; }

        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        th { background-color: var(--bg-base); color: var(--text-secondary); font-weight: 600; }
        tr:hover { background-color: rgba(255,255,255,0.02); }
        .empty-table { text-align: center; color: var(--text-secondary); padding: 30px !important; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <?php if (!isset($_SESSION['login'])): ?>
            <div class="header-section">
                <i class="fa-solid fa-shield-halved"></i>
                <h2>Otentikasi Sistem</h2>
            </div>
            <?php if (isset($error_msg)) echo "<div class='alert error'><i class='fa-solid fa-circle-exclamation'></i> <div>$error_msg</div></div>"; ?>
            <form method="POST">
                <div class="form-group">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" name="password" placeholder="Masukan Sandi" required>
                </div>
                <button type="submit" class="btn"><i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk</button>
            </form>
        <?php else: ?>
            <div class="header-section">
                <i class="fa-solid fa-server"></i>
                <h2>Advanced VPS Engine</h2>
            </div>

            <?php if (!empty($pesan)) echo $pesan; ?>

            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <button class="btn btn-outline" onclick="toggleForm('urlForm')"><i class="fa-solid fa-link"></i> URL Leeching</button>
                <button class="btn btn-outline" onclick="toggleForm('localForm')"><i class="fa-solid fa-hard-drive"></i> Local Upload</button>
            </div>

            <form id="urlForm" method="POST" action="">
                <input type="hidden" name="action_type" value="url">
                <div class="form-group">
                    <i class="fa-solid fa-link input-icon"></i>
                    <input type="url" name="url_download" placeholder="Masukkan Target URL (MediaFire / Direct)" required>
                </div>
                <div class="form-group">
                    <i class="fa-solid fa-pen-to-square input-icon"></i>
                    <input type="text" name="custom_name" placeholder="Nama File Kustom (Opsional, beserta ekstensi ex: game.apk)">
                </div>
                <button type="submit" class="btn" onclick="this.innerHTML='<i class=\'fa-solid fa-circle-notch fa-spin\'></i> Sedang Menarik File...';"><i class="fa-solid fa-cloud-arrow-down"></i> Tarik File</button>
            </form>

            <form id="localForm" style="display: none;">
                <input type="hidden" name="action_type" value="local">
                <div class="form-group">
                    <input type="file" name="file_upload" id="fileInput" required>
                </div>
                <div class="form-group">
                    <i class="fa-solid fa-pen-to-square input-icon"></i>
                    <input type="text" name="custom_name" id="customName" placeholder="Nama File Kustom (Opsional)">
                </div>
                <button type="button" class="btn" onclick="uploadWithProgress()"><i class="fa-solid fa-upload"></i> Unggah File</button>
                
                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" id="progressBar"></div>
                    </div>
                    <div class="progress-stats">
                        <span id="speedDisplay"><i class="fa-solid fa-gauge-high"></i> 0 MB/s</span>
                        <span class="stats-highlight" id="percentDisplay">0%</span>
                        <span id="sizeDisplay">0 MB / 0 MB</span>
                    </div>
                </div>
            </form>

            <script>
                function toggleForm(formId) {
                    document.getElementById('urlForm').style.display = formId === 'urlForm' ? 'block' : 'none';
                    document.getElementById('localForm').style.display = formId === 'localForm' ? 'block' : 'none';
                }

                function uploadWithProgress() {
                    const fileInput = document.getElementById('fileInput');
                    if(fileInput.files.length === 0) { alert('Pilih file terlebih dahulu!'); return; }

                    const formData = new FormData();
                    formData.append("action_type", "local");
                    formData.append("file_upload", fileInput.files[0]);
                    formData.append("custom_name", document.getElementById('customName').value);

                    document.getElementById('progressContainer').style.display = 'block';
                    
                    const xhr = new XMLHttpRequest();
                    let startTime = new Date().getTime();
                    let previousLoaded = 0;

                    xhr.upload.addEventListener("progress", function(e) {
                        if (e.lengthComputable) {
                            let percent = Math.round((e.loaded / e.total) * 100);
                            document.getElementById('progressBar').style.width = percent + '%';
                            document.getElementById('percentDisplay').innerText = percent + '%';

                            let currentTime = new Date().getTime();
                            let timeDiff = (currentTime - startTime) / 1000;
                            if(timeDiff > 0.5) {
                                let loadedDiff = e.loaded - previousLoaded;
                                let speedBps = loadedDiff / timeDiff;
                                
                                let speedText = "";
                                if(speedBps > 1048576) speedText = (speedBps / 1048576).toFixed(2) + " MB/s";
                                else if(speedBps > 1024) speedText = (speedBps / 1024).toFixed(2) + " KB/s";
                                else speedText = speedBps.toFixed(0) + " B/s";

                                document.getElementById('speedDisplay').innerHTML = '<i class="fa-solid fa-gauge-high"></i> ' + speedText;
                                
                                startTime = currentTime;
                                previousLoaded = e.loaded;
                            }

                            let totalMB = (e.total / 1048576).toFixed(2);
                            let loadedMB = (e.loaded / 1048576).toFixed(2);
                            document.getElementById('sizeDisplay').innerText = loadedMB + " MB / " + totalMB + " MB";
                        }
                    }, false);

                    xhr.addEventListener("load", function(e) {
                        try {
                            const res = JSON.parse(e.target.responseText);
                            alert(res.msg);
                            window.location.reload();
                        } catch(err) {
                            alert("Upload selesai, merefresh halaman...");
                            window.location.reload();
                        }
                    }, false);

                    xhr.open("POST", window.location.href, true);
                    xhr.send(formData);
                }
            </script>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['login'])): ?>
    <div class="card">
        <div class="header-section" style="margin-bottom: 15px;">
            <i class="fa-solid fa-folder-tree" style="font-size: 28px; margin-bottom: 5px;"></i>
            <h2 style="font-size: 18px;">File Manager Terunggah</h2>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Nama File</th>
                        <th>Ukuran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $files = array_diff(scandir($DIREKTORI_UPLOAD), array('.', '..'));
                    if (count($files) > 0) {
                        foreach ($files as $file) {
                            $path = $DIREKTORI_UPLOAD . $file;
                            if (is_file($path)) {
                                $size = formatSizeUnits(filesize($path));
                                echo "<tr>
                                        <td><i class='fa-solid fa-file-lines' style='color: var(--text-secondary); margin-right: 8px;'></i> " . htmlspecialchars($file) . "</td>
                                        <td>$size</td>
                                        <td>
                                            <a href='?action=delete&file=" . urlencode($file) . "' class='btn btn-danger' onclick='return confirm(\"Hapus file ini permanen?\")'>
                                                <i class='fa-solid fa-trash'></i>
                                            </a>
                                        </td>
                                      </tr>";
                            }
                        }
                    } else {
                        echo "<tr><td colspan='3' class='empty-table'><i class='fa-solid fa-box-open'></i> Belum ada file yang diunggah.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 30px; display: flex; gap: 10px;">
            <a href="?action=update" class="btn btn-outline" style="margin-bottom:0;"><i class="fa-solid fa-code-merge"></i> Sinkronisasi GitHub</a>
            <a href="?action=logout" class="btn btn-outline" style="margin-bottom:0;"><i class="fa-solid fa-power-off"></i> Keluar</a>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
