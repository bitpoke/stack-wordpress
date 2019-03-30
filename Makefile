PHPUNIT ?= $(PWD)/hack/php-noxdebug $(PWD)/vendor/bin/phpunit
PATCHES = $(shell find $(CURDIR)/patches -type f -name '*.diff')

WP_FILES = $(patsubst wordpress/%,%,$(shell find wordpress -path wordpress/wp-content -prune -o -type f -print))
WP_CONTENT_FILES = $(patsubst wordpress/%,%,$(shell find wordpress/wp-content -type f))
STACK_INTEGRATION_FILES = $(patsubst src/%,%,$(shell find src -type f))

WP_DEV_BUILD_FILES = $(patsubst %,wordpress-develop/build/%,$(WP_FILES))

WP_BUILD_FILES = $(patsubst %,build/%,$(WP_FILES))
WP_BUILD_FILES += $(patsubst %,build/%,$(WP_CONTENT_FILES))
WP_BUILD_FILES += $(patsubst %,build/wp-oem/%,$(STACK_INTEGRATION_FILES))

all:
	@echo "ALL"

.PHONY: patch patch-verify $(PATCHES)
patch: $(PATCHES)
$(PATCHES):
	./hack/patch -d wordpress -p1 -i $@

patch-verify:
	set -e; \
	for p in $(PATCHES); do \
		patch -R -s -f --dry-run --silent -d wordpress -p1 -i $$p; \
	done

.PHONY: test
test: test-runtime test-wp

.PHONY: test-runtime
test-runtime: wordpress-develop
	$(PHPUNIT) --verbose

.PHONY: test-wp
test-wp: wordpress-develop
	cd wordpress-develop && $(PHPUNIT) --verbose \
		--exclude-group ajax,ms-files,ms-required,external-http,import \
		$(ARGS)

.PHONY: composer-build
composer-build: $(WP_BUILD_FILES)

build/composer.json: hack/composer.json
	@mkdir -p $$(dirname "$@")
	cp $< $@

build/wp-oem/%: src/%
	@mkdir -p $$(dirname "$@")
	cp $< $@

build/%: wordpress/%
	@mkdir -p $$(dirname "$@")
	cp $< $@

.PHONY: wordpress-develop
wordpress-develop: wordpress-develop/wp-tests-config.php $(WP_DEV_BUILD_FILES)
	rm -rf wordpress-develop/build/wp-content
	cp -a wordpress-develop/src/wp-content wordpress-develop/build/

wordpress-develop/wp-tests-config.php: hack/wp-tests-config.php
	cp $< $@

wordpress-develop/build/%: wordpress/%
	@mkdir -p $$(dirname "$@")
	cp $< $@
