<?php
namespace Presslabs\Stack;

use \WP_CLI;

/**
 * Manage Presslabs Stack enabled WordPress projects
 */
class CLI
{
    public static function load()
    {
        WP_CLI::add_command('stack', '\Presslabs\Stack\CLI');
    }

    private function getWebroot()
    {
        $absParent = dirname(ABSPATH);
        if (file_exists($absParent. '/wp-config.php') && file_exists($absParent . '/index.php')) {
            // WordPress is installed as a subfolder of the document root
            return $absParent;
        }
        return untrailingslashit(ABSPATH);
    }

    private function relativeWebroot(string $cwd = "")
    {
        $webroot = $this->getWebroot();
        $cwd = $cwd ?: getcwd();
        if (substr($webroot, 0, strlen($cwd)) === $cwd) {
            return ltrim(substr($webroot, strlen($cwd)), '/');
        }
        return $webroot;
    }

    private function dockerfile(string $webroot = "")
    {
        $webroot = $webroot ?: $this->relativeWebroot();
        return <<<EOF
FROM quay.io/presslabs/wordpress-runtime:5.1-latest as builder
RUN rm -rf /var/www/html
COPY --chown=www-data:www-data . /var/www
WORKDIR /var/www
RUN composer install -n --no-ansi --no-dev --prefer-dist && rm -rf .composer

FROM quay.io/presslabs/wordpress-runtime:5.1-latest
ENV DOCUMENT_ROOT=/var/www/$webroot
RUN rm -rf /var/www/html
COPY --from=builder /var/www /var/www
EOF;
    }

    /**
     * Initializes Presslabs Stack for a project
     *
     * [--force]
     * : Whether or not to overwrite existing files
     *
     * ## EXAMPLES
     *
     *     wp stack init
     *
     * @when after_wp_load
     */
    public function init()
    {
        $webroot = $this->relativeWebroot();
        WP_CLI::log("Detected webroot in $webroot");
        WP_CLI::success($dockerfile);
    }
}
