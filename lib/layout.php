<?php
function page_head(string $title): void { ?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e(env('APP_NAME', 'Chat')) ?></title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
</head>
<body class="bg-white text-gray-700">
<?php }
function page_foot(): void { ?>
</body>
</html>
<?php }
