.PHONY: help install pint stan test catraca check release clean

RELEASE_VERSION := $(if $(VERSION),$(VERSION),$(version))
RELEASE_MESSAGE := $(if $(MESSAGE),$(MESSAGE),$(message))

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install dependencies
	composer install

pint: ## Run code style fixer
	vendor/bin/pint --dirty

pint-all: ## Run code style fixer on all files
	vendor/bin/pint

stan: ## Run PHPStan analysis
	vendor/bin/phpstan analyse src --level=max

test: ## Run tests
	vendor/bin/pest --parallel

test-coverage: ## Run tests with coverage
	vendor/bin/pest --parallel --coverage

catraca: ## Run quality gate
	vendor/bin/catraca

check: ## Run all checks (pint + stan + test + catraca)
	vendor/bin/pint --dirty && vendor/bin/phpstan analyse src --level=max && vendor/bin/pest --parallel && vendor/bin/catraca

release: check ## Run quality gates and create Git tag (version=x.y.z message='msg')
	@if [ -f version ]; then \
		LAST_VERSION=$$(cat version); \
		echo "📌 Last version: v$$LAST_VERSION"; \
		echo ""; \
	fi; \
	VERSION_INPUT="$(RELEASE_VERSION)"; \
	if [ -z "$$VERSION_INPUT" ]; then \
		read -p "Enter release version (format x.y.z): " VERSION_INPUT; \
	fi; \
	if ! echo "$$VERSION_INPUT" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$$'; then \
		echo "❌ Invalid version format. Expected x.y.z (e.g., 1.3.0)"; exit 1; \
	fi; \
	echo "📦 New version: v$$VERSION_INPUT"; \
	MESSAGE_INPUT="$(RELEASE_MESSAGE)"; \
	if [ -z "$$MESSAGE_INPUT" ]; then \
		echo "Enter release message (press Enter for default, Ctrl+D when done for multi-line):"; \
		MESSAGE_INPUT=$$(cat); \
		if [ -z "$$MESSAGE_INPUT" ]; then \
			MESSAGE_INPUT="Release v$$VERSION_INPUT"; \
		fi; \
	fi; \
	echo "🔍 Checking for uncommitted changes..."; \
	if ! git diff --quiet || ! git diff --cached --quiet; then \
		echo "📝 Found uncommitted changes. Staging files..."; \
		git add -A; \
		echo "💾 Creating commit..."; \
		git commit -m "$$MESSAGE_INPUT" || true; \
	else \
		echo "✅ Working tree is clean."; \
	fi; \
	echo "🚀 Pushing commits to origin..."; \
	if git push origin HEAD; then \
		echo "✅ Push successful!"; \
	else \
		echo "⚠️  No commits to push (working tree was clean)"; \
	fi; \
	echo "🏷️  Creating tag v$$VERSION_INPUT..."; \
	git tag -a v$$VERSION_INPUT -m "$$MESSAGE_INPUT"; \
	echo "🚀 Pushing tag to origin..."; \
	if git push origin v$$VERSION_INPUT; then \
		echo "✅ Tag pushed successfully!"; \
		echo "📝 Updating version file..."; \
		echo "$$VERSION_INPUT" > version; \
		git add version; \
		git commit -m "Update version to $$VERSION_INPUT" || true; \
		git push origin HEAD || true; \
	else \
		echo "❌ Failed to push tag"; \
		exit 1; \
	fi; \
	echo ""; \
	echo "✅ Release v$$VERSION_INPUT created successfully!"; \
	echo "📦 Packagist will automatically detect the new version."; \
	echo "🔗 View release: https://github.com/b7s/neuraphp/releases/tag/v$$VERSION_INPUT"

clean: ## Remove vendor and cache
	rm -rf vendor/ .phpstan.cache/