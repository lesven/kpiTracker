# 🏗️ Architektur-Übersicht

Die KPI-Tracker-Anwendung basiert auf moderner Web-Architektur mit Symfony als Backend-Framework und Bootstrap für das responsive Frontend.

## 🎯 Technologie-Stack

### Backend
- **Symfony 7 (LTS)**: PHP-Framework für robuste Web-Anwendungen
- **PHP 8.2+**: Aktuelle PHP-Version mit modernen Features
- **Doctrine ORM**: Object-Relational Mapping für Datenbankzugriff
- **Symfony Security**: Authentifizierung und Autorisierung

### Datenbank
- **MariaDB**: Relationale Datenbank (MySQL-kompatibel)
- **Doctrine Migrations**: Versionierung der Datenbankstruktur

### Frontend
- **Twig**: Template-Engine für HTML-Rendering
- **Bootstrap 5**: CSS-Framework für responsive Design
- **Webpack Encore**: Asset-Management und -Kompilierung

### DevOps & Qualität
- **Docker**: Containerisierung für einheitliche Entwicklungsumgebung
- **PHPUnit**: Unit- und Integrationstests
- **PHP CS Fixer**: Code-Style-Überprüfung (PSR-12)
- **GitHub Actions**: CI/CD-Pipeline

## 📊 Datenbank-Schema

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│    User     │──────<│     KPI     │──────<│  KPIValue   │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id (PK)     │       │ id (PK)     │       │ id (PK)     │
│ email       │       │ name        │       │ value       │
│ password    │       │ interval    │       │ period      │
│ roles       │       │ description │       │ comment     │
│ created_at  │       │ user_id(FK) │       │ kpi_id(FK)  │
└─────────────┘       │ created_at  │       │ created_at  │
                      └─────────────┘       │ updated_at  │
                                            └─────────────┘
                                                    │
                                                    │ 1:n
                                                    ▼
                                            ┌─────────────┐
                                            │   KPIFile   │
                                            ├─────────────┤
                                            │ id (PK)     │
                                            │ filename    │
                                            │ original_name│
                                            │ mime_type   │
                                            │ file_size   │
                                            │ kpi_value_id│
                                            │ created_at  │
                                            └─────────────┘
```

### Entitäts-Beziehungen
- **User** 1:n **KPI**: Ein Benutzer kann mehrere KPIs haben
- **KPI** 1:n **KPIValue**: Eine KPI kann mehrere Werte haben
- **KPIValue** 1:n **KPIFile**: Ein Wert kann mehrere Dateien haben

## 🔧 Anwendungsarchitektur

### Model-View-Controller (MVC)
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Controller    │────│     Service     │────│   Repository    │
│  (HTTP Logic)   │    │ (Business Logic)│    │ (Data Access)   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│     Twig        │    │     Entity      │    │    Database     │
│  (Templates)    │    │   (Domain)      │    │   (MariaDB)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Verzeichnisstruktur
```
src/
├── Controller/          # HTTP-Request-Handler
│   ├── DashboardController.php
│   ├── KPIController.php
│   ├── SecurityController.php
│   └── AdminController.php
├── Entity/             # Doctrine-Entities (Domain Model)
│   ├── User.php
│   ├── KPI.php
│   ├── KPIValue.php
│   └── KPIFile.php
├── Repository/         # Datenbank-Zugriff
│   ├── UserRepository.php
│   ├── KPIRepository.php
│   ├── KPIValueRepository.php
│   └── KPIFileRepository.php
├── Service/            # Business Logic
│   ├── KPIService.php
│   ├── ReminderService.php
│   ├── ExportService.php
│   └── FileUploadService.php
├── Form/              # Symfony-Forms
│   ├── UserType.php
│   ├── KPIType.php
│   └── KPIValueType.php
└── Security/          # Authentifizierung
    ├── LoginFormAuthenticator.php
    └── UserChecker.php
