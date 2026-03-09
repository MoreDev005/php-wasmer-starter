<?php
@error_reporting(0);
@ini_set('display_errors', 0);

header('Content-Type: text/plain');
echo "=== DEEP SECURITY AUDIT V3 ===\n";
echo "Target Host: " . $_SERVER['HTTP_HOST'] . "\n";
echo "------------------------------\n\n";

function check($f) {
    if (!function_exists($f)) return "MISSING";
    $disabled = explode(',', @ini_get('disable_functions'));
    if (in_array($f, array_map('trim', $disabled))) return "DISABLED";
    return "ENABLED";
}

// 1. Cek Fungsi Eksekusi Lapis Kedua
echo "[1] Alternative Execution:\n";
$alt_exec = ['mail', 'mb_send_mail', 'error_log', 'putenv', 'dl', 'dl_local', 'assert'];
foreach ($alt_exec as $f) {
    echo "- " . str_pad($f, 13) . ": " . check($f) . "\n";
}

// 2. Cek Koneksi Network (Penting buat Bot WA/Socket)
echo "\n[2] Networking Capabilities:\n";
$nets = ['fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server', 'curl_init'];
foreach ($nets as $n) {
    echo "- " . str_pad($n, 13) . ": " . check($n) . "\n";
}

// 3. Cek CGI & Path Info
echo "\n[3] System Environment:\n";
echo "- Server Soft: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "- Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "- PHP Interface: " . php_sapi_name() . "\n";
echo "- User ID      : " . (check('posix_getpwuid') == "ENABLED" ? @posix_getpwuid(@posix_getuid())['name'] : "Unknown") . "\n";

// 4. Cek Akses ke Binari Langsung (Tanpa Shell)
echo "\n[4] Common Binaries Path Check:\n";
$bins = ['/usr/bin/php', '/usr/bin/python', '/usr/bin/perl', '/usr/bin/node', '/usr/bin/wget', '/usr/bin/curl'];
foreach ($bins as $b) {
    echo "- " . str_pad($b, 16) . ": " . (@file_exists($b) ? "FOUND" : "NOT FOUND") . "\n";
}

// 5. Cek Stream Wrappers (Celah LFI/RCE)
echo "\n[5] Stream Wrappers:\n";
$wrappers = stream_get_wrappers();
echo "- Available: " . implode(', ', $wrappers) . "\n";

echo "\n--- End of Deep Audit ---";
?>
