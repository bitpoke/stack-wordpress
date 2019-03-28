<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbols

define('RUNTIME_TESTS_DIR', dirname(__DIR__));
$autoloader = require dirname(RUNTIME_TESTS_DIR) . '/vendor/autoload.php';
$autoloader->add('Presslabs\\Tests\\', RUNTIME_TESTS_DIR);

require_once(dirname(RUNTIME_TESTS_DIR) . '/wordpress-develop/tests/phpunit/includes/bootstrap.php');
