<?php
// Thử DuckDuckGo Images API (không cần auth, trả về JSON)
$title  = "Chí Phèo";
$author = "Nam Cao";
$q = urlencode("bìa sách $title $author");

// DuckDuckGo Image search (sử dụng vqd token trước)
$initUrl = "https://duckduckgo.com/?q=$q&iax=images&ia=images";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $initUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124'],
]);
$init = curl_exec($ch);
curl_close($ch);

preg_match('/vqd=([\d-]+)/', $init, $vqd);
echo "VQD: " . ($vqd[1] ?? 'not found') . "\n";

if (!empty($vqd[1])) {
    $imgUrl = "https://duckduckgo.com/i.js?l=vn-vi&o=json&q=$q&vqd=" . urlencode($vqd[1]);
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $imgUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124',
            'Referer: https://duckduckgo.com/',
        ],
    ]);
    $json = curl_exec($ch2);
    $code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    echo "Image API: HTTP $code\n";
    $data = json_decode($json, true);
    if (!empty($data['results'])) {
        echo "Found " . count($data['results']) . " images!\n";
        foreach (array_slice($data['results'], 0, 3) as $r) {
            echo "  " . ($r['image'] ?? '') . "\n";
        }
    } else {
        echo "Response: " . substr($json, 0, 300) . "\n";
    }
}
