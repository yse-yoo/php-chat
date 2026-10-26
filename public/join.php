<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$user = require_user();
page_head('ルーム選択'); ?>
<main class="max-w-sm mx-auto mt-20 p-6 shadow-md rounded-lg">
    <p class="mb-4 text-sm"><?= e($user['name']) ?> さん | <?= e($user['lang']) ?></p>
    <form method="get" action="<?= e(url('/chat.php')) ?>" class="space-y-3">
        <select name="room" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            <?php foreach (rooms() as $value => $label): ?>
                <option value="<?= e($value) ?>"><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <button class="w-full px-3 py-2 text-white bg-sky-500 rounded-lg hover:bg-sky-600 transition">Join</button>
    </form>
    <a href="<?= e(url('/logout.php')) ?>" class="block mt-4 text-center text-xs text-red-500">ログアウト</a>
</main>
<?php page_foot();
