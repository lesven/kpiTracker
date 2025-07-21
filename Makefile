# Makefile für KPI-Tracker
# Vereinfacht die häufigsten Entwicklungsaufgaben

.PHONY: help install start stop restart build test coverage lint fix clean migrate seed

# Standard-Ziel
help: ## Zeigt diese Hilfe an
	@echo "KPI-Tracker Development Commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: ## Installiert alle Abhängigkeiten
	docker-compose build
	docker-compose up -d
	docker-compose exec app composer install
	docker-compose exec app php bin/console doctrine:database:create --if-not-exists
	docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

start: ## Startet die Container
	docker-compose up -d

stop: ## Stoppt die Container
	docker-compose down

restart: ## Neustart der Container
	docker-compose restart

build: ## Baut die Container neu
	docker-compose build --no-cache

test: ## Führt alle Tests aus
	docker-compose exec app ./vendor/bin/phpunit

coverage: ## Erstellt Test-Coverage-Report
	docker-compose exec app ./vendor/bin/phpunit --coverage-html coverage/

lint: ## Prüft Code-Style (PSR-12)
	docker-compose exec app ./vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Korrigiert Code-Style automatisch
	docker-compose exec app ./vendor/bin/php-cs-fixer fix

clean: ## Räumt Cache und Logs auf
	docker-compose exec app php bin/console cache:clear
	docker-compose exec app rm -rf var/log/*.log

migrate: ## Führt Datenbank-Migrationen aus
	docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

seed: ## Lädt Testdaten (Fixtures)
	docker-compose exec app php bin/console doctrine:fixtures:load --no-interaction

admin: ## Erstellt einen Admin-Benutzer (interaktiv)
	docker-compose exec app php bin/console app:create-admin

shell: ## Öffnet Shell im Container
	docker-compose exec app bash

logs: ## Zeigt Container-Logs
	docker-compose logs -f

db: ## Öffnet MySQL-Konsole
	docker-compose exec db mysql -u symfony -p kpi_tracker

backup: ## Erstellt Datenbank-Backup
	docker-compose exec db mysqldump -u symfony -p kpi_tracker > backup_$(shell date +%Y%m%d_%H%M%S).sql

# Entwicklung
dev-setup: install ## Komplettes Setup für Entwicklung
	@echo "Setup abgeschlossen! Öffne http://localhost:8080"

# Produktion
prod-build: ## Build für Produktion
	docker-compose -f docker-compose.prod.yml build

prod-deploy: ## Deployment für Produktion
	docker-compose -f docker-compose.prod.yml up -d
	docker-compose -f docker-compose.prod.yml exec app php bin/console cache:warmup --env=prod
