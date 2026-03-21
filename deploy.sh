#!/bin/bash
# Deploy Script for Side Quest Application
# Usage: ./deploy.sh [environment]

set -e

ENVIRONMENT=${1:-production}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "🚀 Deploying Side Quest to $ENVIRONMENT..."

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}✓${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}⚠${NC} $1"
}

log_error() {
    echo -e "${RED}✗${NC} $1"
}

# Check prerequisites
log_info "Checking prerequisites..."

if ! command -v php &> /dev/null; then
    log_error "PHP is not installed"
    exit 1
fi

if ! command -v mysql &> /dev/null; then
    log_warn "MySQL CLI not found (will skip direct schema import)"
fi

# Check files
if [ ! -f "$SCRIPT_DIR/.env" ]; then
    log_error ".env file not found. Copy .env.example and configure it."
    exit 1
fi

if [ ! -f "$SCRIPT_DIR/schema.sql" ]; then
    log_error "schema.sql not found"
    exit 1
fi

# Create necessary directories
log_info "Creating directories..."
mkdir -p "$SCRIPT_DIR/uploads/proofs"
mkdir -p "$SCRIPT_DIR/logs"

# Set permissions
log_info "Setting permissions..."
chmod 755 "$SCRIPT_DIR/uploads"
chmod 755 "$SCRIPT_DIR/uploads/proofs"
chmod 755 "$SCRIPT_DIR/logs"

if [ "$ENVIRONMENT" = "production" ]; then
    log_warn "Setting strict production permissions..."
    chmod 644 "$SCRIPT_DIR/.env"
fi

# Check database connection
log_info "Testing database connection..."
source "$SCRIPT_DIR/.env"

if command -v mysql &> /dev/null; then
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1" &> /dev/null; then
        log_info "Database connection successful"
        
        # Import schema
        log_info "Importing database schema..."
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCRIPT_DIR/schema.sql"
        log_info "Database schema imported"
    else
        log_error "Database connection failed"
        exit 1
    fi
else
    log_warn "MySQL not available, skipping database import"
    log_warn "Please import schema.sql manually"
fi

# Generate quests
log_info "Generating quests..."
php "$SCRIPT_DIR/generate_quests.php"

# Cleanup
log_info "Cleaning up..."
find "$SCRIPT_DIR/logs" -name "*.log" -type f -mtime +30 -delete 2>/dev/null || true

# Final checks
log_info "Running final checks..."

if [ ! -d "$SCRIPT_DIR/uploads" ] || [ ! -d "$SCRIPT_DIR/logs" ]; then
    log_error "Directory creation failed"
    exit 1
fi

if [ ! -w "$SCRIPT_DIR/uploads" ] || [ ! -w "$SCRIPT_DIR/logs" ]; then
    log_error "Permission issues with uploads or logs directory"
    exit 1
fi

# Success message
echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}✓ Deployment Complete!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo "Application URL: $APP_URL"
echo "Admin Panel: $APP_URL/x9_admin_portal_hidden/admin-login.php?token=$ADMIN_URL_SECRET"
echo ""
echo "Next steps:"
echo "1. Verify the application is accessible"
echo "2. Login as admin (default: admin/admin123)"
echo "3. CHANGE THE DEFAULT ADMIN PASSWORD"
echo "4. Change ADMIN_URL_SECRET in .env"
echo ""

if [ "$ENVIRONMENT" = "development" ]; then
    echo -e "${YELLOW}Development Mode - Check logs/error.log for debugging${NC}"
else
    echo -e "${GREEN}Production Mode - Keep sensitive errors hidden${NC}"
fi
