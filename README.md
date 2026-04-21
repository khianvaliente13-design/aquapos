# 💧 AquaStation — Water Refilling Station Management System

> **Version 2.0** — Full-featured POS with Admin Panel, Loyalty Points, and Real-time Sync

---

## 📋 Table of Contents
- [Requirements](#requirements)
- [Setup Instructions](#setup-instructions)
- [Default Login Credentials](#default-login-credentials)
- [Project Structure](#project-structure)
- [Features](#features)
- [Portal Overview](#portal-overview)
- [Troubleshooting](#troubleshooting)

---

## ✅ Requirements

- **XAMPP** (Apache + MySQL) — v7.4 or higher
- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher
- A modern web browser (Chrome, Firefox, Edge)

---

## ⚙️ Setup Instructions (XAMPP)

### Step 1 — Copy Files
Place the `aquapos` folder inside your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\aquapos\
```

### Step 2 — Start XAMPP
Open the **XAMPP Control Panel** and start:
- ✅ **Apache**
- ✅ **MySQL**

### Step 3 — Import the Database
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click the **SQL** tab at the top
3. Copy and paste the entire contents of `database.sql`
4. Click **Go**

> ✅ This will automatically create the `aquastation` database with all tables and sample data.

### Step 4 — Configure Database (if needed)
If your MySQL uses a different username or password, edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');        // ← change if you have a MySQL password
define('DB_NAME', 'aquastation');
```

### Step 5 — Open the App
Navigate to:
```
http://localhost/aquapos/
```
You will be automatically redirected to the Cashier login page.

---

## 🔑 Default Login Credentials

| Portal   | URL                              | Username   | Password   | Role     |
|----------|----------------------------------|------------|------------|----------|
| Admin    | `/aquapos/admin_login.php`       | `admin`    | `password` | Admin    |
| Cashier  | `/aquapos/cashier_login.php`     | `cashier1` | `password` | Cashier  |

> ⚠️ **Important:** Change all passwords after your first login in a production environment!

---

## 📁 Project Structure

```
aquapos/
│
├── 📄 index.php                  # Entry point — auto-redirects based on session
├── 📄 admin_login.php            # Admin portal login
├── 📄 cashier_login.php          # Cashier / Staff portal login
├── 📄 pos.php                    # Main POS interface (cashier view)
├── 📄 logout.php                 # Logout handler (supports ?portal=admin|cashier)
├── 📄 force_logout.php           # Force-clears both sessions
├── 📄 landing.php                # Redirect shim (deprecated)
├── 📄 login.php                  # Backward-compat redirect
├── 📄 setup_passwords.php        # One-time setup tool — delete after use!
├── 📄 database.sql               # Full DB schema + seed data
├── 📄 README.md                  # This file
│
├── 📂 admin/                     # Admin panel pages
│   ├── dashboard.php             # Sales overview & stats
│   ├── products.php              # Product & price management
│   ├── refills.php               # Refill options management
│   ├── customers.php             # Customer management & loyalty points
│   └── transactions.php          # Transaction history & receipts
│
├── 📂 api/                       # Backend API endpoints (JSON)
│   ├── admin.php                 # Admin API (products, customers, transactions, stats)
│   ├── pos.php                   # POS API (products, checkout, receipt)
│   └── customer.php              # Customer search & loyalty calculation
│
├── 📂 includes/                  # Shared PHP helpers
│   ├── config.php                # DB config, session helpers, DB connection
│   └── auth.php                  # Role-based access control
│
└── 📂 assets/                    # Frontend assets
    ├── css/
    │   ├── pos.css               # POS interface styles
    │   ├── admin.css             # Admin panel styles
    │   └── auth.css              # Login page styles
    └── js/
        ├── pos.js                # POS logic (cart, checkout, receipt, sync)
        ├── admin-dashboard.js    # Dashboard stats loader
        ├── admin-products.js     # Product CRUD + inline price editor
        ├── admin-refills.js      # Refill options management
        ├── admin-customers.js    # Customer CRUD + points adjustment
        └── admin-transactions.js # Transaction history, filters, pagination
```

---

## 🚀 Features

### 🛒 Point of Sale (Cashier)
- **Product Grid** — Browse and search products by category
- **Refill Mode** — Separate tab for container refill transactions
- **Cart System** — Add/remove items, adjust quantities
- **Customer Lookup** — Search registered customers by name or phone
- **Loyalty Points** — Apply points as discount (up to 20% of total)
- **Payment Methods** — Cash, GCash, Maya
- **Quick Amount Buttons** — Exact, ₱100, ₱200, ₱500, ₱1,000
- **Receipt Generation** — On-screen receipt with print support
- **Auto Stock Deduction** — Inventory updates on every completed sale
- **Real-time Price Sync** — Cashier sees price changes made by Admin instantly

### 📊 Admin Panel
- **Dashboard** — Today's revenue, transactions, monthly summary, low stock alerts
- **Products & Prices** — Add/edit products, click-to-edit prices (syncs to POS live)
- **Refill Options** — Manage refill container types and prices
- **Customer Management** — Add/edit customers, adjust loyalty points, view purchase history
- **Transaction History** — Full log with search, date filters, paginated view, receipt modal, delete

### 🔐 Security
- Separate session names for Admin (`aquapos_admin`) and Cashier (`aquapos_cashier`)
- Role-based access control — Admin and Cashier cannot access each other's portals
- All DB queries use prepared statements (SQL injection protection)
- Passwords stored using PHP `password_hash()` (bcrypt)

---

## 🖥️ Portal Overview

### Cashier Portal → `http://localhost/aquapos/cashier_login.php`
For cashiers and delivery staff. After login, goes directly to the POS screen.

### Admin Portal → `http://localhost/aquapos/admin_login.php`
For administrators. After login, goes to the dashboard with full management access.

---

## 🔧 Troubleshooting

| Problem | Solution |
|---|---|
| **Blank page / PHP errors** | Make sure Apache is running in XAMPP |
| **"Database connection failed"** | Make sure MySQL is running; check `config.php` credentials |
| **"Please re-import database.sql"** | Go to phpMyAdmin and re-import `database.sql` |
| **Login not working** | Run `setup_passwords.php` once to reset default passwords |
| **POS prices not updating** | Prices sync every 12 seconds automatically |
| **Stock not deducting** | Only products (not refills) deduct from inventory |

---

## 🗑️ Post-Setup Cleanup

After your first successful login, **delete** the following file for security:
```
aquapos/setup_passwords.php
```

---

## 📌 Notes

- The `aquapos_v24/` folder (if present) is an older backup — do **not** use it in production.
- The `dashboard/` folder at the root `htdocs` level is a separate unrelated project.
- Loyalty points: **1 point earned per ₱10 spent** | **₱0.50 value per point** | Max 20% discount per transaction.

---

*AquaStation POS v2.0 — Built for local water refilling station operations.*
