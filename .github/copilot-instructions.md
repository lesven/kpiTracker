# 🤖 AI Coding Instructions for KPI-Tracker

## Architecture Overview
This is a **Symfony 7 LTS** KPI tracking application with strict German documentation standards and role-based access control.

### Key Architectural Patterns
- **Email-based Authentication**: Users identified by email (`User::getUserIdentifier()` returns email)
- **Role-based Security**: Two roles `User::ROLE_USER` (default) and `User::ROLE_ADMIN` with Voter-based permissions
- **Entity Ownership Model**: Users own KPIs, KPIs contain KPIValues, KPIValues can have file attachments
- **GDPR Compliance**: `UserService::deleteUserWithData()` handles cascading deletion

## Critical Development Workflows

### Docker-First Development
```bash
# Complete setup (preferred workflow)
make install          # Full Docker setup with permissions, DB, migrations
make start            # Start containers only
make shell            # Access container shell for debugging
make fix-permissions  # Fix Docker volume permission issues (Windows/macOS)
```

### Database Operations
```bash
# After entity changes - always create migrations
docker compose exec app php bin/console doctrine:migrations:diff
make migrate          # Apply migrations
make seed            # Load test fixtures
```

### User Management Commands (Critical for Setup)
```bash
# Create admin user (User Story 2) 
docker compose exec app php bin/console app:create-admin email@example.com [password]
# Create regular user (User Story 15) - REQUIRES exactly 16-char password
docker compose exec app php bin/console app:create-user email@example.com 1234567890123456 --first-name="Name"
```

### Testing & Quality Pipeline
```bash
make test-full        # Complete test suite with validation (scripts/run-full-tests.sh)
make test            # Basic PHPUnit tests
make validate        # Symfony system validation
make security-check  # Security audits
make lint && make fix # Code style PSR-12
```

## Security & Access Control

### Authentication Setup
- Login uses **email/password** via `security.yaml` with `username_parameter: email`
- Forms must use `name="email"` not `_username` for login
- CSRF protection enabled: `{{ csrf_token('authenticate') }}` in login forms

### Authorization Patterns
- Controllers use `#[IsGranted('ROLE_ADMIN')]` for admin-only routes
- Entity access via Voters: `$this->denyAccessUnlessGranted('edit', $kpi)` in controllers
- **Use Constants**: `User::ROLE_ADMIN`, `User::ROLE_USER` (not string literals)

## Entity & Form Conventions

### Entity Relationships & Recent Updates
```php
User 1:N KPI 1:N KPIValue 1:N KPIFile
```

### Entity Best Practices (Post-Refactoring)
- **User Entity**: Has constants `ROLE_USER`, `ROLE_ADMIN`, `API_TOKEN_LENGTH`
- **Dual Setter Pattern**: `setEmail()` for forms, `setEmailWithValidation()` for commands
- **Helper Methods**: `getFullName()`, `hasRole()`, `addRole()`, `clearApiToken()`
- **Defensive Programming**: Early returns, null-safe operations, input trimming

### Form Types by Use Case
- `UserType`: Admin user management (has `is_edit` option for password handling)
- `KPIType`: User KPI creation  
- `KPIAdminType`: Admin KPI creation with user selection
- `KPIValueType`: Value entry with file upload support

### Template Integration
- Bootstrap 5 with custom CSS classes: `.status-green`, `.status-yellow`, `.status-red`
- German labels: "E-Mail-Adresse", "Passwort", "Anmelden"
- Use `{{ encore_entry_link_tags('app') }}` for assets

## Business Logic Services

### KPI Status System (User Story 9)
- `KPIStatusService::getKpiStatus()` returns color-coded status
- Period calculation: `KPI::getCurrentPeriod()` formats based on interval

### File Upload Handling (User Story 13)
- `FileUploadService::handleFileUploads()` processes multiple files
- Files stored in `uploads/kpi_files/` with UUID filenames
- Supported: PDF, Word, Excel, images, text (5MB limit)

### Email Reminders (User Stories 6-7)
- `ReminderService` sends automated reminders
- Templates in `templates/emails/`

## Testing & Quality Standards

### Code Standards
- **German comments, English code**: `// Prüft ob Benutzer Administrator ist` but `public function isAdmin()`
- **PSR-12 compliance**: Auto-enforced via `make fix`
- **Return types required**: All methods need explicit return types

