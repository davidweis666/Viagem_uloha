<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . '/' . $class . '.php';

    if (is_file($path)) {
        require $path;
    }
});
