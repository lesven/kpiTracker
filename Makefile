# Makefile für KPI-Tracker
# Vereinfacht die häufigsten Entwicklungsaufgaben

.PHONY: help install start stop restart build test coverage coverage-table coverage-detail coverage-summary lint fix clean migrate seed fix-permissions fresh-install

# Standard-Ziel
help: ## Zeigt diese Hilfe an
	@echo "KPI-Tracker Development Commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

deploy:
	git reset --hard HEAD 
	git pull
	make install

install: ## Installiert alle Abhängigkeiten
	docker compose build
	docker compose up -d
	@echo "Warte 10 Sekunden auf Container-Start..."
	@sleep 10
	@echo "Setze Berechtigungen vor Composer-Installation..."
	docker compose exec --user root --workdir /var/www/html app chown -R www-data:www-data . || true
	docker compose exec --user root --workdir /var/www/html app chmod -R 775 . || true
	docker compose exec --user root --workdir /var/www/html app chmod +x bin/console || true
	@echo "Installiere Composer-Abhängigkeiten..."
	docker compose exec --workdir /var/www/html app composer install --no-interaction
	@echo "Prüfe ob bin/console existiert..."
	docker compose exec --workdir /var/www/html app test -f bin/console && echo "bin/console gefunden" || echo "bin/console nicht gefunden"
	@echo "Räume Cache manuell auf..."
	docker compose exec --workdir /var/www/html app rm -rf var/cache/* || true
	@echo "Erstelle Datenbank..."
	docker compose exec --workdir /var/www/html app php bin/console doctrine:database:create --if-not-exists || echo "Fehler bei Datenbank-Erstellung"
	docker compose exec --workdir /var/www/html app php bin/console doctrine:migrations:migrate --no-interaction || echo "Fehler bei Migration"
	@echo "Installation abgeschlossen!"
	docker compose exec --workdir /var/www/html app ./vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes
	@echo "CS Fixer abgeschlossen!"
start: ## Startet die Container
	docker compose up -d

stop: ## Stoppt die Container
	docker compose down

restart: ## Neustart der Container
	docker compose restart

build: ## Baut die Container neu
	docker compose build --no-cache

test: ## Führt alle Tests aus
	docker compose exec --workdir /var/www/html app ./vendor/bin/phpunit

test-unit: ## Führt nur Unit-Tests aus
	docker compose exec --workdir /var/www/html app ./vendor/bin/phpunit --testsuite=unit

test-integration: ## Führt nur Integration-Tests aus
	docker compose exec --workdir /var/www/html app ./vendor/bin/phpunit --testsuite=integration

test-functional: ## Führt nur Functional-Tests aus
	docker compose exec --workdir /var/www/html app ./vendor/bin/phpunit --testsuite=functional

test-full: ## Führt vollständige Test-Suite aus (inkl. Validierung)
	@chmod +x scripts/run-full-tests.sh
	./scripts/run-full-tests.sh

validate: ## Validiert Symfony-System (Schema, Container, etc.)
	docker compose exec --workdir /var/www/html app php bin/console doctrine:schema:validate
	docker compose exec --workdir /var/www/html app php bin/console debug:container
	docker compose exec --workdir /var/www/html app php bin/console lint:container
	docker compose exec --workdir /var/www/html app php bin/console lint:twig templates/

security-check: ## Führt Sicherheitsprüfungen durch
	docker compose exec --workdir /var/www/html app composer audit
	docker compose exec --workdir /var/www/html app php bin/console security:check

performance-test: ## Testet Performance-Basics
	@echo "Teste Cache Warmup..."
	docker compose exec --workdir /var/www/html app php bin/console cache:warmup --env=prod
	@echo "Teste Response Time..."
	@curl -w "@scripts/curl-format.txt" -o /dev/null -s http://localhost:8080/ || echo "Anwendung nicht erreichbar auf localhost:8080"

coverage: ## Erstellt Test-Coverage-Report (HTML + detaillierte Shell-Ausgabe)
	@echo "🧪 Generiere Code Coverage Report..."
	docker compose exec --workdir /var/www/html app bash -c "XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/ --coverage-text --coverage-clover=coverage.xml"
	@echo ""
	@echo "📊 Coverage Report pro Klasse:"
	@echo "================================"
	@docker compose exec --workdir /var/www/html app php scripts/coverage-parser.php

coverage-detail: ## Zeigt detaillierte Coverage pro Datei in der Shell  
	@echo "📈 Generiere detaillierte Coverage-Analyse..."
	@docker compose exec --workdir /var/www/html app bash -c "XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-text --coverage-filter=src/ --testdox --coverage-text-show-uncovered"

coverage-table: ## Zeigt Coverage pro Klasse in schöner Tabellenform
	@echo "📊 Generiere Coverage-Tabelle..."
	@docker compose exec --workdir /var/www/html app bash -c "XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-clover=coverage.xml >/dev/null 2>&1"
	@docker compose exec --workdir /var/www/html app php scripts/coverage-parser.php

coverage-summary: ## Zeigt kompakte Coverage-Zusammenfassung
	@echo "📈 Code Coverage Zusammenfassung:"
	@echo "========================================"
	@docker compose exec --workdir /var/www/html app bash -c "XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-text --colors=never" | grep -E "(Classes|Methods|Lines):" || echo "Coverage-Daten nicht verfügbar"
	@echo "========================================"
	@docker compose exec --workdir /var/www/html app bash -c "ls -la coverage/ | grep -E '\\.html$$' | wc -l" 2>/dev/null | xargs -I {} echo "📄 {} HTML-Dateien generiert"

lint: ## Prüft Code-Style (PSR-12)
	docker compose exec --workdir /var/www/html app ./vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Korrigiert Code-Style automatisch
	docker compose exec --workdir /var/www/html app ./vendor/bin/php-cs-fixer fix --allow-risky=yes

clean: ## Räumt Cache und Logs auf
	docker compose exec --workdir /var/www/html app php bin/console cache:clear --no-warmup || true
	docker compose exec --workdir /var/www/html app rm -rf var/log/*.log || true

migrate: ## Führt Datenbank-Migrationen aus
	docker compose exec --workdir /var/www/html app php bin/console doctrine:migrations:migrate --no-interaction

seed: ## Lädt Testdaten (Fixtures)
	docker compose exec --workdir /var/www/html app php bin/console doctrine:fixtures:load --no-interaction

admin: ## Erstellt einen Admin-Benutzer (interaktiv)
	docker compose exec --workdir /var/www/html app php bin/console app:create-user --admin

shell: ## Öffnet Shell im Container
	docker compose exec --workdir /var/www/html app bash

logs: ## Zeigt Container-Logs
	docker compose logs -f

db: ## Öffnet MySQL-Konsole
	docker compose exec db mysql -u symfony -p kpi_tracker

backup: ## Erstellt Datenbank-Backup
	docker compose exec db mysqldump -u symfony -p kpi_tracker > backup_$(shell date +%Y%m%d_%H%M%S).sql

fix-permissions: ## Behebt Berechtigungsprobleme
	@echo "Setze Berechtigungen für alle Symfony-Verzeichnisse..."
	docker compose exec --user root --workdir /var/www/html app chown -R www-data:www-data . || true
	docker compose exec --user root --workdir /var/www/html app chmod -R 775 . || true
	docker compose exec --user root --workdir /var/www/html app chmod 644 .env* || true
	@echo "Berechtigungen wurden gesetzt."

# Entwicklung
dev-setup: install ## Komplettes Setup für Entwicklung
	@echo "Setup abgeschlossen! Öffne http://localhost:8080"

fresh-install: ## Komplette Neuinstallation (bei Problemen)
	@echo "Stoppe und entferne alle Container und Volumes..."
	docker compose down --volumes --remove-orphans || true
	@echo "Bereinige Docker-System..."
	docker system prune -f || true
	@echo "Baue Container komplett neu..."
	docker compose build --no-cache
	@echo "Starte Installation..."
	$(MAKE) install

# Produktion
prod-build: ## Build für Produktion
	docker compose -f docker-compose.prod.yml build

prod-deploy: ## Deployment für Produktion
	docker compose -f docker-compose.prod.yml up -d
	docker compose -f docker-compose.prod.yml exec --workdir /var/www/html app php bin/console cache:warmup --env=prod
