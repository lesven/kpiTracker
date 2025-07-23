# 📊 KPI-Tracker

Ein modernes KPI-Erfassungssystem basierend auf Symfony 7 (LTS), MariaDB und Docker.

## 🚀 Features

### Benutzerverwaltung
- Login/Logout mit E-Mail und Passwort
- Rollenbasierte Zugriffskontrolle (Admin/User)
- Sichere Passwort-Hashes (Argon2)
- DSGVO-konforme Datenlöschung

### KPI-Management
- KPI-Erstellung mit verschiedenen Intervallen (wöchentlich, monatlich, quartalsweise)
- KPI-Werte mit Kommentaren und Datei-Upload
- Dashboard mit Ampellogik (grün/gelb/rot)
- Automatische E-Mail-Erinnerungen

### Export & Reporting
- CSV-Export für Benutzer (eigene Daten)
- CSV-Export für Administratoren (alle Daten)
- Responsive UI mit Bootstrap 5

### Sicherheit & Qualität
- XSS/CSRF/SQL-Injection-Schutz
- Unit-Tests (>70% Abdeckung)
- PSR-12 Coding Standards
- Clean Code Prinzipien

## 📋 Voraussetzungen

- Docker & Docker Compose
- Git
- (Optional) PHP 8.2+ und Composer für lokale Entwicklung

## ⚡ Schnellstart

### 1. Repository klonen
```bash
git clone https://github.com/lesven/kpiTracker.git
cd kpiTracker
```

### 2. Umgebungsvariablen konfigurieren
```bash
cp .env.example .env
# .env nach Bedarf anpassen (Datenbank-Passwörter, E-Mail-Konfiguration)
```

### 3. Komplettes Setup mit einem Befehl
```bash
# Alles automatisch installieren und einrichten
make install

# Oder einzeln:
make build    # Container bauen
make start    # Container starten
make migrate  # Datenbank einrichten
```

### 4. Anwendung installieren und einrichten
```bash
# Alle Abhängigkeiten installieren und Datenbank einrichten
make install

# Oder manuell:
docker compose exec app composer install
docker compose exec app php bin/console doctrine:database:create --if-not-exists
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Ersten Admin-Benutzer erstellen
docker compose exec app php bin/console app:create-user admin@example.com --admin
```

### 5. Anwendung aufrufen
Die Anwendung ist unter `http://localhost:8080` erreichbar.

#### 📧 E-Mail-Testing mit MailHog
MailHog ist für lokales E-Mail-Testing integriert:
- **Web-Interface**: `http://localhost:8025` - Hier werden alle gesendeten E-Mails angezeigt
- **SMTP-Server**: `localhost:1025` - Automatisch in der Anwendung konfiguriert
- Alle E-Mails (Erinnerungen, Registrierung, etc.) werden von MailHog abgefangen

## 🛠️ Lokale Entwicklung

### Entwicklung mit Docker
```bash
# Container starten
make start
# oder: docker compose up -d

# PHP-Abhängigkeiten installieren
docker compose exec app composer install

# Container-Shell öffnen
make shell
# oder: docker compose exec app bash

# Lokale Entwicklung ohne Docker (optional)
composer install
symfony server:start
```

### Tests ausführen
```bash
# Unit-Tests
make test
# oder: docker compose exec app ./vendor/bin/phpunit

# Code-Coverage
make coverage
# oder: docker compose exec app ./vendor/bin/phpunit --coverage-html coverage/

# Code-Style prüfen
make lint
# oder: docker compose exec app ./vendor/bin/php-cs-fixer fix --dry-run

# Code-Style automatisch korrigieren
make fix
# oder: docker compose exec app ./vendor/bin/php-cs-fixer fix
```

### Erweiterte System-Validierung
```bash
# Symfony-System nach Updates validieren
docker compose exec app php bin/console doctrine:schema:validate
docker compose exec app php bin/console debug:router
docker compose exec app php bin/console debug:container
docker compose exec app php bin/console lint:container

# Sicherheitsprüfungen
docker compose exec app composer audit
docker compose exec app php bin/console security:check

# Performance-Tests
docker compose exec app php bin/console cache:warmup --env=prod
docker compose exec app php bin/console debug:config framework

# Funktionale Tests kritischer Features
docker compose exec app ./vendor/bin/phpunit tests/Functional/ -v
```

### Test-Automation Script
```bash
# Vollständige Test-Suite ausführen
./scripts/run-full-tests.sh

# Oder manuell:
docker compose exec app ./vendor/bin/phpunit --testsuite=unit
docker compose exec app ./vendor/bin/phpunit --testsuite=integration
docker compose exec app ./vendor/bin/phpunit --testsuite=functional
```

### Datenbank-Management
```bash
# Migration erstellen
docker compose exec app php bin/console make:migration

# Migration ausführen
make migrate
# oder: docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Fixtures laden (Testdaten)
make seed
# oder: docker compose exec app php bin/console doctrine:fixtures:load --no-interaction

# MySQL-Konsole öffnen
make db
# oder: docker compose exec db mysql -u symfony -p kpi_tracker
```

