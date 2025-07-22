# 🚀 CI/CD Pipeline Dokumentation

## Übersicht der GitHub Actions Workflows

### 1. Haupt-Pipeline: `ci.yml`
**Trigger:** Push auf `main`/`develop`, Pull Requests auf `main`

#### Jobs:
- **`tests`**: Basis-Validierung und Tests
  - ✅ Doctrine Schema Validation
  - ✅ Container Lint Check  
  - ✅ Twig Template Validation
  - ✅ YAML Configuration Check
  - ✅ PHPUnit Tests mit Coverage
  - ✅ PHP CS Fixer Style Check

- **`security`**: Sicherheitsprüfungen
  - 🔒 Composer Security Audit
  - 🔒 Symfony Security Check
  - 🔒 PHP Configuration Analysis

- **`performance`**: Performance-Validierung
  - ⚡ Cache Warmup Tests
  - ⚡ Database Query Performance
  - ⚡ Memory Usage Validation

### 2. Erweiterte Pipeline: `extended-testing.yml`
**Trigger:** Pull Requests, nächtlich um 2:00 UTC, manuell

#### Jobs:
- **`integration-tests`**: Umfassende Tests
  - 🧪 Unit Tests mit separater Coverage
  - 🧪 Integration Tests  
  - 🧪 Functional Tests
  - 🧪 User Journey Simulation
  - 🧪 Advanced System Validation

- **`security-scan`**: Tiefgreifende Sicherheitsanalyse
  - 🔍 Comprehensive Security Scan
  - 🔍 Code Quality Analysis
  - 🔍 File Permissions Check

- **`load-testing`**: Performance unter Last (nur nächtlich/manuell)
  - 📈 Memory Load Tests
  - 📈 Database Stress Tests
  - 📈 Performance Metrics Collection

- **`post-deployment-tests`**: Nach Main-Push
  - ✅ Deployment Readiness Validation
  - ✅ Success Notifications

## Pipeline-Konfiguration

### Test-Umgebungen
```yaml
# Test Database für verschiedene Szenarien
- kpi_tracker_test          # Standard Tests
- kpi_tracker_extended_test # Integration Tests  
- kpi_tracker_perf         # Performance Tests
- kpi_tracker_load_test    # Load Testing
```

### Coverage-Reports
- **Codecov Integration**: Automatischer Upload der Coverage-Reports
- **Separate Coverage**: Unit, Integration, Functional Tests getrennt
- **Coverage-Flags**: `extended-tests` für erweiterte Pipeline

### Fehlerbehandlung
```yaml
# Graceful Degradation
|| echo "::warning::Test completed with warnings"
|| echo "::error::Critical test failed"
```

## Workflow-Optimierungen

### Caching-Strategie
```yaml
# Composer Dependencies Caching
- uses: actions/cache@v3
  with:
    path: vendor
    key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
```

### Matrix-Testing (Zukunft)
```yaml
# Für verschiedene PHP-Versionen
strategy:
  matrix:
    php-version: ['8.2', '8.3']
    dependency-version: ['lowest', 'highest']
```

### Parallelisierung
- Security und Performance Jobs laufen parallel zu Tests
- Extended Testing läuft nur bei Bedarf
- Load Testing nur nächtlich/manuell

## Status-Checks und Branch Protection

### Required Status Checks
```yaml
# Branch Protection Rules
required_status_checks:
  - tests
  - security  
  - performance (bei Performance-kritischen Changes)
```

### Optional Checks
- Extended Integration Tests
- Load Testing Results
- Security Scan Details

## Monitoring und Alerting

### Success-Indikatoren
- ✅ Alle Tests bestanden
- ✅ Keine kritischen Security-Issues
- ✅ Performance innerhalb Grenzen
- ✅ Code Quality Standards erfüllt

### Failure-Handling
```yaml
# Bei kritischen Fehlern
- name: Notify on Failure
  if: failure()
  run: |
    echo "::error::Critical pipeline failure detected"
    # Weitere Benachrichtigungen hier
```

## Lokale Pipeline-Simulation

### Vollständige Test-Suite lokal ausführen
```bash
# Entspricht GitHub Actions Pipeline
make test-full

# Einzelne Pipeline-Komponenten
make validate          # System Validation
make security-check    # Security Tests  
make performance-test  # Performance Tests
```

### Docker-basierte CI-Simulation
```bash
# GitHub Actions Environment nachstellen
docker run --rm -v $(pwd):/app -w /app \
  shivammathur/php:8.2 \
  bash -c "composer install && ./vendor/bin/phpunit"
```

## Pipeline-Metriken

### Durchschnittliche Ausführungszeiten
- **ci.yml**: ~8-12 Minuten
- **extended-testing.yml**: ~15-20 Minuten  
- **load-testing**: ~10-15 Minuten

### Ressourcen-Verbrauch
- **Standard Runner**: ubuntu-latest (2 CPU, 7GB RAM)
- **Parallele Jobs**: Bis zu 3 gleichzeitig
- **Artifact Storage**: Coverage Reports, Test Results

## Best Practices

### 1. Fail Fast
Kritische Tests (Schema Validation) laufen zuerst

### 2. Granulare Reporting
```yaml
echo "::group::Test Category"
# Test commands here
echo "::endgroup::"
```

### 3. Environment Isolation
Separate Datenbanken für verschiedene Test-Typen

### 4. Conditional Execution
```yaml
if: github.event_name == 'pull_request'
if: github.ref == 'refs/heads/main'
```

### 5. Security First
Keine Secrets in Logs, sichere Credential-Handling

## Zukunftige Erweiterungen

### Geplante Verbesserungen
- [ ] Matrix-Testing für PHP 8.3
- [ ] Browser-Tests mit Playwright/Selenium
- [ ] Docker Security Scanning
- [ ] Dependency License Checking
- [ ] Automated Performance Regression Detection
- [ ] Slack/Teams Notifications
- [ ] Custom GitHub Status Checks

### Integration-Möglichkeiten
- **SonarQube**: Code Quality Analysis
- **Snyk**: Erweiterte Security Scans
- **Percy**: Visual Regression Testing
- **Lighthouse**: Performance Audits
