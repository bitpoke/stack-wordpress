WordPress
===
WordPress integration with Presslabs Stack

## Development

### Upgrading vendored WordPress
In order to bump the WordPress version you should use `get-wp` script. The
script fetches the code from https://wordpress.org.

```console
./hack/get-wp VERSION
```

### Upgrading vendored WordPress test suite
In order to bump the WordPress test suite you should use the `get-wp-dev`
script. The script fetches the code from https://github.com/WordPress/wordpress-develop.

```console
./hack/get-wp-dev VERSION
```
