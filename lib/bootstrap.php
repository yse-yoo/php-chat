<?php
declare(strict_types=1);

// 公開パス（/php-chat や 空文字）。Alias でサブパス配下に置いても動くようにする
function base_path(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $dir;
}

function url(string $path): string
{
    return base_path() . $path;
}

// 他プロジェクトとセッションが衝突しないよう、名前と Cookie の path を分ける
session_name('chat_sid');
session_set_cookie_params(['path' => base_path() . '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

// .env 読み込み
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\"'");
    }
}

function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? $default;
}

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function db(): PDO
{
    static $pdo = null;
    return $pdo ??= new PDO(env('DB_DSN'), env('DB_USER'), env('DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function base64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

// next-chat-server が検証する HS256 JWT
function jwt_sign(array $payload, int $ttl = 604800): string
{
    $payload['iat'] = time();
    $payload['exp'] = time() + $ttl;
    $h = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = base64url(json_encode($payload));
    $sig = base64url(hash_hmac('sha256', "$h.$p", env('JWT_SECRET', 'next-chat-secret'), true));
    return "$h.$p.$sig";
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, name, displayName, lang FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . url('/login.php'));
        exit;
    }
    return $user;
}

function rooms(): array
{
    return ['a' => 'Room A', 'b' => 'Room B', 'c' => 'Room C'];
}
