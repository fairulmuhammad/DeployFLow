.PHONY: help env setup up infra down logs compose-config dev dev-host migrate test lint analyse typecheck check fresh

help:
	@printf "%s\n" \
		"setup          install PHP/JS dependencies, generate key, build assets, migrate" \
		"up             build and start the full local stack (docker compose)" \
		"infra          start dependencies only (postgres, redis, s3, registry, traefik)" \
		"down           stop the local stack" \
		"logs           follow stack logs" \
		"dev            start the stack and print the app URL" \
		"dev-host       run app, queue/horizon, and Vite on the host (needs PHP extensions)" \
		"migrate        run database migrations" \
		"test           run Pest" \
		"lint           Pint --test and TypeScript check" \
		"analyse        PHPStan (level 8)" \
		"typecheck      TypeScript noEmit" \
		"check          lint + analyse + test (the CI gate)" \
		"fresh          migrate:fresh --seed (local only)"

env:
	@test -f .env || (cp .env.example .env && php artisan key:generate --ansi)

setup: env
	composer install
	npm ci
	npm run build
	php artisan storage:link --force
	php artisan migrate --force

up: env
	docker compose up -d --build
	docker compose --profile tools run --rm migrate

infra: env
	docker compose up -d postgres redis s3 s3-init registry traefik

down:
	docker compose down

logs:
	docker compose logs -f --tail=100

compose-config:
	docker compose config -q

dev: up
	@printf "\nDeployFlow is running at http://localhost:%s\n" "$${APP_PORT:-8000}"

dev-host: env
	composer dev

migrate:
	php artisan migrate --force

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
