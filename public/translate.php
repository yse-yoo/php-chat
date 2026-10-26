<?php
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $status): never
{
    http_response_code($status);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!current_user()) fail('Unauthorized', 401);
$apiKey = env('GEMINI_API_KEY');
if ($apiKey === '' || $apiKey === 'your_gemini_api_key') fail('Missing API key', 500);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$text = $body['text'] ?? '';
$fromLang = $body['fromLang'] ?? '';
$toLang = $body['toLang'] ?? '';

if (!$text || !$fromLang || !$toLang) fail('パラメータを指定してください', 400);
if ($fromLang === $toLang) fail('翻訳元と翻訳先の言語が同じです', 400);

$prompt = "次の文を{$fromLang}から{$toLang}に、短く正確に1文だけで翻訳して(エラー、解説、補足は不要)。\n\n\"{$text}\"";
$model = env('GEMINI_MODEL', 'gemini-2.5-flash');

$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', "x-goog-api-key: {$apiKey}"],
    CURLOPT_POSTFIELDS => json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['responseMimeType' => 'text/plain'],
    ], JSON_UNESCAPED_UNICODE),
]);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($res === false || $status !== 200) fail('翻訳に失敗しました', 500);

$data = json_decode($res, true);
$translated = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'It seems that the translation failed.';

echo json_encode(compact('text', 'translated', 'fromLang', 'toLang'), JSON_UNESCAPED_UNICODE);
