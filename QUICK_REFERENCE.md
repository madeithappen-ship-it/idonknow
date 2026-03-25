# ⚡ Quick Reference - Performance Optimization Commands

## 🚀 START HERE - First Time Setup

```bash
# 1. Navigate to app directory
cd /path/to/boringlife

# 2. Run performance setup (one command, 5 minutes)
php apply-performance.php

# 3. Clear cache
rm -rf cache/*

# 4. Verify setup worked
curl -I https://your-domain.com/dashboard.php
# Look for: Content-Encoding: gzip ✓
```

---

## 📊 Check Performance

### Response Time
```bash
# Should be < 500ms
time curl https://your-domain.com/dashboard.php > /dev/null
```

### Check Compression
```bash
# Should see 'Content-Encoding: gzip'
curl -I https://your-domain.com/dashboard.php | grep -i encoding
```

### File Size
```bash
# Check actual file on disk
du -sh /path/to/boringlife/cache/
```

---

## 🔧 Daily Operations

### Monitor Cache
```bash
# View cache files
ls -lah cache/

# Count cache files
find cache -type f | wc -l

# Total cache size
du -sh cache/
```

### View Performance Log
```bash
# Check for errors
tail -50 logs/error.log

# Watch for cache hits
grep cache logs/error.log | tail -20
```

### Clear Cache (if needed)
```bash
# Clear all cache
rm -rf cache/*

# Create cache dir if missing
mkdir -p cache/
chmod 755 cache/
```

---

## 🛠️ Database Maintenance

### Check Indexes
```bash
# List all indexes on user_quests table
mysql -h HOST -u USER -p DATABASE -e "SHOW INDEXES FROM user_quests;"

# Check index usage
mysql -h HOST -u USER -p DATABASE -e "SELECT * FROM information_schema.STATISTICS WHERE TABLE_NAME='user_quests';"
```

### Analyze Database
```bash
# Analyze table for optimization
mysql -h HOST -u USER -p DATABASE -e "ANALYZE TABLE user_quests;"

# Check table stats
mysql -h HOST -u USER -p DATABASE -e "SHOW TABLE STATUS WHERE Name='user_quests';"
```

### Monitor Connections
```bash
# Check active connections
mysql -h HOST -u USER -p DATABASE -e "SHOW PROCESSLIST;"

# Check max connections
mysql -h HOST -u USER -p DATABASE -e "SHOW VARIABLES LIKE 'max_connections';"
```

---

## 🟢 Troubleshooting Commands

### If Dashboard is Slow
```bash
# 1. Clear cache
rm -rf cache/*

# 2. Verify indexes exist
mysql -h HOST -u USER -p DATABASE -e "SHOW INDEXES FROM user_quests;" | grep -c "user_id"
# Should show at least 3 indexes

# 3. Restart PHP
sudo systemctl restart php-fpm

# 4. Check disk space
df -h
```

### If Memory Usage Is High
```bash
# 1. Check PHM memory
php -i | grep memory_limit

# 2. Check OPCache
php -i | grep opcache

# 3. Check system
free -h
```

### If Redis Not Working
```bash
# Check Redis status
redis-cli ping
# Should return: PONG

# Check Redis connection
telnet localhost 6379
# Should connect

# View Redis config
redis-cli CONFIG GET "*"
```

---

## 📈 Performance Metrics

### Test with Apache Bench
```bash
# Install: apt-get install apache2-utils

# Simple test: 100 requests
ab -n 100 https://your-domain.com/dashboard.php

# Concurrent test: 10 users, 100 requests each
ab -n 100 -c 10 https://your-domain.com/dashboard.php

# Expected: ~100-200ms per request
```

### Load Test with Siege
```bash
# Install: apt-get install siege

# Simple run
siege -u https://your-domain.com/dashboard.php

# Specify number of concurrent users
siege -c 10 https://your-domain.com/dashboard.php

# Specify duration
siege -c 10 -t 60S https://your-domain.com/dashboard.php
```

---

## 🔑 Common Tasks

### Reset to Baseline
```bash
# Clear cache and restart
rm -rf cache/*
mkdir -p cache/
chmod 755 cache/
sudo systemctl restart php-fpm
```

