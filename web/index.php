<?php
// This magic trick fixes our dev process. It's safe to create the `wordpress/wp-config.php` symlink since it's
// gitignored.
if (!file_exists(dirname(__DIR__) . '/wordpress/wp-config.php') && file_exists(__DIR__ . '/wp-config.php')) {
    symlink(__DIR__ . '/wp-config.php', dirname(__DIR__) . '/wordpress/wp-config.php');
}

/** WordPress view bootstrapper */
define('WP_USE_THEMES', true);
require __DIR__ . '/wp/wp-blog-header.php';
