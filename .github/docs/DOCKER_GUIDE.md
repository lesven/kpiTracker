# 🐳 Docker-Anleitung

Diese Anleitung erklärt die Docker-Konfiguration und -Nutzung des KPI-Tracker-Systems.

## 📋 Container-Übersicht

Das System besteht aus folgenden Docker-Containern:

### 1. **App-Container** (`symfony_app`)
- **Image**: Custom PHP 8.2-FPM Container
- **Zweck**: Symfony-Anwendung ausführen
- **Ports**: 9000 (intern)
- **Volumes**: Quellcode, Uploads, Logs

### 2. **Web-Container** (`nginx`)
- **Image**: Nginx
- **Zweck**: Webserver und Reverse Proxy
- **Ports**: 8080 (extern) → 80 (intern)
- **Abhängigkeiten**: App-Container

### 3. **Database-Container** (`db`)
- **Image**: MariaDB Latest
- **Zweck**: Datenbank-Server
- **Ports**: 3306 (intern)
- **Volumes**: Persistente Datenbank-Speicherung

## 🚀 Schnellstart mit Docker

### Projekt starten
```bash
# Alles mit einem Befehl
make install

# Oder Schritt für Schritt:
docker-compose build        # Container bauen
docker-compose up -d        # Container starten
make migrate               # Datenbank einrichten
make admin                 # Admin-Benutzer erstellen
```

### Häufige Befehle
```bash
# Container-Status anzeigen
docker-compose ps

# Logs anzeigen
make logs
# oder: docker-compose logs -f

# Container stoppen
make stop
# oder: docker-compose down

# Container neu starten
make restart
# oder: docker-compose restart

# In Container einloggen
make shell
# oder: docker-compose exec app bash
```

## 🔧 Entwicklung mit Docker

### PHP-Befehle ausführen
```bash
# Composer
docker-compose exec app composer install
docker-compose exec app composer update
docker-compose exec app composer require package/name

# Symfony Console
docker-compose exec app php bin/console cache:clear
docker-compose exec app php bin/console make:entity
docker-compose exec app php bin/console doctrine:migrations:migrate

# Tests
docker-compose exec app ./vendor/bin/phpunit
docker-compose exec app ./vendor/bin/php-cs-fixer fix
```

### Datenbank-Zugriff
```bash
# MySQL-Konsole öffnen
make db
# oder: docker-compose exec db mysql -u symfony -p kpi_tracker

# Datenbank-Backup erstellen
make backup
# oder: docker-compose exec db mysqldump -u symfony -p kpi_tracker > backup.sql

# Backup einspielen
docker-compose exec db mysql -u symfony -p kpi_tracker < backup.sql
```

### Dateien zwischen Host und Container
```bash
# Datei in Container kopieren
docker cp local-file.txt symfony_app:/var/www/html/

# Datei aus Container kopieren
docker cp symfony_app:/var/www/html/var/log/prod.log ./local-log.log

# Uploads-Ordner synchronisieren
docker-compose exec app ls -la public/uploads/
```

## 📁 Volume-Konfiguration

### Persistente Daten
```yaml
volumes:
  mariadb_data:          # Datenbank-Daten
    driver: local
```

### Bind-Mounts (Entwicklung)
```yaml
volumes:
  - .:/var/www/html                    # Quellcode
  - ./public/uploads:/var/www/html/public/uploads  # Uploads
  - ./var/log:/var/www/html/var/log    # Symfony-Logs
```

## 🔧 Container-Konfiguration

### App-Container (Dockerfile)
```dockerfile
FROM php:8.2-fpm

# PHP-Extensions installieren
RUN docker-php-ext-install pdo_mysql mbstring gd zip

# Composer installieren
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Upload-Limits konfigurieren
RUN echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini
```

### Nginx-Konfiguration
```nginx
server {
    listen 80;
    root /var/www/html/public;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/index\.php(/|$) {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

## 🏗️ Produktions-Setup

### Produktions-Container bauen
```bash
# Separate Produktions-Konfiguration
docker-compose -f docker-compose.prod.yml build