```

## 🔒 Sicherheitskonzept

### Authentifizierung
- **Symfony Security Bundle**: Benutzerauthentifizierung
- **Argon2**: Sichere Passwort-Hashes
- **CSRF-Schutz**: Schutz vor Cross-Site Request Forgery

### Autorisierung
- **Rollenbasierte Zugriffskontrolle**: ROLE_USER, ROLE_ADMIN
- **Symfony Voters**: Granulare Berechtigungen
- **Access Control Lists**: URL-basierte Zugriffskontrolle

### Datenschutz (DSGVO)
- **Datenminimierung**: Nur notwendige Daten speichern
- **Löschbarkeit**: Benutzer und zugehörige Daten löschbar
- **Verschlüsselung**: Passwörter gehash, sensible Daten geschützt

### Eingabevalidierung
- **Symfony Validator**: Server-seitige Validierung
- **Doctrine Types**: Typsichere Datenbankfelder
- **XSS-Schutz**: Automatisches Escaping in Twig

## 📈 Performance & Skalierung

### Caching
- **Symfony Cache**: Application Cache für häufige Abfragen
- **Doctrine Query Cache**: Caching von Datenbank-Abfragen
- **Twig Cache**: Kompilierte Templates

### Optimierungen
- **Doctrine Lazy Loading**: Nur benötigte Daten laden
- **Database Indexing**: Optimierte Datenbankzugriffe
- **Asset Compression**: Minifizierte CSS/JS-Dateien

## 🧪 Test-Strategie

### Unit-Tests
- **PHPUnit**: Testing-Framework
- **Test Coverage**: > 70% für Kernlogik
- **Mocking**: Isolierte Tests mit Doctrine-Mocks

### Integrationstests
- **Symfony Test Client**: HTTP-Request-Tests
- **Database Tests**: Tests mit Testdatenbank
- **Form Tests**: Validierung und Submission

### End-to-End Tests
- **Browser Tests**: Komplette User-Journeys
- **API Tests**: REST-Endpoint-Tests

## 🚀 Deployment-Architektur

### Development
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Nginx     │────│  PHP-FPM    │────│   MariaDB   │
│  (Port 80)  │    │  (Symfony)  │    │ (Port 3306) │
└─────────────┘    └─────────────┘    └─────────────┘
```

### Production
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  Load       │    │   Nginx     │    │  PHP-FPM    │
│  Balancer   │────│  (Reverse   │────│  (Symfony)  │
│             │    │   Proxy)    │    │   Pool      │
└─────────────┘    └─────────────┘    └─────────────┘
                           │                 │
                           ▼                 ▼
                   ┌─────────────┐    ┌─────────────┐
                   │   Redis     │    │   MariaDB   │
                   │  (Session/  │    │  (Master/   │
                   │   Cache)    │    │   Slave)    │
                   └─────────────┘    └─────────────┘
```

## 📝 Code-Standards

### PSR-Standards
- **PSR-4**: Autoloading-Standard
- **PSR-12**: Code-Style-Standard
- **PSR-3**: Logger-Interface

### Clean Code Prinzipien
- **Single Responsibility**: Eine Klasse, eine Aufgabe
- **DRY**: Don't Repeat Yourself
- **SOLID**: Object-Oriented Design Principles

### Dokumentation
- **Deutsche Kommentare**: Erklärungen in deutscher Sprache
- **Englische Namen**: Variablen/Methoden auf Englisch
- **PHPDoc**: Vollständige API-Dokumentation

## 🔄 CI/CD-Pipeline

### GitHub Actions Workflow
1. **Code Quality**: PHP CS Fixer, PHPStan
2. **Tests**: Unit-Tests, Integrationstests
3. **Security**: Dependency-Check, Security-Audit
4. **Build**: Docker Image erstellen
5. **Deploy**: Automatisches Deployment (bei Main-Branch)

### Qualitätssicherung
- **Automatische Tests**: Bei jedem Commit
- **Code Review**: Pull Request Reviews
- **Security Scans**: Regelmäßige Sicherheitsprüfungen
