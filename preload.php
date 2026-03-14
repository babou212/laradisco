<?php

/**
 * OPcache preloading script for Laravel Octane / FrankenPHP.
 *
 * Compiles all Composer classmap files into shared OPcache memory at startup,
 * so every Octane worker shares the same pre-compiled opcodes. This reduces
 * per-worker memory usage and eliminates file stat calls on first load.
 */

$classmap = __DIR__ . '/vendor/composer/autoload_classmap.php';

if (!file_exists($classmap)) {
    return;
}

$files = require $classmap;

foreach ($files as $file) {
    try {
        if (is_file($file) && str_ends_with($file, '.php')) {
            opcache_compile_file($file);
        }
    } catch (\Throwable) {
        // Skip files with unresolvable dependencies at preload time
    }
}
