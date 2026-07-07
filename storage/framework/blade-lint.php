<?php
foreach (['resources/views/notifications/index.blade.php', 'resources/views/partials/message-notifications.blade.php', 'resources/views/layouts/app.blade.php'] as $v) {
    $tmp = sys_get_temp_dir() . '/chk.php';
    file_put_contents($tmp, app('blade.compiler')->compileString(file_get_contents($v)));
    exec('php -l ' . escapeshellarg($tmp), $o, $c);
    echo $v . ': ' . ($c === 0 ? 'OK' : 'SYNTAX ERROR') . PHP_EOL;
}
