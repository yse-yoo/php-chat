<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$user = require_user();
$room = $_GET['room'] ?? '';
if (!isset(rooms()[$room])) {
    header('Location: ' . url('/join.php'));
    exit;
}

$config = [
    'socketUrl' => env('SOCKET_URL', 'http://localhost:3001'),
    'token'     => jwt_sign(['userId' => (int)$user['id'], 'name' => $user['name']]),
    'room'      => $room,
    'base'      => base_path(),
    'user'      => ['id' => (int)$user['id'], 'name' => $user['name'], 'lang' => $user['lang']],
];

page_head('Room ' . strtoupper($room)); ?>
<header class="p-4 text-sm flex justify-between items-center">
    <div><strong>Room <?= e($room) ?></strong> | <?= e($user['name']) ?> さん | <?= e($user['lang']) ?></div>
    <a href="<?= e(url('/logout.php')) ?>" onclick="return confirm('ログアウトしますか？')"
       class="bg-red-500 text-white text-xs px-3 py-1 rounded hover:bg-red-600">ログアウト</a>
</header>

<div id="chat-list" class="pb-[160px] px-4 space-y-2"></div>

<div class="fixed bottom-0 left-0 w-full bg-white z-10">
    <div class="p-6 rounded-lg shadow-md">
        <div id="preview" class="hidden flex justify-center p-4">
            <div>
                <button id="preview-cancel" type="button" class="text-sm text-red-500 px-2 py-1">✖</button>
                <img id="preview-img" alt="preview" class="w-32 h-32 object-cover rounded">
            </div>
        </div>
        <form id="chat-form" class="flex items-center gap-2">
            <input id="text" type="text" autocomplete="off" placeholder="Type or speak your message..."
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500">
            <button type="submit" class="px-3 py-2 text-white bg-green-500 rounded-lg hover:bg-green-600 transition">Send</button>
            <button id="mic" type="button" title="音声入力" class="px-3 py-2 rounded-lg border bg-white border-gray-300 hover:bg-gray-50 transition">🎤</button>
            <label class="px-3 py-2 bg-white border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                <input id="file" type="file" accept="image/*" class="hidden">📷
            </label>
        </form>
    </div>
</div>

<script>window.CHAT = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js"></script>
<script src="<?= e(url('/assets/chat.js')) ?>"></script>
<?php page_foot();
