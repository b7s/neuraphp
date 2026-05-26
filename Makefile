.PHONY: help install pint stan test catraca check clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install dependencies
	composer install

pint: ## Run code style fixer
	vendor/bin/pint --dirty

pint:all: ## Run code style fixer on all files
	vendor/bin/pint

stan: ## Run PHPStan analysis
	phpstan analyse src --level=max

test: ## Run tests
	vendor/bin/pest --parallel

test:coverage: ## Run tests with coverage
	vendor/bin/pest --parallel --coverage

catraca: ## Run quality gate
	vendor/bin/catraca

check: ## Run all checks (pint + stan + test + catraca)
	vendor/bin/pint --dirty && phpstan analyse src --level=max && vendor/bin/pest --parallel && vendor/bin/catraca

clean: ## Remove vendor and cache
	rm -rf vendor/ .phpstan.cache/