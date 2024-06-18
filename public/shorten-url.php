<?php

// 設定と初期化
$apiUrl = 'https://st.pinkgold.space/api/v2/links';
$apiKey = '3FvBSwHlcBPLtTRPJnxyftsJDI58ZOT8u5w8QwLG';

// リクエストを取得
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$originalUrl = $input['originalUrl'] ?? '';

if (empty($originalUrl)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Original URL is missing']);
    exit;
}

// cURLを使用してAPIリクエストを送信
$ch = curl_init($apiUrl);
$payload = json_encode([
    'target' => $originalUrl,
    'reuse' => true
]);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    header('Content-Type: application/json');
    echo json_encode(['error' => $error_msg]);
    exit;
}

curl_close($ch);

header('Content-Type: application/json');
http_response_code($httpCode);
echo $apiResponse;