### Force Full Rebuild
```bash
# 1. Clear cache
rm -rf cache/*

# 2. Reapply indexes
php apply-performance.php

# 3. Verify
curl -I https://your-domain.com/dashboard.php
```

### Check Everything
```bash
# Run all checks
echo "=== Indexes ===" && \
mysql -h HOST -u USER -p DB -e "SHOW INDEXES FROM user_quests;" | wc -l && \
echo "=== Cache ===" && \
find cache -type f | wc -l && \
echo "=== Compression ===" && \
curl -I https://your-domain.com | grep -i gzip && \
echo "=== Response Time ===" && \
time curl -s https://your-domain.com/ > /dev/null
```

---

## 📋 Setup Checklist

- [ ] Run: `php apply-performance.php`
- [ ] Clear cache: `rm -rf cache/*`
- [ ] Test compression: `curl -I [url] | grep gzip`
- [ ] Test speed: `time curl [url] > /dev/null`
- [ ] Check indexes: `mysql -e "SHOW INDEXES FROM user_quests;"`
- [ ] Monitor logs: `tail logs/error.log`

---

## 🚀 Optional Enhancements

### Enable Redis
```bash
# Install Redis
sudo apt-get install redis-server

# Start Redis
sudo systemctl start redis-server

# Test Redis
redis-cli ping

# Update .env
echo "REDIS_URL=redis://127.0.0.1:6379" >> .env
```

### Setup Cron for Cache Cleanup
```bash
# Edit crontab
crontab -e

# Add this line (cleanup cache files older than 2 days at 2 AM)
0 2 * * * find /path/to/boringlife/cache -type f -mtime +2 -delete
```

### Enable Nginx Compression
```nginx
# Add to nginx.conf
gzip on;
gzip_vary on;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml application/json application/javascript;
gzip_disable "msie6";
client_max_body_size 50M;
```

---

## 📞 Quick Help

| Problem | Solution |
|---------|----------|
| Dashboard slow | `rm -rf cache/*` then `php apply-performance.php` |
| High memory | Check `php -i \| grep memory_limit` |
| Queries slow | Verify indexes: `mysql -e "SHOW INDEXES FROM user_quests;"` |
| Redis not connecting | Test: `redis-cli ping` |
| Cache not working | Check permissions: `chmod 755 cache/` |

---

## 🎯 Performance Goals

| Metric | Target | How to Check |
|--------|--------|-------------|
| Response time | < 500ms | `time curl [url]` |
| Page size | < 1MB | Network tab in DevTools |
| Database queries | < 5 | Check logs |
| Cache hit rate | > 80% | Monitor logs |
| Concurrent users | 500+ | Load test with Siege |

---

## 📚 Documentation

- **PERFORMANCE.md** - Full documentation
- **DEPLOYMENT.md** - Deployment steps
- **FRONTEND_OPTIMIZATION.md** - CSS/JS optimization
- **OPTIMIZATION_SUMMARY.md** - Overview

---

## ✅ Status Check

```bash
#!/bin/bash
echo "Performance Optimization Status Check"
echo "======================================"
echo ""

echo "1. Cache Directory:"
[ -d "cache" ] && echo "✅ Cache directory exists" || echo "❌ Cache directory missing"

echo ""
echo "2. Database Indexes:"
INDEXES=$(mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME -e "SHOW INDEXES FROM user_quests;" 2>/dev/null | wc -l)
echo "Found $INDEXES index references (should be > 10)"

echo ""
echo "3. Response Compression:"
curl -s -I https://your-domain.com/dashboard.php | grep -i "Content-Encoding: gzip" && echo "✅ GZIP enabled" || echo "❌ GZIP not found"

echo ""
echo "4. Response Time:"
time curl -s https://your-domain.com/dashboard.php > /dev/null

echo ""
echo "5. Disk Usage:"
du -sh cache/ logs/ uploads/ 2>/dev/null
```

---

**Last Updated:** March 25, 2026
**Status:** 🟢 Production Ready
**Version:** 1.0
