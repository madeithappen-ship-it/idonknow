# 🚀 Quick Start Guide

Get Side Quest running in 5 minutes locally!

## Prerequisites
- PHP 7.4+ installed
- MySQL running
- 50MB free space

## Steps

### 1️⃣ Setup Database (2 min)
```bash
mysql -u root -p < schema.sql
```

### 2️⃣ Configure Environment (1 min)
```bash
cp .env.example .env

# Edit .env:
DB_HOST=localhost
DB_USER=root
DB_PASS=your_password
DB_NAME=sidequest_app
```

### 3️⃣ Create Directories (1 min)
```bash
mkdir -p uploads/proofs logs
chmod 755 uploads logs uploads/proofs
```

### 4️⃣ Generate Quests (1 min)
```bash
php generate_quests.php
```

### 5️⃣ Start Server (< 1 min)
```bash
php -S localhost:8000
```

✅ Done! Visit: http://localhost:8000

---

## First Steps

### Sign Up
1. Go to http://localhost:8000
2. Click "Get Started"
3. Create account (any username/email)

### Complete a Quest
1. Login with your account
2. Click "Get New Quest"
3. Read the challenge
4. Take a photo/screenshot
5. Upload as proof
6. Get verified!

### Access Admin Panel
1. URL: http://localhost:8000/x9_admin_portal_hidden/admin-login.php?token=x9_admin_portal_hidden
2. Username: **admin**
3. Password: **admin123** (⚠️ Change this!)
4. Add more quests, verify submissions, view stats

---

## Deploy Online (Choose One)

### Option A: Render (Easiest)
1. Login to https://render.com
2. Create new MySQL database
3. Create new Web Service
4. Connect your GitHub repo
5. Set environment variables
6. Push to GitHub
7. Auto-deploys!

### Option B: Docker
```bash
docker-compose up --build
# Access at http://localhost:8000
```

### Option C: Traditional Hosting
1. Upload files via FTP
2. Create database in cPanel
3. Import schema.sql
4. Create .env file
5. Run http://yoursite.com

---

## Troubleshooting

### Database Error?
```bash
# Check connection
mysql -u root -p -h localhost
# Test query: use sidequest_app; SELECT 1;
```

### Upload folder errors?
```bash
chmod 755 uploads logs
chown www-data:www-data uploads logs  # Linux
```

### Blank page?
```bash
# Check error log
tail -f logs/error.log

# Verify PHP extensions
php -m | grep pdo
```

---

## File Structure
```
boringlife/
├── index.html          ← Landing page
├── login.php           ← Sign in
├── register.php        ← Create account
├── dashboard.php       ← Main app (after login)
├── admin.php           ← Admin panel
├── config.php          ← Settings
├── schema.sql          ← Database
└── .env.example        ← Copy and configure
```

---

## Default Accounts

| Role | User | Password |
|------|------|----------|
| Admin | admin | admin123 |

Create regular user accounts via registration page.

---

## Next Steps

1. **Change Admin Password**
   - Login to admin panel
   - Update admin password
   - Change ADMIN_URL_SECRET in .env

2. **Add More Quests**
   - Go to Admin Panel > Manage Quests
   - Click "Add New Quest"
   - Fill in details

3. **Verify Submissions**
   - Go to Admin Panel > Submissions
   - Review user proofs
   - Approve or reject

4. **Monitor Users**
   - Check leaderboard
   - View user progress
   - Track submissions

---

## Features Walkthrough

### For Players
- ✅ Get random quests
- ✅ Upload proof photos
- ✅ Earn XP and level up
- ✅ Compete on leaderboard
- ✅ Track your progress

### For Admins
- ✅ Manage 10,000+ quests
- ✅ Review user submissions
- ✅ Approve/reject proofs
- ✅ View analytics
- ✅ Manage users

---

## Common Tasks

### Add this person as Admin
```bash
# Use MySQL client
INSERT INTO admin_users 
(username, email, password, role, is_active) 
VALUES ('john', 'john@example.com', 
'$2y$10$...hashed_password...', 'admin', 1);
```

### Clear old submissions
```sql
DELETE FROM submissions 
WHERE verification_status = 'rejected' 
AND verified_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### View most active users
```sql
SELECT username, level, xp, total_completed
FROM users
WHERE status = 'active'
ORDER BY level DESC, xp DESC
LIMIT 10;
```

---

## Security Reminders

⚠️ **Before going public:**
1. Change default admin password
2. Change ADMIN_URL_SECRET
3. Set APP_ENV=production
4. Enable HTTPS
5. Set strong database password
6. Regular backups

---

## Getting Help

1. Check `README.md` for full docs
2. Check `INSTALLATION.md` for deployment
3. Check `API.md` for API reference
4. Check `logs/error.log` for errors
5. Review code comments in PHP files

---

## Ready? Let's Go! 🎮

```bash
# One-liner to start
php -S localhost:8000 &
echo "Visit http://localhost:8000"
```

**Enjoy building!** 🚀

---

**Version**: 1.0.0
**Status**: Production Ready ✅
**Questions?** Review the documentation files in this directory
