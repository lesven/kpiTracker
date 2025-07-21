# 👨‍💼 Administrator-Anleitung

Diese Anleitung richtet sich an Systemadministratoren, die das KPI-Tracker-System verwalten und konfigurieren.

## 🚀 Erste Schritte

### System-Setup
1. **Docker installieren**: Docker und Docker Compose auf dem Server installieren
2. **Repository klonen**: `git clone https://github.com/lesven/kpiTracker.git`
3. **Umgebung konfigurieren**: `.env`-Datei anpassen
4. **System starten**: `make install` oder `docker-compose up -d`

### Ersten Admin-Benutzer erstellen
```bash
# Interaktiv über Container
docker-compose exec app php bin/console app:create-admin

# Oder über Makefile
make admin

# Mit Parametern (nicht-interaktiv)
docker-compose exec app php bin/console app:create-admin admin@example.com --password=secure123 --firstname=Admin --lastname=User
```

## 👥 Benutzerverwaltung

### Neue Benutzer anlegen
1. **Login** als Administrator
2. **Admin-Bereich** aufrufen (`/admin`)
3. **"Benutzer hinzufügen"** klicken
4. **Daten eingeben**:
   - E-Mail-Adresse (eindeutig)
   - Temporäres Passwort
   - Rolle auswählen (Benutzer/Administrator)
5. **Speichern**

### Benutzerrollen
- **ROLE_USER**: Standard-Benutzer mit eingeschränkten Rechten
  - Eigene KPIs verwalten
  - KPI-Werte erfassen
  - Eigene Daten exportieren
  - Passwort ändern

- **ROLE_ADMIN**: Administrator mit erweiterten Rechten
  - Alle Benutzer verwalten
  - KPIs für andere Benutzer anlegen
  - Globaler Datenexport
  - System-Übersicht

### Benutzer bearbeiten/löschen
- **Bearbeiten**: E-Mail, Rolle ändern, Passwort zurücksetzen
- **Löschen**: ⚠️ **DSGVO-konforme Löschung** - alle zugehörigen Daten werden unwiderruflich gelöscht

## 📊 KPI-Management

### KPIs für Benutzer anlegen
1. **KPI-Verwaltung** im Admin-Bereich
2. **"KPI hinzufügen"** für gewünschten Benutzer
3. **KPI-Details** eingeben:
   - Name (aussagekräftig)
   - Intervall (wöchentlich/monatlich/quartalsweise)
   - Beschreibung (optional)
4. **Speichern**

### KPI-Intervalle verstehen
- **Wöchentlich**: Eintragung jeden Montag fällig
- **Monatlich**: Eintragung am 1. des Monats fällig
- **Quartalsweise**: Eintragung am 1.1., 1.4., 1.7., 1.10. fällig

### KPI-Status überwachen
- **Grün**: Wert für aktuellen Zeitraum erfasst
- **Gelb**: Fällig in den nächsten 3 Tagen
- **Rot**: Überfällig (keine Eintragung vorhanden)

## 📧 E-Mail-Erinnerungen

### Automatische Erinnerungen
Das System sendet automatisch E-Mails:
- **3 Tage vor Fälligkeit**: Höfliche Erinnerung
- **7 Tage nach Fälligkeit**: Dringliche Erinnerung
- **14 Tage nach Fälligkeit**: Letzte Erinnerung
- **21 Tage nach Fälligkeit**: **Eskalation an alle Administratoren**

### E-Mail-Konfiguration
```bash
# .env-Datei anpassen
MAILER_DSN=smtp://user:password@smtp.example.com:587

# Test-E-Mail senden
docker-compose exec app php bin/console app:send-test-email admin@example.com
```

### Manuellen Erinnerungslauf starten
```bash
# Alle fälligen Erinnerungen senden
docker-compose exec app php bin/console app:send-reminders

# Nur für bestimmten Benutzer
docker-compose exec app php bin/console app:send-reminders --user=user@example.com

# Debug-Modus (zeigt alle Erinnerungen an, sendet aber keine E-Mails)
docker-compose exec app php bin/console app:send-reminders --dry-run
```

### Cron-Job für automatische Erinnerungen
```bash
# Crontab-Eintrag für tägliche Ausführung um 9:00 Uhr (auf Host-System)
0 9 * * * cd /path/to/kpiTracker && docker-compose exec -T app php bin/console app:send-reminders

# Für Docker Swarm oder Kubernetes (als Service)
# Siehe docker-compose.cron.yml für einen separaten Cron-Container
```

## 📈 Datenexport & Reporting

### CSV-Export für alle Benutzer
1. **Admin-Dashboard** aufrufen
2. **"Vollständiger CSV-Export"** klicken
3. **Datei herunterladen** (enthält alle Daten aller Benutzer)

### Export-Format
```csv
Benutzer,KPI-Name,Intervall,Zeitraum,Wert,Kommentar,Erstellt am
user@example.com,Umsatz,monatlich,2024-01,50000,Guter Monat,2024-01-31 14:30:00
user@example.com,Umsatz,monatlich,2024-02,48000,,2024-02-29 09:15:00
```

### Automatische Berichte
```bash
# Monatlichen Bericht generieren
docker-compose exec app php bin/console app:generate-report monthly

# Quartalsberichte
docker-compose exec app php bin/console app:generate-report quarterly

# Berichte für bestimmten Zeitraum
docker-compose exec app php bin/console app:generate-report monthly --from=2024-01 --to=2024-12
```

