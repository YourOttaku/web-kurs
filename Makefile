.PHONY: help init up down restart logs ps artisan test pint migrate seed fresh node-shell composer-install

help: ## Показати список доступних команд
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

init: ## Повна ініціалізація проєкту (копіювання .env, запуск docker, composer, ключ, міграції)
	@test -f .env || cp .env.example .env
	docker compose up -d
	docker compose exec php composer install
	docker compose exec php php artisan key:generate
	docker compose exec php php artisan migrate --seed
	@echo "\nПроєкт готовий до роботи:"
	@echo "Додаток:   http://localhost:8080"
	@echo "Vite:      http://localhost:5173"
	@echo "Adminer:   http://localhost:8084/?pgsql=postgres"
	@echo "Mailpit:   http://localhost:8025"
	@echo "Reverb WS: http://localhost:8090"

up: ## Запустити всі контейнери
	docker compose up -d

down: ## Зупинити всі контейнери
	docker compose down

restart: ## Перезапустити контейнери
	docker compose restart

logs: ## Переглянути логи контейнерів
	docker compose logs -f

ps: ## Статус контейнерів
	docker compose ps

artisan: ## Виконати команду artisan (напр. make artisan cmd="route:list")
	docker compose exec php php artisan $(cmd)

composer-install: ## Встановити PHP залежності
	docker compose exec php composer install

migrate: ## Запустити міграції бази даних
	docker compose exec php php artisan migrate

fresh: ## Перестворити базу даних з сідами
	docker compose exec php php artisan migrate:fresh --seed

test: ## Запустити тести PHPUnit
	docker compose exec php ./vendor/bin/phpunit

pint: ## Запустити лінтер коду Laravel Pint
	docker compose exec php ./vendor/bin/pint

node-shell: ## Зайти в консоль контейнера node
	docker compose exec node sh
