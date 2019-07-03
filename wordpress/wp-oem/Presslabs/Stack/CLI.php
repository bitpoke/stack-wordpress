<?php
namespace Presslabs\Stack;

use \WP_CLI;
use \WP_CLI_Command;

define('RELEASE', basename(getcwd()));
define('SKAFFOLD_DIR', getcwd());
define('DEFAULT_DOMAIN', RELEASE . '.localstack.pl');
define('CHART_DIR', 'chart');
define('CHART_URL', 'https://github.com/presslabs/charts/raw/master/docs/wordpress-site-v0.1.6.tgz');
define('DEFAULT_PROD_KUBECONFIG_CONTEXT', 'default');
/**
 * Manage Presslabs Stack enabled WordPress projects
 */
class CLI extends WP_CLI_Command
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

    private function exists(string $path)
    {
        return file_exists(SKAFFOLD_DIR . '/' . $path);
    }

    private function writeFile(string $path, string $content)
    {
        if (! file_exists(SKAFFOLD_DIR)) {
            mkdir(SKAFFOLD_DIR);
        }

        $path = SKAFFOLD_DIR . "/" . $path;

        $fp = fopen($path, 'w');
        fwrite($fp, $content);
        fclose($fp);
    }

    private function chartArchive()
    {
        if (!file_exists(SKAFFOLD_DIR)) {
            mkdir(SKAFFOLD_DIR);
        }
        if (!@copy(CHART_URL, SKAFFOLD_DIR . '/chart.tar.gz')) {
            $err = error_get_last();
            WP_CLI::error($err["message"], true);
        }


        if (! file_exists(SKAFFOLD_DIR . '/' . CHART_DIR)) {
            mkdir(SKAFFOLD_DIR . '/' . CHART_DIR);
        }

        $phar = new \PharData(SKAFFOLD_DIR . '/chart.tar.gz');
        $phar->extractTo(SKAFFOLD_DIR . '/' . CHART_DIR, null, true);

        unlink(SKAFFOLD_DIR . '/chart.tar.gz');
    }

    private function skaffold(string $devDomain, string $prodDomain, string $dockerImage, string $prodKubeConfig)
    {
        $release = RELEASE;
        $chartPath = CHART_DIR . '/wordpress-site';

        return <<<EOF
apiVersion: skaffold/v1beta9
kind: Config
build:
  artifacts:
  - image: $dockerImage
  tagPolicy:
    dateTime:
      format: 2006-01-02_15-04-05.999_MST
deploy:
  helm:
    releases:
    - name: dev-$release
      chartPath: $chartPath
      values:
        image: $dockerImage
      setValues:
        site.domains[0]: $devDomain
      skipBuildDependencies: false
      imageStrategy:
        helm: {}
profiles:
  - name: production
    activation:
      - command: deploy
        kubeContext: $prodKubeConfig
    patches:
      - op: replace
        path: /deploy/helm/releases/0/name
        value: $release
      - op: replace
        path: /deploy/helm/releases/0/setValues/site.domains[0]
        value: $prodDomain
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
     * @when after_wp_config_load
     */
    public function init($args, $assoc_args)
    {
        if (! count($assoc_args)) {
            $requirements = array('Dockerfile', 'skaffold.yaml', '.dockerignore',
                                  'chart/');
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

        echo "Docker image repository (eg. docker.io/USERNAME/". RELEASE ."): ";
        $dockerImage = trim(fgets(STDIN));
        if (empty($dockerImage)) {
            WP_CLI::error("You must specify a docker image repository!", true);
        }

        echo "Your site's DEVELOPMENT domain [" . DEFAULT_DOMAIN . "]: ";
        $devDomain = trim(fgets(STDIN)) ?: DEFAULT_DOMAIN;

        echo "Your site's PRODUCTION domain [" . $devDomain . "]: ";
        $prodDomain = trim(fgets(STDIN)) ?: $devDomain;

        echo "Your site's PRODUCTION kubeconfing context [" . DEFAULT_PROD_KUBECONFIG_CONTEXT . "]: ";
        $prodKubeContext = trim(fgets(STDIN)) ?: DEFAULT_PROD_KUBECONFIG_CONTEXT;

        $this->writeFile('Dockerfile', $this->dockerfile());
        WP_CLI::log("📋 Dockerfile created");
        $this->writeFile('skaffold.yaml', $this->skaffold($devDomain, $prodDomain, $dockerImage, $prodKubeContext));
        WP_CLI::log("📋 skaffold.yaml created");
        $this->writeFile('.dockerignore', $this->dockerignore());
        WP_CLI::log("📋 .dockerignore created");

        $this->chartArchive();
        WP_CLI::log("📋 chart/ crated");

        WP_CLI::success("🙌 Stack initialized successfuly!");
    }
}
