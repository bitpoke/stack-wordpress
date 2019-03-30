WordPress
===
WordPress integration with Presslabs Stack

## Development
Code goes in `wordpress/wp-oem` and tests go in `tests` folder.

### Prerequisites
* PHP >= 7.2
* [Composer](https://getcomposer.org/download/)
* [wp-cli](https://github.com/wp-cli/wp-cli/releases)

### Getting started
```console
$ git clone git@github.com:presslabs/stack-wordpress.git
$ composer install
```

### Development server
```console
$ wp server
```

### Linting and testing
```console
$ make lint
$ make test
```

### Upgrading vendored WordPress
In order to bump the WordPress version you should use `get-wp` script. The
script fetches the code from https://wordpress.org.

```console
$ ./hack/get-wp VERSION
```

### Upgrading vendored WordPress test suite
In order to bump the WordPress test suite you should use the `get-wp-dev`
script. The script fetches the code from https://github.com/WordPress/wordpress-develop.

```console
$ ./hack/get-wp-dev VERSION
```
