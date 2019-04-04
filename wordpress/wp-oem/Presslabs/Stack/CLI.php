<?php
namespace Presslabs\Stack;

use \WP_CLI;

define('RELEASE', basename(getcwd()));
define('SKAFFOLD_DIR', getcwd());
define('DEFAULT_DOMAIN', 'test.local.wp');
define('CHART_PATH', SKAFFOLD_DIR . '/chart/');
define('CHART_URL', 'https://github.com/presslabs/charts/raw/master/docs/wordpress-site-v0.1.5.tgz');

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

    private function exists(string $path) {
        return file_exists(SKAFFOLD_DIR . '/' . $path);
    }

    private function writeFile(string $path,  string $content)
    {
        if (! file_exists(SKAFFOLD_DIR)) {
            mkdir(SKAFFOLD_DIR);
        }

        $path = path_join(SKAFFOLD_DIR, $path);

        $fp = fopen($path, 'w');
        fwrite($fp, $content);
        fclose($fp);
    }

    private function chartArchive()
    {
        $response = wp_remote_get(CHART_URL);

        if (! is_array($response)) {
            WP_CLI::error("Couldn't download site chart archive from " . CHART_URL);
            return;
        }

        $this->writeFile('chart.tgz', $response['body']);

        if (! file_exists(CHART_PATH)) {
            mkdir(CHART_PATH);
        }

        $phar = new \PharData(SKAFFOLD_DIR . '/chart.tgz');
        $phar->extractTo(CHART_PATH, null, true);

        unlink(SKAFFOLD_DIR . '/chart.tgz');
    }

    private function chartValues(array $domains = null, string $release = null) {
        $domains = join(", ", $domains);
        $release = $release ?: RELEASE;

        return <<<EOF
site:
  domains: [$domains]
image:
  repository: presslabs-stack/$release
EOF;
    }

    private function skaffold(string $release = '')
    {
        $release = $release ?: RELEASE;
        $chartPath = CHART_PATH . '/wordpress-site';

        return <<<EOF
apiVersion: skaffold/v1beta7
kind: Config
build:
  artifacts:
  - image: presslabs-stack/$release
deploy:
  helm:
    releases:
    - name: $release
      chartPath: $chartPath
      valuesFiles: ['chart/values.yaml']
EOF;
    }

    private function dockerfile(string $webroot = "")
    {
        $webroot = $webroot ?: $this->relativeWebroot();
        return <<<EOF
FROM quay.io/presslabs/wordpress-runtime:5.1-latest as builder
RUN rm -rf /var/www/html
COPY --chown=www-data:www-data . /var/www
WORKDIR /var/www
RUN composer install -n --no-ansi --no-dev --prefer-dist 
RUN rm -rf .composer

FROM quay.io/presslabs/wordpress-runtime:5.1-latest
ENV DOCUMENT_ROOT=/var/www/$webroot
RUN rm -rf /var/www/html
COPY --from=builder /var/www /var/www
EOF;
    }

    private function dockerignore()
    {
        return <<<EOF
.git
*.md
skaffold.yaml
.env*
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
    public function init($args, $assoc_args)
    {
        if (! count($assoc_args)) {
            $requirements = array('Dockerfile', 'skaffold.yaml', '.dockerignore',
                                  'chart/', 'chart/values.yaml');
            $found = array();
            foreach ($requirements as $requirement) {
                if (file_exists(SKAFFOLD_DIR . '/' . $requirement)) {
                    array_push($found, $requirement);
                }
            }

            if (count($found)) {
                echo "❌ Found existing " . join(", ", $found) . " files.\n";
                echo "Do you want to overwrite them? [y/N]";

                $response = trim(fgets(STDIN)) ?: 'N';
                if (strtoupper($response) == 'N') {
                    WP_CLI::success("🙌 Stack initialized successfuly!");
                    return;
                }
            }
        }

        $webroot = $this->relativeWebroot();
        WP_CLI::log("🔍 Detected webroot in $webroot");

        $this->writeFile('Dockerfile', $this->dockerfile());
        WP_CLI::log("📋 Dockerfile created");
        $this->writeFile('skaffold.yaml', $this->skaffold());
        WP_CLI::log("📋 skaffold.yaml created");
        $this->writeFile('.dockerignore', $this->dockerignore());
        WP_CLI::log("📋 .dockerignore created");

        $this->chartArchive();
        WP_CLI::log("📋 chart/ crated");

        echo "Input your site's domains, separated by comma [" . DEFAULT_DOMAIN . "]: ";
        $rawDomains = trim(fgets(STDIN)) ?: DEFAULT_DOMAIN;
        $this->writeFile('chart/values.yaml', $this->chartValues(explode(',', $rawDomains)));
        WP_CLI::log("📋 chart/values.yaml crated");

        WP_CLI::success("🙌 Stack initialized successfuly!");
    }
}
