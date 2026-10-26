<?php
require __DIR__ . '/../lib/bootstrap.php';
header('Location: ' . url(current_user() ? '/join.php' : '/login.php'));
