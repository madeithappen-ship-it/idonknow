# Installation & Deployment Guide

## Quick Start (Local Development)

### Prerequisites
- PHP 7.4+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.4+
- Apache with mod_rewrite enabled
- 50MB free disk space

### Installation Steps

1. **Clone/Download Project**
   ```bash
   cd ~/Desktop
   git clone <your-repo> boringlife
   cd boringlife
   ```

2. **Setup Database**
   ```bash
   # Create database
   mysql -u root -p < schema.sql
   
   # OR if you need to create db first:
   mysql -u root -p -e "CREATE DATABASE sidequest_app;"
   mysql -u root -p sidequest_app < schema.sql
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   
   # Edit .env with your settings
   nano .env
   ```

4. **Set Permissions**
   ```bash
   mkdir -p uploads/proofs logs
   chmod 755 uploads logs
   chmod 755 uploads/proofs
   ```

5. **Generate Quests** (Optional but recommended)
   ```bash
   php generate_quests.php
   ```

6. **Start Development Server**
   ```bash
   php -S localhost:8000
   ```

7. **Access Application**
   - User: http://localhost:8000
   - Admin: http://localhost:8000/x9_admin_portal_hidden/admin-login.php?token=x9_admin_portal_hidden
   - Default creds: admin / admin123

---

## Docker Deployment

### Using Docker Compose (Recommended)

```bash
# Build and start
docker-compose up --build

# Wait for MySQL to be ready (~20 seconds)
# Application: http://localhost:8000

# Generate quests (in another terminal)
docker-compose exec web php generate_quests.php

# Stop services
docker-compose down
```

### Using Docker CLI

```bash
# Build image
docker build -t sidequest:latest .

# Create network
docker network create sidequest-net

# Start MySQL
docker run -d \
  --name sidequest-db \
  --network sidequest-net \
  -e MYSQL_ROOT_PASSWORD=password \
  -e MYSQL_DATABASE=sidequest_app \
  -p 3306:3306 \
  mysql:8.0

# Wait for MySQL (~10 seconds)

# Import schema
docker exec -i sidequest-db mysql -uroot -ppassword sidequest_app < schema.sql

# Start PHP app
docker run -d \
  --name sidequest-web \
  --network sidequest-net \
  -p 8000:80 \
  -e DB_HOST=sidequest-db \
  -e DB_USER=root \
  -e DB_PASS=password \
  -e DB_NAME=sidequest_app \
  sidequest:latest

# Generate quests
docker exec sidequest-web php generate_quests.php

# View application
# http://localhost:8000
```

---

## Render Deployment (Production)

### Option 1: Git Push Deploy (Recommended)

1. **Prepare Repository**
   ```bash
   git init
   git add .
   git commit -m "Initial commit"
   git remote add origin <your-github-repo>
   git push -u origin main
   ```

2. **Create Render Account**
   - Sign up at https://render.com
   - Connect your GitHub account

3. **Create MySQL Database**
   - In Render Dashboard: New → MySQL
   - Database name: `sidequest_app`
   - Username: `sidequest`
   - Note the connection string

4. **Create Web Service**
   - New → Web Service
   - Connect GitHub repo
   - Select this repository
   - Runtime: PHP
   - Build Command: 
     ```
     mkdir -p logs uploads/proofs && \
     chmod 755 logs uploads/proofs
     ```
   - Start Command: `apache2-foreground`

5. **Set Environment Variables**
   ```
   APP_ENV=production
   DEVELOPMENT_MODE=false
   DB_HOST=<database-host>
   DB_USER=sidequest
   DB_PASS=<database-password>
   DB_NAME=sidequest_app
   APP_URL=https://<your-app>.onrender.com
   ADMIN_URL_SECRET=your_secret_here
   ```

6. **Run Setup Commands**
   - Go to Service → Shell
   - ```bash
     mysql -h $DB_HOST -u $DB_USER -p$DB_PASS < schema.sql
     php generate_quests.php
     ```

7. **Deploy**
   - Push any changes to trigger automatic deployment
   - Monitor logs in Render dashboard

### Option 2: Manual Render izer File

