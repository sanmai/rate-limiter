.PHONY: ci test prerequisites

# Leave out a window to specify PHP executable with `make PHP=/path/to/php`
PHP=$(shell which php)

# Default parallelism
JOBS=$(shell nproc)

# Default silencer if installed from moreutils (hides output unless the program fails)
SILENT=$(shell which chronic)

# PHP CS Fixer
PHP_CS_FIXER=vendor/bin/php-cs-fixer
PHP_CS_FIXER_ARGS=--cache-file=build/cache/.php_cs.cache --verbose
export PHP_CS_FIXER_IGNORE_ENV=1

# PHPUnit
PHPUNIT=vendor/bin/phpunit
PHPUNIT_COVERAGE_CLOVER=--coverage-clover=build/logs/clover.xml
PHPUNIT_GROUP=default
PHPUNIT_ARGS=--coverage-xml=build/logs/coverage-xml --log-junit=build/logs/junit.xml $(PHPUNIT_COVERAGE_CLOVER)
export XDEBUG_MODE=coverage

# PHPStan
PHPSTAN=vendor/bin/phpstan
PHPSTAN_ARGS_TESTS=analyse src tests -c .phpstan.neon
PHPSTAN_ARGS_SRC=analyse -c .phpstan.src.neon

# Composer
COMPOSER=$(PHP) $(shell which composer)

# Infection
INFECTION=vendor/bin/infection
MIN_MSI=84
MIN_COVERED_MSI=84
INFECTION_ARGS=--min-msi=$(MIN_MSI) --min-covered-msi=$(MIN_COVERED_MSI) --threads=$(JOBS) --coverage=build/logs --show-mutations --no-interaction

all: test

##############################################################
# Development Workflow                                       #
##############################################################

.PHONY: test
test: phpunit composer-validate cs sa yamllint

.PHONY: composer-validate
composer-validate: prerequisites
	$(SILENT) $(COMPOSER) validate --strict
	$(SILENT) $(COMPOSER) normalize --diff

.PHONY: phpunit
phpunit: cs prerequisites
	$(SILENT) $(PHP) $(PHPUNIT) --group=$(PHPUNIT_GROUP)

phpunit-coverage: cs prerequisites
	rm -fr build/logs/*
	$(SILENT) $(PHP) $(PHPUNIT) $(PHPUNIT_ARGS)

.PHONY: mt
mt: phpunit-coverage prerequisites infection.json.dist
	$(SILENT) $(PHP) $(INFECTION) $(INFECTION_ARGS)

.PHONY: sa
sa: phpstan

.PHONY: phpstan
phpstan: cs .phpstan.src.neon .phpstan.neon
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS_SRC)
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS_TESTS)

.PHONY: cs
cs: prerequisites
	$(SILENT) $(PHP) $(PHP_CS_FIXER) $(PHP_CS_FIXER_ARGS) --diff fix

##############################################################
# Prerequisites Setup                                        #
##############################################################

# We need both vendor/autoload.php and composer.lock being up to date
.PHONY: prerequisites
prerequisites: report-php-location build/cache vendor/autoload.php composer.lock

# Do install if there's no 'vendor'
vendor/autoload.php:
	$(SILENT) $(COMPOSER) install --prefer-dist --no-progress --no-interaction

# If composer.lock is older than `composer.json`, do update,
# and touch composer.lock because composer not always does that
composer.lock: composer.json
	$(SILENT) $(COMPOSER) update && touch composer.lock

build/cache:
	mkdir -p build/cache

.PHONY: report-php-location
report-php-location:
	# Using $(PHP)

.PHONY: yamllint
yamllint:
	@find .github/ -name \*.y*ml -print0 | xargs --no-run-if-empty -n 1 -0 yamllint --no-warnings
	@find . -maxdepth 1 -name \*.y*ml -print0 | xargs --no-run-if-empty -n 1 -0 yamllint --no-warnings