### Test Organization
- **3-tier test suite**: unit, integration, functional
- **Full validation script**: `scripts/run-full-tests.sh` includes system validation
- **Coverage reports**: Generated to `public/coverage/`

## Common Patterns & Anti-Patterns

### Repository Usage
- Custom queries: `findByUser()`, `findByKpiAndPeriod()`
- Use constants in queries: `'%' . User::ROLE_ADMIN . '%'` not `'%ROLE_ADMIN%'`

### Entity Method Patterns
- **Status methods**: `User::isAdmin()`, `KPI::getStatusColor()`
- **Collection helpers**: Early returns for idempotent operations
- **Clean Code patterns**: Constants over magic strings, defensive programming

### Service Injection
- Constructor injection with `private readonly` properties
- Specific services over generic EntityManager when possible

### Migration Workflow
**Critical**: Always create migrations after entity changes. New fields like `unit`, `target` need migrations even if optional. Run `doctrine:migrations:diff` after every entity modification.

## Development Environment

### Container Structure
- **Primary container**: `app` (PHP 8.2-fpm)
- **Database**: MySQL with symfony/kpi_tracker
- **Working directory**: `/var/www/html` (always use `--workdir` flag)
- **Port**: Application runs on `localhost:8080`

### Permission Management
- **Windows/macOS**: Use `make fix-permissions` for Docker volume issues
- **Root operations**: Use `--user root` flag when needed
- **File ownership**: `www-data:www-data` inside container
```php
User 1:N KPI 1:N KPIValue 1:N KPIFile
```

### Form Types by Use Case
- `UserType`: Admin user management (has `is_edit` option for password handling)
- `KPIType`: User KPI creation  
- `KPIAdminType`: Admin KPI creation with user selection
- `KPIValueType`: Value entry with file upload support

### Template Integration
- Bootstrap 5 with custom CSS classes: `.status-green`, `.status-yellow`, `.status-red`
- Encore assets: Use `{{ encore_entry_link_tags('app') }}` or direct links if compilation issues
- German labels throughout: "E-Mail-Adresse", "Passwort", "Anmelden"

## Business Logic Services

### KPI Status System (User Story 9)
- `KPIStatusService::getKpiStatus()` returns color-coded status:
  - `green`: Value exists for current period
  - `yellow`: Due within 3 days  
  - `red`: Overdue
- Period calculation: `KPI::getCurrentPeriod()` formats based on interval (weekly: Y-W, monthly: Y-m, quarterly: Y-Q*)

### File Upload Handling (User Story 13)
- `FileUploadService::handleFileUploads()` processes multiple files
- Files stored in `uploads/kpi_files/` with UUID filenames
- Supported formats: PDF, Word, Excel, images, text files (5MB limit)

### Email Reminders (User Stories 6-7)
- `ReminderService` sends automated reminders: 3 days before, 7/14 days after due
- Escalation to admins after 21 days overdue
- Templates in `templates/emails/`

## Testing & Quality Standards

### Code Standards
- **German comments**, **English code**: `// Prüft ob Benutzer Administrator ist` but `public function isAdmin()`
- PSR-12 compliance: `./vendor/bin/php-cs-fixer fix`
- Return types required: `public function getEmail(): ?string`

### Testing Commands  
```bash
make test          # Run PHPUnit tests
make coverage      # Generate coverage report  
make lint          # Check code style
make fix           # Auto-fix style issues
```

## Common Patterns & Anti-Patterns

### Repository Usage
- Custom queries in repositories: `findByUser()`, `findByKpiAndPeriod()`
- Count methods: `countUsers()`, `countAdmins()` for dashboard stats

### Entity Method Patterns
- Status methods: `User::isAdmin()`, `KPI::getStatusColor()`
- Formatting: `KPIValue::getFormattedPeriod()`
- Collection helpers: `User::addKpi()`, `User::removeKpi()`

### Service Injection
- Controllers inject specific services, not generic EntityManager when possible
- Services use constructor injection with private readonly properties
- Logger injection for audit trails in GDPR operations

### Migration Workflow
Always create migrations after entity changes, even for new optional fields like `unit` and `target` on KPI entity. Properties need corresponding database columns and getter/setter methods.