The `render.yaml` file is included for automatic Render deployment:

```bash
# Render will read render.yaml and auto-configure everything
git add render.yaml
git push origin main
```

Then on Render dashboard:
- Click "New" → "Infrastructure" → "From Repo"
- Select your GitHub repo
- Render reads render.yaml and deploys automatically

---

## Heroku Deployment

### Procfile Setup
```bash
# Create Procfile
echo "web: vendor/bin/heroku-php-apache2 public/" > Procfile

# Configure buildpacks
heroku buildpacks:add heroku/php

# Deploy
git push heroku main
```

---

## Shared/Typical Hosting Deployment

### Using cPanel/WHM

1. **Upload Files**
   - Use FTP or File Manager
   - Upload all files to public_html/

2. **Create Database**
   - cPanel → MySQL Databases
   - Database: `sidequest_app`
   - User: `sidequest`
   - Import schema.sql via phpMyAdmin

3. **Configure PHP**
   - cPanel → Select PHP Version
   - Ensure PDO MySQL is enabled

4. **Set Permissions**
   ```
   uploads/: 755
   logs/: 755
   ```

5. **Create .env**
   ```bash
   # Use file manager
   Copy .env.example to .env
   Edit with actual database credentials
   ```

6. **Generate Quests**
   - cPanel → Terminal
   - `php generate_quests.php`

---

## PostgreSQL Alternative

If using PostgreSQL instead of MySQL:

1. Change connection string:
```php
$dsn = "pgsql:host=" . $db_config['host'] . 
       ";port=" . $db_config['port'] . 
       ";dbname=" . $db_config['name'];
```

2. Convert schema.sql (uses PostgreSQL syntax)
3. Update config.php for PostgreSQL

---

## Troubleshooting

### Database Connection Failed
- Verify MySQL is running: `systemctl status mysql`
- Check credentials in .env
- Test connection: `mysql -h $DB_HOST -u $DB_USER -p$DB_PASS`

### Permission Denied Errors
```bash
chmod 755 uploads logs
chmod 755 uploads/proofs
chown www-data:www-data uploads logs
```

### Blank Page / 500 Error
- Check logs: `tail -f logs/error.log`
- Verify PHP extensions: `php -m | grep pdo`
- Check Apache modules: `apache2ctl -M | grep rewrite`

### Can't Upload Files
- Check directory exists: `ls -la uploads/proofs/`
- Increase PHP limits:
  ```php
  upload_max_filesize = 10M
  post_max_size = 10M
  ```

### Mail Functionality
- Update .env EMAIL settings
- Or use sendmail: `apt-get install sendmail`

---

## Maintenance After Deployment

### Regular Backups
```bash
# Daily database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > backup-$(date +%Y%m%d).sql

# Upload to S3
aws s3 cp backup-*.sql s3://your-bucket/
```

### Monitor Logs
```bash
# Watch error logs
tail -f logs/error.log

# Check Apache logs
tail -f /var/log/apache2/error.log
```

### Security Hardening
1. Change admin credentials immediately
2. Set `APP_ENV=production`
3. Use strong ADMIN_URL_SECRET
4. Enable HTTPS/SSL
5. Keep PHP updated
6. Regular security scans

### Performance Optimization
1. Enable query caching in MySQL
2. Add Redis for sessions
3. Compress images
4. Use CDN for uploads
5. Add rate limiting

---

## Success Checklist

- [ ] Database created and migrated
- [ ] All files uploaded/cloned
- [ ] .env configured with database credentials
- [ ] Permissions set (755 for uploads/logs)
- [ ] Quests generated (10,000+)
- [ ] Admin login works
- [ ] User registration works
- [ ] Quest assignment works
- [ ] File uploads work
- [ ] Admin can verify submissions
- [ ] HTTPS enabled (production)
- [ ] Admin URL secret changed
- [ ] Default admin password changed

---

## Support Resources

- [PHP Manual](https://www.php.net/manual/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Render Docs](https://render.com/docs)
- [Docker Documentation](https://docs.docker.com/)
- [Apache Rewrite Guide](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html)

---

**Version**: 1.0.0
**Last Updated**: March 2026
