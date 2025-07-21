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
# .env nach Bedarf anpassen
```

### 3. Docker Container starten
```bash
docker-compose up -d
```

### 4. Symfony installieren und einrichten
```bash
# Container betreten
docker exec -it symfony_app bash

# Composer installieren
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Symfony Projekt erstellen
composer create-project symfony/skeleton .
composer require webapp

# Datenbank-Migration
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Erste Admin-User erstellen
php bin/console app:create-admin admin@example.com password123
```

### 5. Anwendung aufrufen
Die Anwendung ist unter `http://localhost:8080` erreichbar.

## 🛠️ Lokale Entwicklung

### Projekt-Setup (ohne Docker)
```bash
# PHP-Abhängigkeiten installieren
composer install

# Node.js-Abhängigkeiten (für Assets)
npm install

# Assets kompilieren
npm run build

# Lokalen Server starten
symfony server:start
```

### Tests ausführen
```bash
# Unit-Tests
./vendor/bin/phpunit

# Code-Coverage
./vendor/bin/phpunit --coverage-html coverage/

# Code-Style prüfen
./vendor/bin/php-cs-fixer fix --dry-run
```

### Datenbank-Management
```bash
# Migration erstellen
php bin/console make:migration

# Migration ausführen
php bin/console doctrine:migrations:migrate

# Fixtures laden (Testdaten)
php bin/console doctrine:fixtures:load
```

## 📁 Projektstruktur

```
├── src/
│   ├── Controller/          # HTTP-Controller
│   ├── Entity/             # Doctrine-Entities
│   ├── Repository/         # Datenbank-Repository
│   ├── Service/            # Business Logic
│   ├── Form/               # Symfony-Forms
│   └── Security/           # Authentifizierung
├── templates/              # Twig-Templates
├── tests/                  # Unit- und Integrationstests
├── migrations/             # Datenbank-Migrationen
├── public/                 # Webroot (CSS, JS, Uploads)
├── config/                 # Symfony-Konfiguration
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
# Crontab-Eintrag für tägliche Erinnerungen
0 9 * * * cd /path/to/project && php bin/console app:send-reminders
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
3. Cache aufwärmen: `php bin/console cache:warmup`
4. Assets optimieren: `npm run build`
5. Webserver auf `public/` zeigen lassen

### CI/CD (GitHub Actions)
Automatische Tests und Deployment sind in `.github/workflows/` konfiguriert.

## 📚 Dokumentation

- [Architektur-Übersicht](.github/docs/ARCHITEKTUR.md)
- [Admin-Anleitung](.github/docs/ADMIN_GUIDE.md)
- [Benutzer-Anleitung](.github/docs/USER_GUIDE.md)

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