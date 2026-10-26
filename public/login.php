<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = db()->prepare('SELECT id, password FROM users WHERE name = ?');
    $stmt->execute([$_POST['name'] ?? '']);
    $row = $stmt->fetch();
    // bcrypt ($2b$) は password_verify で検証可能
    if ($row && password_verify($_POST['password'] ?? '', $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['id'];
        header('Location: ' . url('/join.php'));
        exit;
    }
    $error = 'ユーザ名またはパスワードが間違っています';
}

page_head('ログイン'); ?>
<main class="max-w-sm mx-auto mt-20 p-6 shadow-md rounded-lg">
    <h1 class="text-xl font-bold mb-4"><?= e(env('APP_NAME')) ?></h1>
    <?php if ($error): ?><p class="mb-3 text-sm text-red-500"><?= e($error) ?></p><?php endif ?>
    <form method="post" class="space-y-3">
        <input name="name" required placeholder="User name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500">
        <input name="password" type="password" required placeholder="Password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500">
        <button class="w-full px-3 py-2 text-white bg-green-500 rounded-lg hover:bg-green-600 transition">Login</button>
    </form>
</main>
<?php page_foot();
