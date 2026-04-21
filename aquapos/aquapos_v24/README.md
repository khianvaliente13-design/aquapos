# 💧 AquaStation — Water Refilling Station Management System

## Setup Instructions (XAMPP)

### 1. Copy Files
Place the `aquapos` folder inside your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\aquapos\
```

### 2. Start XAMPP
- Start **Apache** and **MySQL** in the XAMPP Control Panel.

### 3. Import the Database
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **Import** tab
3. Choose the file: `aquapos/database.sql`
4. Click **Go**

### 4. Configure Database (if needed)
Edit `includes/config.php` if your MySQL credentials differ:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');   // change if you have a password
define('DB_NAME', 'aquastation');
```

### 5. Open the App
Navigate to: `http://localhost/aquapos/`

---

## Default Login
| Username | Password  | Role  |
|----------|-----------|-------|
| admin    | password  | Admin |
| cashier1 | password  | Cashier |

> ⚠️ Change passwords after first login in production!

---

## POS Features
- 🛒 **Product Grid** — Browse and search products by category
- 🪣 **Cart System** — Add/remove items, adjust quantities
- 👤 **Customer Selection** — Walk-in or registered customers
- 💵 **Payment Methods** — Cash, GCash, Maya
- ⚡ **Quick Amounts** — Exact, ₱100, ₱200, ₱500, ₱1000
- 🧾 **Receipt Generation** — On-screen + printable receipt
- 📦 **Auto Inventory Deduction** — Stock updates on every sale
- 📝 **Transaction Logging** — Full history in database

---

## Project Structure
```
aquapos/
├── index.php           # Entry point (redirects to login/pos)
├── login.php           # Login page
├── logout.php          # Logout
├── pos.php             # Main POS interface
├── database.sql        # Database schema + seed data
├── includes/
│   └── config.php      # DB config & helper functions
└── api/
    └── pos.php         # AJAX API for POS operations
```

---

## Upcoming Modules
- 📊 Dashboard — Sales overview, charts
- 📦 Inventory — Stock management
- 👥 Users — User management
- 🚚 Delivery — Delivery order tracking
