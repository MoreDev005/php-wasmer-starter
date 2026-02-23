<?php
// Matikan error reporting agar tidak merusak output JSON/Binary
error_reporting(0);

$targetUrl = $_GET['target'] ?? null;

if (!$targetUrl) {
    die("Target URL tidak ditemukan.");
}

$ch = curl_init();

// Ambil semua header dari browser
$headers = [];
foreach (getallheaders() as $key => $value) {
    $lowerKey = strtolower($key);
    // Abaikan host dan encoding manual agar cURL yang menangani dekompresinya
    if ($lowerKey !== 'host' && $lowerKey !== 'accept-encoding') {
        $headers[] = "$key: $value";
    }
}

curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// PENTING: Biarkan cURL menangani semua tipe kompresi (gzip/deflate) 
// dan mengembalikannya ke PHP dalam bentuk teks polos
curl_setopt($ch, CURLOPT_ENCODING, ''); 

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0');

$method = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

if ($method !== 'GET') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Error cURL: ' . curl_error($ch);
    exit;
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$resHeader = substr($response, 0, $headerSize);
$resBody = substr($response, $headerSize);
curl_close($ch);

// Pecah header dan kirim kembali ke browser
$resHeadersArray = explode("\r\n", $resHeader);
foreach ($resHeadersArray as $h) {
    // JANGAN teruskan header encoding atau length yang lama 
    // karena body sudah didekompresi oleh cURL di atas
    $lowerH = strtolower($h);
    if (!empty($h) && 
        strpos($lowerH, 'transfer-encoding') === false && 
        strpos($lowerH, 'content-encoding') === false &&
        strpos($lowerH, 'content-length') === false) {
        header($h);
    }
}

// Pastikan tidak ada karakter sebelum atau sesudah echo
echo $resBody;
exit;
