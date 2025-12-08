# Mawgifi - Setup Instructions

## ⚠️ Important: Use XAMPP Apache Server

Five Server (port 5500) doesn't support PHP sessions and MySQL properly. 
You MUST use XAMPP's Apache server for this project to work.

## 🚀 Setup Steps:

### 1. Start XAMPP
- Open XAMPP Control Panel
- Start **Apache** (port 80)
- Start **MySQL** (port 3306)

### 2. Import Database
- Open phpMyAdmin: http://localhost/phpmyadmin
- Click "Import" tab
- Select `database.sql` file
- Click "Go"
- Database `mawgifi` will be created

### 3. Move Project to XAMPP
Option A: Copy files
```
Copy WEB folder to: C:\xampp\htdocs\WEB
```

Option B: Create symbolic link (recommended)
```powershell
# Run in PowerShell as Administrator
New-Item -ItemType SymbolicLink -Path "C:\xampp\htdocs\WEB" -Target "C:\Users\Lenovo\OneDrive\Desktop\WEB"
```

### 4. Access the Application
- Landing Page: http://localhost/WEB/
- Login: http://localhost/WEB/login.php

### 5. Test Login
Use these credentials:
- **Admin**: admin@parking.com / password
- **Student**: john@example.com / password
- **Student**: jane@example.com / password

## ❌ Why Five Server Doesn't Work:

Five Server is a static file server that has limited PHP support:
- ❌ No session handling
- ❌ No MySQL database connections
- ❌ No proper POST request handling
- ✅ Only good for HTML/CSS/JS preview

## ✅ Solution: Use XAMPP Apache

XAMPP provides:
- ✅ Full PHP support
- ✅ MySQL database
- ✅ Session management
- ✅ All server-side features

## 🔧 Quick Fix Command:

Run this in PowerShell (as Administrator):
```powershell
# Navigate to XAMPP htdocs
cd C:\xampp\htdocs

# Create symbolic link
New-Item -ItemType SymbolicLink -Path "WEB" -Target "C:\Users\Lenovo\OneDrive\Desktop\WEB"
```

Then access: http://localhost/WEB/

---
**Note**: After setup, always use `http://localhost/WEB/` instead of `127.0.0.1:5500`
