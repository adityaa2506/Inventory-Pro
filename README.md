# Inventory System 2 (Core PHP)

A practical inventory and sales management system built with Core PHP and MySQL, with support for barcode workflows, exhibition sales, containers, and analytics.

## Requirements
- XAMPP (Apache + MySQL + PHP 8+)
- MySQL database: `inventory_system_2`
- PHP extensions commonly enabled in XAMPP (`pdo_mysql`, `gd`, `mbstring`)

## Quick Setup
1. Copy this project folder to `c:/xampp/htdocs/IV AI`.
2. Start Apache and MySQL from XAMPP.
3. Create/import database using `database.sql` in phpMyAdmin.
4. Run initial admin setup once:
	- `http://localhost/IV%20AI/setup_admin.php?run=1`
5. Open login page:
	- `http://localhost/IV%20AI/login.php`

## Default Admin Login
- Email: `admin@inventory.local`
- Password: `admin123`

## Project Structure
- `admin/` authenticated application pages (dashboard, products, sales, analytics, labels, exports)
- `admin/api/` AJAX endpoints for analytics and search
- `includes/` shared config, authentication, helper functions, layout files
- `uploads/` product and barcode assets
- `database.sql` base schema
- `sample_data.sql` optional sample data

## Core Features
- Role-based login and session authentication
- Product CRUD with barcode and image handling
- Barcode scanning (camera + manual fallback)
- Direct product sales and container sales
- Inventory movement logging with action types
- Exhibition mapping, sales analysis, and expense tracking
- Analytics dashboard (revenue, profit, trends, top products, restock hints)
- Thermal/barcode label generation and print support

## Notes
- Database connectivity and environment-specific settings are in `includes/config.php`.
- The app uses PDO prepared statements for database queries.
- Ensure `uploads/` is writable by PHP.
