<?php

declare(strict_types=1);

$monorepoVendor = dirname(__DIR__, 2).'/vendor/autoload.php';
if (is_file($monorepoVendor)) {
    require $monorepoVendor;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Symfinity\\Bundle\\PokerPlanner\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__).'/src/'.str_replace('\\', '/', $relative).'.php';
    if (is_file($path)) {
        require $path;
    }
});
