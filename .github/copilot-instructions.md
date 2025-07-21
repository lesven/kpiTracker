# 🤖 AI Coding Instructions for KPI-Tracker

## Architecture Overview
This is a **Symfony 7 LTS** KPI tracking application with strict German documentation standards and role-based access control.

### Key Architectural Patterns
- **Email-based Authentication**: Users identified by email (`User::getUserIdentifier()` returns email)
- **Role-based Security**: Two roles `ROLE_USER` (default) and `ROLE_ADMIN` with Voter-based permissions
- **Entity Ownership Model**: Users own KPIs, KPIs contain KPIValues, KPIValues can have file attachments
- **GDPR Compliance**: `UserService::deleteUserWithData()` handles cascading deletion

## Critical Development Workflows

### Database Operations
```bash
# Create migration after entity changes
docker compose exec app php bin/console doctrine:migrations:diff
# Apply migrations  
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
# Clear cache after config changes
docker compose exec app php bin/console cache:clear
```

### User Management Commands
```bash
# Create admin user (User Story 2)
docker compose exec app php bin/console app:create-admin email@example.com [password]
# Create regular user (User Story 15) - requires 16-char password
docker compose exec app php bin/console app:create-user email@example.com 1234567890123456 --first-name="Name"
```

### Container Access
- Primary development: `docker compose exec app bash`
- Use `make install` for complete setup, `make fix-permissions` for Docker permission issues

## Security & Access Control

### Authentication Setup
- Login uses email/password via `security.yaml` with `username_parameter: email`
- Forms must use `name="email"` not `_username` for login
- CSRF protection enabled: `{{ csrf_token('authenticate') }}` in login forms

### Authorization Patterns
- Controllers use `#[IsGranted('ROLE_ADMIN')]` for admin-only routes
- Entity access via Voters: `$this->denyAccessUnlessGranted('edit', $kpi)` in controllers
- KPIVoter checks ownership: users can only access their own KPIs unless admin

## Form & Entity Conventions

### Entity Relationships
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
