# 🔧 Technische Dokumentation: Symfony 7.1 Update & Testing

## 📋 Symfony 7.1 Update Prozess

### Voraussetzungen prüfen
```bash
# PHP Version prüfen (mindestens 8.2)
docker compose exec app php --version

# Aktuelle Symfony Version prüfen
docker compose exec app php bin/console --version

# Composer Dependencies prüfen
docker compose exec app composer outdated
```

### Update-Schritte

1. **Backup erstellen**
   ```bash
   # Datenbank-Backup
   docker compose exec db mysqldump -u symfony -p kpi_tracker > backup_$(date +%Y%m%d_%H%M%S).sql
   
   # Code-Backup (Git)
   git checkout -b backup/pre-symfony-7.1
   git commit -am "Backup before Symfony 7.1 update"
   ```

2. **Dependencies aktualisieren**
   ```bash
   # Symfony Packages auf 7.1 aktualisieren
   docker compose exec app composer require symfony/framework-bundle:^7.1
   docker compose exec app composer update
   ```

3. **Cache leeren**
   ```bash
   docker compose exec app php bin/console cache:clear --env=dev
   docker compose exec app php bin/console cache:clear --env=prod
   ```

## 🧪 Erweiterte Test-Suite

### 1. System-Validierung (Post-Update)

#### Container-Konfiguration prüfen
```bash
# Doctrine Schema-Validierung
docker compose exec app php bin/console doctrine:schema:validate

# Container Service-Validation
docker compose exec app php bin/console debug:container

# Router-Konfiguration prüfen
docker compose exec app php bin/console debug:router

# Security-Konfiguration validieren
docker compose exec app php bin/console debug:firewall

# Event Dispatcher prüfen
docker compose exec app php bin/console debug:event-dispatcher
```

#### Datenbank-Integrität
```bash
# Migration-Status prüfen
docker compose exec app php bin/console doctrine:migrations:status

# Doctrine Mapping validieren
docker compose exec app php bin/console doctrine:mapping:info

# Datenbank-Schema vergleichen
docker compose exec app php bin/console doctrine:schema:update --dump-sql
```

### 2. Funktionale Tests

#### Authentifizierung & Autorisierung
```bash
# Test-User erstellen
docker compose exec app php bin/console app:create-user test@example.com 1234567890123456 --first-name="Test"

# Admin-User erstellen
docker compose exec app php bin/console app:create-admin admin-test@example.com password123

# Login-Funktionalität testen
curl -X POST http://localhost:8080/login \
  -d "email=test@example.com&password=1234567890123456" \
  -H "Content-Type: application/x-www-form-urlencoded"
```

#### KPI CRUD Operationen
```bash
# PHPUnit Tests für KPI-Management
docker compose exec app ./vendor/bin/phpunit tests/Controller/KPIControllerTest.php -v

# Service Layer Tests
docker compose exec app ./vendor/bin/phpunit tests/Service/KPIServiceTest.php -v

# Repository Tests
docker compose exec app ./vendor/bin/phpunit tests/Repository/KPIRepositoryTest.php -v
```

#### File Upload Funktionalität
```bash
# Upload-Directory Permissions prüfen
docker compose exec app ls -la public/uploads/

# File Upload Service testen
docker compose exec app ./vendor/bin/phpunit tests/Service/FileUploadServiceTest.php -v

# Upload-Limits prüfen
docker compose exec app php -i | grep upload
```

#### Email-Versand
```bash
# Mailer-Konfiguration testen
docker compose exec app php bin/console debug:config framework mailer

# Test-Email senden
docker compose exec app php bin/console app:send-test-email admin@example.com

# Email-Templates validieren
docker compose exec app php bin/console lint:twig templates/emails/
```

### 3. Performance & Security Tests

#### Performance-Benchmarks
```bash
# Symfony Profiler aktivieren (dev environment)
docker compose exec app php bin/console debug:config framework profiler

# Cache-Performance prüfen
docker compose exec app php bin/console cache:pool:list
docker compose exec app php bin/console cache:pool:clear doctrine.result_cache

# Database Query Performance
docker compose exec app php bin/console doctrine:query:dql "SELECT u FROM App\Entity\User u" --show-sql
```

#### Security-Validierung
```bash
# Security Checker für Dependencies
docker compose exec app composer audit

# Symfony Security Check
docker compose exec app php bin/console security:check

# CSRF Token Validation testen
curl -X GET http://localhost:8080/login -c cookies.txt
csrf_token=$(grep csrf cookies.txt | awk '{print $7}')
echo "CSRF Token: $csrf_token"

# XSS Protection testen
docker compose exec app ./vendor/bin/phpunit tests/Security/XSSProtectionTest.php
```

### 4. Regressions-Tests

#### Critical User Journeys
```bash
# 1. User Registration & Login Flow
docker compose exec app ./vendor/bin/phpunit tests/Functional/UserJourneyTest.php::testUserRegistrationAndLogin

# 2. KPI Creation & Value Entry
docker compose exec app ./vendor/bin/phpunit tests/Functional/UserJourneyTest.php::testKPICreationAndValueEntry

# 3. Admin Dashboard Access
docker compose exec app ./vendor/bin/phpunit tests/Functional/UserJourneyTest.php::testAdminDashboardAccess

# 4. CSV Export Functionality
docker compose exec app ./vendor/bin/phpunit tests/Functional/UserJourneyTest.php::testCSVExport
```