## 🔧 System-Wartung

### Datenbank-Backup
```bash
# Manuelles Backup
make backup

# Automatisches tägliches Backup (Cron)
0 2 * * * cd /path/to/kpiTracker && make backup
```

### Log-Überwachung
```bash
# Container-Logs anzeigen
make logs

# Symfony-Logs prüfen
docker-compose exec app tail -f var/log/prod.log

# Nginx-Logs
docker-compose exec web tail -f /var/log/nginx/access.log
```

### System-Updates
```bash
# Code aktualisieren
git pull origin main

# Container neu bauen und starten
make build
make restart

# Abhängigkeiten aktualisieren
docker-compose exec app composer install --no-dev --optimize-autoloader

# Datenbank-Migrationen
make migrate

# Cache leeren und neu aufbauen
make clean
docker-compose exec app php bin/console cache:warmup --env=prod
```

### Performance-Monitoring
```bash
# Datenbankgrößen prüfen
docker-compose exec db mysql -e "
  SELECT 
    table_schema as 'Database',
    sum(data_length + index_length) / 1024 / 1024 as 'Size (MB)'
  FROM information_schema.tables 
  GROUP BY table_schema;"

# Aktive Sessions
docker-compose exec app php bin/console debug:autowiring
```

## 🔒 Sicherheit & Compliance

### DSGVO-Compliance
- **Datenminimierung**: Nur notwendige Daten sammeln
- **Löschrecht**: Benutzer und alle Daten vollständig löschbar
- **Datenportabilität**: CSV-Export für Benutzer verfügbar
- **Zweckbindung**: Daten nur für KPI-Tracking verwenden

### Benutzer-Löschung (DSGVO-konform)
1. **Warnung**: Alle Daten werden unwiderruflich gelöscht
2. **Bestätigung**: Admin muss Löschung explizit bestätigen
3. **Ausführung**: Benutzer + KPIs + Werte + Dateien werden gelöscht
4. **Protokoll**: Löschung wird geloggt (ohne personenbezogene Daten)

### Passwort-Richtlinien
- **Mindestlänge**: 8 Zeichen
- **Komplexität**: Buchstaben, Zahlen, Sonderzeichen empfohlen
- **Hashing**: Argon2 (sicher und modern)
- **Rotation**: Empfehlung alle 90 Tage

### Security-Updates
```bash
# Abhängigkeiten auf Sicherheitslücken prüfen
docker-compose exec app composer audit

# Symfony Security-Check
docker-compose exec app composer require --dev symfony/security-checker
docker-compose exec app ./vendor/bin/security-checker security:check

# Container-Updates (neu bauen)
docker-compose build --no-cache
docker-compose up -d

# System-Updates im Container installieren
docker-compose exec app apt update && apt upgrade -y
```

## 📊 System-Statistiken

### Dashboard-Übersicht
- **Gesamtanzahl Benutzer**
- **Aktive KPIs**
- **Erfasste Werte (letzte 30 Tage)**
- **Upload-Statistiken**
- **System-Status**

### Erweiterte Auswertungen
```bash
# KPI-Aktivität der letzten 30 Tage
docker-compose exec app php bin/console app:stats --period=30days

# Benutzer-Engagement-Report
docker-compose exec app php bin/console app:user-engagement

# Storage-Nutzung
docker-compose exec app php bin/console app:storage-stats
```

## 🆘 Troubleshooting

### Häufige Probleme

#### Container starten nicht
```bash
# Logs prüfen
docker-compose logs

# Ports prüfen
netstat -tulpn | grep :8080

# Container neu starten
docker-compose down && docker-compose up -d
```

#### Datenbank-Verbindungsfehler
```bash
# Datenbank-Status prüfen
docker-compose exec db mysqladmin ping -u root -p

# Verbindung testen
docker-compose exec app php bin/console doctrine:query:sql "SELECT 1"
```

#### E-Mail-Versand funktioniert nicht
```bash
# SMTP-Konfiguration testen
docker-compose exec app php bin/console app:test-mailer

# Logs prüfen
docker-compose exec app grep -i mail var/log/prod.log
```

#### Dateien können nicht hochgeladen werden
```bash
# Berechtigungen prüfen
docker-compose exec app ls -la public/uploads/

# Speicherplatz prüfen
docker-compose exec app df -h

# PHP-Limits prüfen
docker-compose exec app php -i | grep upload_max_filesize
```

### Support-Kontakt
Bei weiteren Problemen:
1. **Logs sammeln**: Relevante Fehlermeldungen dokumentieren
2. **Issue erstellen**: GitHub Repository Issues verwenden
3. **System-Info**: PHP-Version, Docker-Version, Betriebssystem angeben

## 🔄 Wartungsfenster

### Geplante Wartung
1. **Ankündigung**: Benutzer 24h vorher informieren
2. **Backup**: Vollständiges System-Backup erstellen
3. **Updates**: System- und Sicherheitsupdates installieren
4. **Tests**: Funktionalität nach Update prüfen
5. **Freigabe**: System wieder für Benutzer freigeben

### Rollback-Prozedure
```bash
# Bei Problemen: Rollback auf vorherige Version
git checkout <previous-commit>
docker-compose build
docker-compose up -d

# Datenbank-Rollback (falls nötig)
mysql kpi_tracker < backup_YYYYMMDD_HHMMSS.sql
```
