<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;

    return;
}

// Запуск без composer install (например, phpunit.phar): простой PSR-4 автозагрузчик.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Krugozor\\RussianBadWords\\Tests\\' => __DIR__ . '/',
        'Krugozor\\RussianBadWords\\' => dirname(__DIR__) . '/src/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;

                return;
            }
        }
    }
});
