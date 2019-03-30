<?php
/*
Plugin Name:  Presslabs stack integration
Plugin URI:   https://github.com/presslabs/wordpress-runtime
Description:  Integrates WordPress with Presslabs Stack WordPress operator
Version:      1.0.0
Author:       Presslabs
Author URI:   https://presslabs.com/stack
License:      Apache 2.0
*/

Presslabs\Config::loadDefaults();

// Register default theme directory. Copied over from bedrock,
// since we install here without bedrock.
if (!defined('WP_DEFAULT_THEME')) {
    register_theme_directory(ABSPATH . 'wp-content/themes');
}

if (defined('UPLOADS_FTP_HOST') && UPLOADS_FTP_HOST != "") {
    new Presslabs\FTPStorage(UPLOADS_FTP_HOST);
}