# Optimierte Builds
docker-compose -f docker-compose.prod.yml up -d

# Cache für Produktion aufwärmen
docker-compose exec app php bin/console cache:warmup --env=prod
```

### Produktions-Optimierungen
```bash
# Composer ohne Dev-Abhängigkeiten
docker-compose exec app composer install --no-dev --optimize-autoloader

# OPcache aktivieren
docker-compose exec app php -m | grep OPcache

# Asset-Optimierung
docker-compose exec app php bin/console asset:install --env=prod
```

## 📧 Cron-Jobs in Docker

### Separater Cron-Container
```bash
# Cron-Container für automatische Erinnerungen
docker-compose -f docker-compose.cron.yml up -d

# Logs prüfen
docker-compose -f docker-compose.cron.yml logs kpi-cron
```

### Host-basierte Cron-Jobs
```bash
# Crontab auf Host-System
0 9 * * * cd /path/to/kpiTracker && docker-compose exec -T app php bin/console app:send-reminders
```

## 🔍 Debugging & Monitoring

### Container-Ressourcen überwachen
```bash
# Ressourcen-Verbrauch anzeigen
docker stats

# Container-Details
docker inspect symfony_app

# Speicher-Nutzung
docker system df
```

### Logs analysieren
```bash
# Alle Container-Logs
docker-compose logs

# Nur bestimmter Container
docker-compose logs app
docker-compose logs db
docker-compose logs web

# Live-Logs verfolgen
docker-compose logs -f app
```

### Performance-Tuning
```bash
# PHP-FPM Status
docker-compose exec app curl http://localhost/status

# Nginx-Status
docker-compose exec web nginx -s reload

# MariaDB-Performance
docker-compose exec db mysql -e "SHOW PROCESSLIST;"
```

## 🚨 Troubleshooting

### Häufige Probleme

#### Container startet nicht
```bash
# Ports prüfen
netstat -tulpn | grep :8080

# Container-Status
docker-compose ps

# Logs prüfen
docker-compose logs app
```

#### Datenbank-Verbindung fehlt
```bash
# Datenbank-Container prüfen
docker-compose exec db mysql -u symfony -p

# Netzwerk-Konnektivität testen
docker-compose exec app ping db
```

#### Berechtigungsprobleme
```bash
# Dateiberechtigungen korrigieren
docker-compose exec app chown -R www:www /var/www/html
docker-compose exec app chmod -R 755 /var/www/html/public
```

#### Cache-Probleme
```bash
# Cache komplett leeren
docker-compose exec app rm -rf var/cache/*
docker-compose exec app php bin/console cache:clear

# Container komplett neu bauen
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Recovery-Befehle
```bash
# Alle Container stoppen und entfernen
docker-compose down -v

# Volumes entfernen (⚠️ Datenverlust!)
docker volume prune

# Komplett neu aufbauen
make install
```

## 🔐 Sicherheit in Docker

### Container-Sicherheit
```bash
# Updates installieren
docker-compose build --no-cache

# Vulnerability-Scan
docker scout cves symfony_app

# Rootless-Container verwenden
USER www
```

### Netzwerk-Isolation
```yaml
networks:
  kpi-network:
    driver: bridge
    internal: true  # Nur interne Kommunikation
```

### Secrets-Management
```yaml
secrets:
  db_password:
    file: ./secrets/db_password.txt
```

## 💡 Best Practices

### Entwicklung
- Verwenden Sie `make`-Befehle für konsistente Workflows
- Nutzen Sie bind-mounts für Live-Code-Reloading
- Entwickeln Sie innerhalb der Container für Konsistenz

### Produktion
- Verwenden Sie separate Container für verschiedene Services
- Implementieren Sie Health-Checks
- Nutzen Sie Multi-Stage-Builds für kleinere Images
- Konfigurieren Sie Log-Rotation

### Wartung
- Regelmäßige Container-Updates
- Monitoring der Container-Ressourcen
- Automatisierte Backups der Volumes
- Security-Scans der Images