## 📁 Projektstruktur

```
├── src/
│   ├── Controller/          # HTTP-Controller
│   ├── Entity/             # Doctrine-Entities
│   ├── Repository/         # Datenbank-Repository
│   ├── Service/            # Business Logic
│   ├── Form/               # Symfony-Forms
│   ├── Command/            # Console-Commands
│   └── Security/           # Authentifizierung & Voters
├── templates/              # Twig-Templates
│   ├── admin/              # Admin-Interface
│   ├── dashboard/          # Dashboard-Views
│   ├── emails/             # E-Mail-Templates
│   ├── kpi/                # KPI-Management
│   └── security/           # Login/Logout
├── tests/                  # Unit- und Integrationstests
├── migrations/             # Datenbank-Migrationen
├── public/                 # Webroot (CSS, JS, Uploads)
├── config/                 # Symfony-Konfiguration
├── docker-compose.yml      # Docker-Entwicklungsumgebung
├── Dockerfile              # Container-Definition
├── Makefile               # Entwicklungshelfer
└── .github/
    ├── workflows/          # GitHub Actions CI/CD
    └── docs/               # Projekt-Dokumentation
```

## 🗄️ Datenbank-Schema

### User
- id, email, password, roles, created_at

### KPI
- id, name, interval, user_id, created_at

### KPIValue
- id, kpi_id, value, period, comment, created_at

### KPIFile
- id, kpi_value_id, filename, original_name, created_at

## 🔧 Konfiguration

### Umgebungsvariablen (.env)
```bash
APP_ENV=dev|prod
APP_SECRET=your-secret-key
DATABASE_URL=mysql://user:pass@host:port/dbname
MAILER_DSN=smtp://user:pass@host:port
```

### E-Mail-Konfiguration (Erinnerungen)
```bash
# SMTP-Konfiguration für E-Mail-Erinnerungen
MAILER_DSN=smtp://username:password@smtp.example.com:587
```

## 📧 Automatische Erinnerungen

Das System sendet automatische E-Mail-Erinnerungen:
- 3 Tage vor Fälligkeit
- 7 Tage nach Fälligkeit
- 14 Tage nach Fälligkeit
- Eskalation an Admin nach 21 Tagen

Erinnerungen werden über Cron-Jobs versendet:
```bash
# Crontab-Eintrag für tägliche Erinnerungen (auf dem Host-System)
0 9 * * * cd /path/to/kpiTracker && docker compose exec app php bin/console app:send-reminders

# Manuell Erinnerungen senden
docker compose exec app php bin/console app:send-reminders

# Test-Erinnerung senden
docker compose exec app php bin/console app:send-test-email admin@example.com
```

## 👥 Benutzerrollen

### Administrator
- Benutzer anlegen/bearbeiten/löschen
- KPIs für alle Benutzer verwalten
- Globaler CSV-Export
- System-Übersicht

### Benutzer
- Eigene KPIs verwalten
- KPI-Werte erfassen
- Eigene Daten exportieren
- Passwort ändern

## 🚀 Deployment

### Produktion
1. `.env` für Produktionsumgebung anpassen
2. `APP_ENV=prod` setzen
3. Produktions-Container bauen: `make prod-build`
4. Cache aufwärmen: `docker compose exec app php bin/console cache:warmup --env=prod`
5. Container für Produktion starten: `make prod-deploy`

### CI/CD (GitHub Actions)
Automatische Tests und Deployment sind in `.github/workflows/` konfiguriert.

## 📚 Dokumentation

- [🐳 Docker-Anleitung](.github/docs/DOCKER_GUIDE.md)
- [🏗️ Architektur-Übersicht](.github/docs/ARCHITEKTUR.md)
- [👨‍💼 Admin-Anleitung](.github/docs/ADMIN_GUIDE.md)
- [👤 Benutzer-Anleitung](.github/docs/USER_GUIDE.md)
- [🔧 Symfony 7.1 Testing Guide](.github/docs/SYMFONY_71_TESTING.md)
- [🚀 CI/CD Pipeline Dokumentation](.github/docs/CI_CD_PIPELINE.md)

## 🚀 Entwicklung

### Mit Docker (empfohlen)
```bash
# Komplettes Setup
make install

# Tests ausführen
make test

# Code-Style prüfen
make lint

# Container-Shell öffnen
make shell
```

### Makefile-Befehle
```bash
make help      # Alle verfügbaren Befehle anzeigen
make start     # Container starten
make stop      # Container stoppen
make restart   # Container neu starten
make logs      # Container-Logs anzeigen
make clean     # Cache und Logs leeren
make backup    # Datenbank-Backup erstellen
```

## 🤝 Beitragen

1. Fork das Repository
2. Feature-Branch erstellen: `git checkout -b feature/neue-funktion`
3. Änderungen committen: `git commit -am 'Neue Funktion hinzugefügt'`
4. Branch pushen: `git push origin feature/neue-funktion`
5. Pull Request erstellen

## 📄 Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert.

## 🆘 Support

Bei Fragen oder Problemen erstelle bitte ein [Issue](https://github.com/lesven/kpiTracker/issues).