.PHONY: help setup dev test lint analyse typecheck check fresh

help:
	@printf "%s\n" \
		"setup      install PHP/JS dependencies, generate key, build assets, migrate" \
		"dev        run app server, queue listener, and Vite (composer dev)" \
		"test       run Pest" \
		"lint       Pint --test and TypeScript check" \
		"analyse    PHPStan (level 8)" \
		"typecheck  TypeScript noEmit" \
		"check      lint + analyse + test (the CI gate)" \
		"fresh      migrate:fresh --seed (local only)"

setup:
	composer install
	@test -f .env || cp .env.example .env
	php artisan key:generate
	npm ci
	npm run build
	php artisan migrate --force

dev:
	composer dev

test:
	php artisan test

lint:
	vendor/bin/pint --test
	npm run lint

analyse:
	vendor/bin/phpstan analyse --memory-limit=1G

typecheck:
	npm run typecheck

check: lint analyse test

fresh:
	php artisan migrate:fresh --seed
