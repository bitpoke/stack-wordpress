<?php
// vim: set ft=php:

Presslabs\Config::loadDefaults();

if (constant('MEMCACHED_DISCOVERY_HOST')) {
    $this->servers = array_map(function ($server) {
        return array($server['host'], (int)$server['port'] ?: 11211);
    }, \Presslabs\DNSDiscovery::cachedDiscover(constant('MEMCACHED_DISCOVERY_HOST')));

    if (count($this->servers) != 0) {
        require_once WP_OEM_DIR . '/object-cache-proxy.php';
    }
}


if (defined('MEMCACHED_HOST') and MEMCACHED_HOST != '') {
    $host = '';
    $port = '';
    $server = explode(':', constant('MEMCACHED_HOST'));

    if (count($server) == 1) {
        $host = $server[0];
        $port = '11211';
    } else {
        $host = $server[0];
        $port = $server[1];
    }

    $connection = @fsockopen($host, $port);
    if (is_resource($connection)) {
        require_once WP_OEM_DIR . '/object-cache-proxy.php';
    }
}
