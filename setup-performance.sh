#!/bin/bash
# Performance Optimization Setup Script
# Run this once to optimize your application for production

echo "=========================================="
echo "Side Quest - Performance Optimization Setup"
echo "=========================================="
echo ""

# Check if MySQL client is available
if ! command -v mysql &> /dev/null; then
    echo "ERROR: mysql command not found. Please install MySQL client."
    echo "On Ubuntu/Debian: sudo apt-get install mysql-client"
    echo "On macOS: brew install mysql-client"
    exit 1
fi

# Load environment variables
if [ -f ".env" ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "ERROR: .env file not found!"
    exit 1
fi

# Verify database credentials are set
if [ -z "$DB_HOST" ] || [ -z "$DB_USER" ] || [ -z "$DB_NAME" ]; then
    echo "ERROR: Database credentials not set in .env"
    exit 1
fi

echo "📊 Applying Performance Indexes..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < performance-indexes.sql

if [ $? -eq 0 ]; then
    echo "✅ Indexes applied successfully"
else
    echo "❌ Failed to apply indexes"
    exit 1
fi

echo ""
echo "📁 Creating cache directory..."
mkdir -p cache/
chmod 755 cache/
echo "✅ Cache directory created"

echo ""
echo "📝 Optimizing PHP Configuration..."

# Check if .user.ini exists
if [ -f ".user.ini" ]; then
    echo "✅ .user.ini already exists"
else
    cat > .user.ini << 'EOF'
; Performance Optimization Settings
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=0
opcache.fast_shutdown=1

; Compression
zlib.output_compression=On
zlib.output_compression_level=6

; Memory & Performance
memory_limit=256M
max_execution_time=30
post_max_size=50M
upload_max_filesize=50M

; Database
default_socket_timeout=10

; Session
session.gc_probability=100
session.gc_divisor=1000
session.gc_maxlifetime=86400
EOF
    chmod 644 .user.ini
    echo "✅ .user.ini created with optimizations"
fi

echo ""
echo "📦 Checking for production dependencies..."

# Optional: Check for Redis
if command -v redis-cli &> /dev/null; then
    echo "✅ Redis found - caching will use Redis for better performance"
    echo "   Make sure REDIS_URL is set in .env: redis://host:port"
else
    echo "ℹ️  Redis not installed - using file-based caching (still performs well)"
fi

echo ""
echo "=========================================="
echo "✅ Performance Optimization Complete!"
echo "=========================================="
echo ""
echo "🚀 Next Steps:"
echo "1. Clear any existing PHP opcache: sudo systemctl restart php-fpm"
echo "2. Clear any application cache: rm -rf cache/*"
echo "3. Test the application: your-domain.com"
echo "4. Monitor performance: check logs/error.log"
echo ""
echo "📊 For best results:"
echo "  - Enable GZIP compression on your web server"
echo "  - Use a CDN for static assets"
echo "  - Set up Redis for distributed caching"
echo "  - Monitor database slow query log"
echo ""
