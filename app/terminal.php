<?php
// Pastikan tidak ada spasi atau baris kosong sebelum tag php di atas!

session_start();
set_time_limit(0);
header('X-Accel-Buffering: no');

// Bersihkan buffer agar tidak mengganggu respon JSON atau SSE
while (ob_get_level()) ob_end_clean();

// --- 1. FITUR KILL PROCESS (Tombol STOP) ---
if (isset($_POST['action']) && $_POST['action'] === 'kill') {
    header('Content-Type: application/json');
    
    if (isset($_SESSION['current_pid'])) {
        $pid = (int)$_SESSION['current_pid'];
        
        if ($pid > 0) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                shell_exec("taskkill /F /PID $pid 2>&1");
            } else {
                shell_exec("kill -9 $pid 2>&1");
            }
        }
        
        unset($_SESSION['current_pid']);
        echo json_encode(['status' => 'killed', 'pid' => $pid]);
    } else {
        echo json_encode(['status' => 'no_active_process']);
    }
    exit;
}

// --- 2. VALIDASI POST (Menerima Perintah) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    header('Content-Type: application/json');
    // Simpan ke session untuk cadangan jika diperlukan
    $_SESSION['last_command'] = $_POST['command']; 
    echo json_encode(['status' => 'ready']);
    exit;
}

// --- 3. STREAM OUTPUT (SSE) ---
if (isset($_GET['stream']) && isset($_GET['cmd'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $cmd = base64_decode($_GET['cmd']);
    
    if (!$cmd) {
        echo "data: Error: Command empty.\n\n";
        echo "data: [DONE]\n\n";
        exit;
    }

    $descriptorspec = [
       1 => ["pipe", "w"], 
       2 => ["pipe", "w"]  
    ];

    $process = proc_open($cmd . " 2>&1", $descriptorspec, $pipes);

    if (is_resource($process)) {
        $status = proc_get_status($process);
        $_SESSION['current_pid'] = $status['pid'];
        
        // PENTING: Tutup session lock agar POST 'kill' bisa masuk
        session_write_close(); 

        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            if ($line !== false) {
                // rtrim agar format SSE data: \n\n tetap bersih
                echo "data: " . htmlspecialchars(rtrim($line)) . "\n\n";
            }
            
            flush();

            // Cek jika koneksi user terputus (tab ditutup)
            if (connection_aborted()) {
                if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                    shell_exec("kill -9 " . $status['pid']);
                }
                break;
            }
        }

        fclose($pipes[1]);
        proc_close($process);
        
        // Buka session lagi untuk hapus PID setelah selesai
        session_start();
        unset($_SESSION['current_pid']);
        session_write_close();
    }

    echo "data: [DONE]\n\n";
    exit;
}
