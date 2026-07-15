# 📊 Database Status Report

## Database Information

**Database Name:** uni_activity  
**Container:** laravel-mysql  
**Status:** ✅ Connected and Healthy  
**Date:** April 21, 2026

---

## Table Structure

The database has **22 tables** created:

| Table Name | Records | Status |
|------------|---------|--------|
| users | 0 | Empty |
| activities | 0 | Empty |
| activity_categories | 0 | Empty |
| activity_feedbacks | 0 | Empty |
| admin_audit_logs | 0 | Empty |
| announcements | 0 | Empty |
| attendances | 0 | Empty |
| registrations | 0 | Empty |
| job_listings | 0 | Empty |
| job_applications | 0 | Empty |
| job_comments | 0 | Empty |
| job_inquiries | 0 | Empty |
| notifications_custom | 0 | Empty |
| settings | 0 | Empty |
| cache | 0 | Empty |
| cache_locks | 0 | Empty |
| sessions | 0 | Empty |
| jobs | 0 | Empty |
| job_batches | 0 | Empty |
| failed_jobs | 0 | Empty |
| password_reset_tokens | 0 | Empty |
| migrations | ? | System table |

**Total Records:** 0

---

## ⚠️ Status: Database is Empty

The database structure exists (all tables are created), but there is **no data** in any table.

### What This Means:
- ✅ Migrations have run successfully
- ✅ All tables are created with proper structure
- ❌ No users exist (cannot login)
- ❌ No activities, jobs, or announcements
- ❌ Application will work but show empty lists

---

## 🔧 How to Populate the Database

### Option 1: Run Database Seeders (Recommended)

```powershell
# Run all seeders
docker exec laravel-app php artisan db:seed

# Or run specific seeders
docker exec laravel-app php artisan db:seed --class=UserSeeder
docker exec laravel-app php artisan db:seed --class=ActivityCategorySeeder
```

### Option 2: Import Backup Data

If you have a backup file:

```powershell
# Import SQL backup
docker exec -i laravel-mysql mysql -u root -proot uni_activity < backup.sql

# Or if backup is inside container
docker exec laravel-mysql mysql -u root -proot uni_activity < /path/to/backup.sql
```

### Option 3: Create Admin User Manually

```powershell
docker exec -it laravel-app php artisan tinker
```

Then in tinker:
```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::create([
    'student_id' => 'admin001',
    'full_name' => 'System Administrator',
    'email' => 'admin@university.ac.th',
    'password' => Hash::make('password123'),
    'role' => 'admin',
    'is_active' => true,
    'faculty' => 'Administration',
    'department' => 'IT',
]);

// Press Ctrl+C to exit
```

### Option 4: Create Test Data

```powershell
# Create admin user
docker exec laravel-app php artisan tinker --execute="
\App\Models\User::create([
    'student_id' => 'admin',
    'full_name' => 'Admin User',
    'email' => 'admin@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'is_active' => true
]);
"

# Create student user
docker exec laravel-app php artisan tinker --execute="
\App\Models\User::create([
    'student_id' => '6510001',
    'full_name' => 'Test Student',
    'email' => 'student@test.com',
    'password' => bcrypt('password'),
    'role' => 'student',
    'is_active' => true,
    'faculty' => 'Engineering',
    'department' => 'Computer Science',
    'year' => 3
]);
"
```

---

## 📋 User Table Structure

The users table has these fields:

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| student_id | varchar(20) | Student/Staff ID (unique) |
| full_name | varchar(255) | Full name |
| email | varchar(255) | Email (unique) |
| password | varchar(255) | Hashed password |
| role | enum | student, staff, admin |
| phone | varchar(20) | Phone number |
| faculty | varchar(100) | Faculty name |
| department | varchar(100) | Department name |
| year | tinyint | Year level (for students) |
| program | varchar(50) | Program/Major |
| position | varchar(100) | Position (for staff) |
| organization | varchar(150) | Organization |
| profile_photo | varchar(255) | Photo path |
| is_active | tinyint(1) | Active status |
| last_seen_at | timestamp | Last activity |
| remember_token | varchar(100) | Session token |
| created_at | timestamp | Created date |
| updated_at | timestamp | Updated date |

---

## 🎯 Next Steps

1. **Choose a population method** from the options above
2. **Create at least one admin user** to access the system
3. **Optionally create test data** for development
4. **Verify login** at http://localhost:8000/login

---

## 🔍 Verification Commands

### Check if data was added:
```powershell
# Run the check script
.\check-database.ps1

# Or check manually
docker exec laravel-mysql mysql -u root -proot uni_activity -e "SELECT COUNT(*) FROM users;"
```

### View users:
```powershell
docker exec laravel-mysql mysql -u root -proot uni_activity -e "SELECT id, student_id, full_name, email, role FROM users;"
```

### Test login:
```
URL: http://localhost:8000/login
Email: admin@test.com (or whatever you created)
Password: password (or whatever you set)
```

---

## ✅ Summary

**Database Status:**
- ✅ MySQL container healthy
- ✅ Database `uni_activity` exists
- ✅ All 22 tables created
- ❌ No data in any table
- ⚠️ Cannot login (no users)

**Action Required:**
- Create at least one user to access the system
- Optionally populate with test data or import backup

**Tools Created:**
- `check-database.ps1` - Check database status anytime

---

**The database structure is ready, it just needs data! 🎉**
