<?php
// get_bili_name.php
header('Content-Type: application/json; charset=utf-8');

$uid = intval($_GET['uid'] ?? 0);
if ($uid <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid uid']);
    exit;
}

/* ================= 缓存 ================= */

$cacheDir  = __DIR__ . '/cache';
$cacheFile = $cacheDir . "/bili_{$uid}.json";
$cacheTTL  = 600; // 10 分钟

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTTL) {
    echo file_get_contents($cacheFile);
    exit;
}

/* ================= 请求函数 ================= */

function bili_request(string $url): ?array {
    $headers = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36",
        "Referer: https://www.bilibili.com/",
        "Accept: application/json",
        "Accept-Language: zh-CN,zh;q=0.9"
    ];

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => implode("\r\n", $headers),
            'timeout' => 5
        ]
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;

    return json_decode($raw, true);
}

/* ================= 主接口（稳定） ================= */

$url = "https://api.bilibili.com/x/web-interface/card?mid={$uid}";
$data = bili_request($url);

if (
    !$data ||
    !isset($data['code']) ||
    $data['code'] !== 0 ||
    !isset($data['data']['card']['name'])
) {
    http_response_code(403);
    echo json_encode(['error' => 'bilibili api blocked']);
    exit;
}

$result = [
    'uid'      => $uid,
    'username' => $data['data']['card']['name']
];

$json = json_encode($result, JSON_UNESCAPED_UNICODE);
file_put_contents($cacheFile, $json);
echo $json;
