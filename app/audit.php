<?php
// Menghilangkan semua laporan error agar tidak tampil di browser
error_reporting(0);
@ini_set('display_errors', 0);

header('Content-Type: text/plain');
echo "=== SECURE AUDIT REPORT ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "---------------------------\n\n";

/**
 * Fungsi pembantu untuk cek fungsi eksekusi dengan aman
 */
function check_exec($func_name) {
    if (!function_exists($func_name)) return "NOT FOUND";
    
    // Cek apakah masuk daftar disable_functions di php.ini
    $disabled = @ini_get('disable_functions');
    if ($disabled) {
        $disabled_array = array_map('trim', explode(',', $disabled));
        if (in_array($func_name, $disabled_array)) return "DISABLED BY CONFIG";
    }
    
    return "ENABLED";
}

// 1. Audit Fungsi Eksekusi
echo "[1] System Execution Functions:\n";
$targets = ['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec'];
foreach ($targets as $t) {
    echo "- " . str_pad($t, 12) . ": " . check_exec($t) . "\n";
}

// 2. Audit Izin Direktori (Penting untuk Binary Injection)
echo "\n[2] Writable Directories:\n";
$paths = [
    'Current Dir' => '.',
    'Temp Dir'    => sys_get_temp_dir(),
    '/tmp'        => '/tmp',
    '/var/tmp'    => '/var/tmp'
];
foreach ($paths as $label => $path) {
    echo "- " . str_pad($label, 12) . ": " . (@is_writable($path) ? "YES" : "NO") . " ($path)\n";
}

// 3. Test Eksekusi Perintah (Safe Execution)
echo "\n[3] Basic Command Test:\n";
if (check_exec('shell_exec') === "ENABLED") {
    $out = @shell_exec('id 2>&1'); // 2>&1 supaya error shell juga tertangkap
    echo "- Result: " . ($out ? trim($out) : "No output (Possibly restricted)") . "\n";
} else {
    echo "- Result: shell_exec is not available.\n";
}

// 4. Cek Resource & Environment
echo "\n[4] Environment Info:\n";
echo "- PHP User   : " . (@get_current_user()) . "\n";
echo "- PHP Version: " . PHP_VERSION . "\n";
echo "- OS         : " . PHP_OS . "\n";
echo "- BaseDir    : " . (@ini_get('open_basedir') ?: "UNRESTRICTED") . "\n";

// 5. Cek Tool Download di Server (Binary Downloader)
echo "\n[5] Available Binaries:\n";
$tools = ['wget', 'curl', 'python', 'perl', 'node', 'git'];
if (check_exec('shell_exec') === "ENABLED") {
    foreach ($tools as $tool) {
        $path = @shell_exec("which $tool 2>/dev/null");
        echo "- " . str_pad($tool, 8) . ": " . ($path ? trim($path) : "NOT FOUND") . "\n";
    }
} else {
    echo "- Binary check skipped (shell_exec disabled).\n";
}

echo "\n--- End of Audit ---";
?>
