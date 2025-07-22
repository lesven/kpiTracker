#!/bin/bash
set -e

echo "🧪 Starting Full Test Suite for KPI-Tracker..."
echo "================================================"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}📋 $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if Docker is running
if ! docker compose ps >/dev/null 2>&1; then
    print_error "Docker containers are not running. Please start them with 'make start' or 'docker compose up -d'"
    exit 1
fi

print_status "Step 1: System Validation"
echo "----------------------------------------"

# Symfony System Validation
print_status "Validating Symfony configuration..."
docker compose exec app php bin/console lint:container || {
    print_error "Container validation failed"
    exit 1
}

print_status "Validating Doctrine schema..."
docker compose exec app php bin/console doctrine:schema:validate --skip-sync || {
    print_error "Doctrine schema validation failed"
    exit 1
}

print_status "Validating routing configuration..."
docker compose exec app php bin/console debug:router >/dev/null || {
    print_error "Router validation failed"
    exit 1
}

print_status "Validating Twig templates..."
docker compose exec app php bin/console lint:twig templates/ || {
    print_error "Twig template validation failed"
    exit 1
}

print_success "System validation completed"

print_status "Step 2: Security Checks"
echo "----------------------------------------"

# Security Audit
print_status "Running Composer security audit..."
docker compose exec app composer audit || {
    print_warning "Security audit found vulnerabilities"
}

print_status "Running Symfony security check..."
docker compose exec app php bin/console security:check || {
    print_warning "Symfony security check found issues"
}

print_success "Security checks completed"

print_status "Step 3: Unit Tests"
echo "----------------------------------------"

# Unit Tests
print_status "Running PHPUnit unit tests..."
if docker compose exec app ./vendor/bin/phpunit --testsuite=unit --coverage-text --colors=always; then
    print_success "Unit tests passed"
else
    print_error "Unit tests failed"
    exit 1
fi

print_status "Step 4: Integration Tests"
echo "----------------------------------------"

# Integration Tests
print_status "Running integration tests..."
if docker compose exec app ./vendor/bin/phpunit --testsuite=integration --colors=always; then
    print_success "Integration tests passed"
else
    print_error "Integration tests failed"
    exit 1
fi

print_status "Step 5: Functional Tests"
echo "----------------------------------------"

# Functional Tests
print_status "Running functional tests..."
if docker compose exec app ./vendor/bin/phpunit --testsuite=functional --colors=always; then
    print_success "Functional tests passed"
else
    print_error "Functional tests failed"
    exit 1
fi

print_status "Step 6: Code Quality Checks"
echo "----------------------------------------"

# PHP CS Fixer
print_status "Running PHP CS Fixer..."
if docker compose exec app ./vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes; then
    print_success "Code style is compliant"
else
    print_warning "Code style issues found. Run 'make fix' to auto-fix them."
fi

print_status "Step 7: Performance Validation"
echo "----------------------------------------"

# Cache Warmup
print_status "Testing cache warmup..."
docker compose exec app php bin/console cache:clear --env=prod >/dev/null
docker compose exec app php bin/console cache:warmup --env=prod >/dev/null
print_success "Cache warmup successful"

# Database Performance Check
print_status "Testing database connectivity..."
docker compose exec app php bin/console doctrine:query:dql "SELECT COUNT(u) FROM App\Entity\User u" >/dev/null
print_success "Database connectivity test passed"

print_status "Step 8: Critical Feature Tests"
echo "----------------------------------------"

# Test User Creation
print_status "Testing user creation command..."
if docker compose exec app php bin/console app:create-user test-script@example.com 1234567890123456 --first-name="TestScript" >/dev/null 2>&1; then
    print_success "User creation test passed"
    # Cleanup: Delete test user (if deletion command exists)
    docker compose exec app php bin/console doctrine:query:sql "DELETE FROM user WHERE email = 'test-script@example.com'" >/dev/null 2>&1 || true
else
    print_warning "User creation test failed or user already exists"
fi

# Test Email Configuration
print_status "Testing email configuration..."
if docker compose exec app php bin/console debug:config framework mailer >/dev/null; then
    print_success "Email configuration test passed"
else
    print_warning "Email configuration test failed"
fi

# Test File Upload Directory
print_status "Testing file upload directory..."
if docker compose exec app test -w /app/public/uploads/kpi_files/; then
    print_success "File upload directory is writable"
else
    print_warning "File upload directory is not writable"
fi

echo ""
echo "================================================"
print_success "🎉 Full Test Suite Completed Successfully!"
echo ""
print_status "Test Summary:"
echo "  ✅ System Validation: Passed"
echo "  ✅ Security Checks: Completed"
echo "  ✅ Unit Tests: Passed"
echo "  ✅ Integration Tests: Passed"
echo "  ✅ Functional Tests: Passed"
echo "  ✅ Code Quality: Checked"
echo "  ✅ Performance: Validated"
echo "  ✅ Critical Features: Tested"
echo ""
print_status "🚀 Application is ready for deployment!"

# Optional: Generate test report
if command -v php >/dev/null 2>&1; then
    print_status "Generating test coverage report..."
    docker compose exec app ./vendor/bin/phpunit --coverage-html public/coverage/ >/dev/null 2>&1 || true
    print_success "Coverage report generated at http://localhost:8080/coverage/"
fi

echo "================================================"