#### Data Integrity Tests
```bash
# User-KPI Ownership Tests
docker compose exec app ./vendor/bin/phpunit tests/Security/VoterTest.php::testKPIVoter

# GDPR Compliance Tests
docker compose exec app ./vendor/bin/phpunit tests/Service/UserServiceTest.php::testDeleteUserWithData

# File Upload Security Tests
docker compose exec app ./vendor/bin/phpunit tests/Security/FileUploadSecurityTest.php
```

## 📊 Test-Automatisierung

### GitHub Actions CI/CD Pipeline

Die CI/CD Pipeline wurde erweitert um:

#### **Haupt-Pipeline (ci.yml)**
```yaml
# Läuft bei jedem Push und Pull Request
jobs:
  tests:
    - System-Validierung (Schema, Container, Templates, YAML)
    - PHPUnit Tests mit Coverage
    - Code-Style-Prüfung
  
  security:
    - Composer Security Audit
    - Symfony Security Check
    - PHP Konfigurationsprüfung
  
  performance:
    - Cache Warmup Performance
    - Database Query Performance  
    - Memory Usage Tests
```

#### **Erweiterte Pipeline (extended-testing.yml)**
```yaml
# Läuft bei Pull Requests und nächtlich
jobs:
  integration-tests:
    - Unit/Integration/Functional Tests mit separater Coverage
    - Erweiterte System-Validierung
    - User Journey Simulation
    
  security-scan:
    - Umfassende Sicherheitsprüfungen
    - Code Quality Analysis
    - File Permissions Check
    
  load-testing:
    - Memory Load Tests
    - Database Stress Tests
    - Performance Metriken
```

### Lokale Test-Integration
```bash
# Test-Script erstellen
cat > scripts/run-full-tests.sh << 'EOF'
#!/bin/bash
set -e

echo "🧪 Starting Full Test Suite..."

# Unit Tests
echo "📋 Running Unit Tests..."
./vendor/bin/phpunit --testsuite=unit --coverage-text --colors=always

# Integration Tests
echo "🔗 Running Integration Tests..."
./vendor/bin/phpunit --testsuite=integration --colors=always

# Functional Tests
echo "🌐 Running Functional Tests..."
./vendor/bin/phpunit --testsuite=functional --colors=always

# Code Quality
echo "✨ Running Code Quality Checks..."
./vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes

# Security Checks
echo "🔒 Running Security Checks..."
composer audit
php bin/console security:check

# Symfony Validation
echo "⚙️ Running Symfony Validation..."
php bin/console lint:container
php bin/console doctrine:schema:validate

echo "✅ All tests completed successfully!"
EOF

chmod +x scripts/run-full-tests.sh
```

## 🚨 Troubleshooting Guide

### Häufige Probleme nach Symfony 7.1 Update

#### 1. Deprecation Warnings
```bash
# Deprecations anzeigen
docker compose exec app php bin/console debug:container --deprecations

# Deprecation-Log aktivieren
# config/packages/monolog.yaml
channels: [deprecation]
handlers:
    deprecation:
        type: stream
        path: "%kernel.logs_dir%/deprecation.log"
        level: info
        channels: [deprecation]
```

#### 2. Cache-Probleme
```bash
# Cache komplett neu generieren
docker compose exec app rm -rf var/cache/*
docker compose exec app php bin/console cache:warmup
docker compose exec app php bin/console cache:clear --env=prod
```

#### 3. Autoloader-Probleme
```bash
# Composer Autoloader neu generieren
docker compose exec app composer dump-autoload --optimize
docker compose exec app composer install --optimize-autoloader
```

#### 4. Service Configuration Issues
```bash
# Service-Container analysieren
docker compose exec app php bin/console debug:autowiring
docker compose exec app php bin/console debug:container --show-arguments
```

## 📈 Performance-Monitoring

### Metriken nach Update überwachen
```bash
# Response Time Monitoring
curl -w "Time: %{time_total}s\n" -o /dev/null -s http://localhost:8080/

# Memory Usage prüfen
docker compose exec app php -d memory_limit=128M bin/console cache:warmup

# Database Query Performance
docker compose exec app php bin/console doctrine:query:dql "SELECT COUNT(u) FROM App\Entity\User u" --show-sql
```

### Production-Readiness Checklist
- [ ] Alle Tests bestanden (Unit, Integration, Functional)
- [ ] Security Audit ohne kritische Findings
- [ ] Performance-Regression < 5%
- [ ] Doctrine Schema validiert
- [ ] Symfony Container validiert
- [ ] Cache Warmup erfolgreich
- [ ] Email-Versand funktional
- [ ] File-Upload funktional
- [ ] CSV-Export funktional
- [ ] Admin-Dashboard erreichbar
- [ ] GDPR-Funktionen (User Deletion) getestet

## 🔄 Rollback-Strategie

Falls Probleme auftreten:
```bash
# 1. Schneller Rollback (Docker)
git checkout main
docker compose down
docker compose up -d --build

# 2. Datenbank-Rollback (falls nötig)
docker compose exec db mysql -u symfony -p kpi_tracker < backup_YYYYMMDD_HHMMSS.sql

# 3. Dependencies Rollback
git checkout composer.json composer.lock
docker compose exec app composer install
```
