.PHONY: test lint cs-check cs-fix before-commit

test:
	php bin/phpunit

lint:
	php bin/console lint:container
	php bin/console lint:yaml config/

cs-check:
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	vendor/bin/php-cs-fixer fix

before-commit: lint cs-check test
