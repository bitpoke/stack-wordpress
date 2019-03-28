PHPUNIT ?= $(PWD)/hack/php-noxdebug $(PWD)/vendor/bin/phpunit
PATCHES = $(shell find $(CURDIR)/patches -type f -name '*.diff')
WP_FILES = $(patsubst wordpress/%,%,$(shell find wordpress -path wordpress/wp-content -prune -o -type f -print))
WP_BUILD_FILES = $(patsubst %,wordpress-develop/build/%,$(WP_FILES))

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

.PHONY: wordpress-develop
wordpress-develop: wordpress-develop/wp-tests-config.php $(WP_BUILD_FILES)
	rm -rf wordpress-develop/build/wp-content
	cp -a wordpress-develop/src/wp-content wordpress-develop/build/

wordpress-develop/wp-tests-config.php: hack/wp-tests-config.php
	cp $< $@

wordpress-develop/build/%: wordpress/%
	@mkdir -p $$(dirname "$@")
	cp $< $@
