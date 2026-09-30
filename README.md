# AGROVISE

Website and business management system for AGROVISE agriculture products
(insecticides, weedicides, fungicides, granulars and micronutrients).

- **Public site** – `index.php` and `categories/`: the product catalogue.
- **Admin panel** – `admin/`: products, purchasing and stock lots, packing,
  invoices, sales policies, payments, banking, clients, vendors, employees,
  ledgers, reports and site traffic.

Plain PHP 8 + MySQL, no framework and no build step.

## Requirements

- PHP 8 (developed on 8.3) with PDO MySQL
- MySQL 8 / MariaDB
- Apache with `.htaccess` support (`AllowOverride All`)

## Local setup (WAMP / XAMPP)

1. Put the project in the web root, e.g. `www/agrovise`.
2. Create a database named `agrovise_db` and import `database/schema.sql`.
3. Open `http://localhost/agrovise/`. The admin panel is at
   `http://localhost/agrovise/admin/login.php`.

Without `includes/config.php` the app connects as `root` with no password,
which matches a default WAMP install.

## Deploying to a PHP host (cPanel)

1. In **MySQL Databases**, create a database and a user, and give the user
   all privileges on the database.
2. In **phpMyAdmin**, select the database and import `database/schema.sql`.
3. Clone this repository into `public_html` with **Git Version Control**
   (or upload the files).
4. Copy `includes/config.example.php` to `includes/config.php` and enter the
   database name, user and password from step 1.
5. Make sure `uploads/` is writable by PHP.
6. Enable SSL, then log in with `admin` / `admin123` and change the password
   immediately.

## Static preview on GitHub Pages

`docs/` holds a static copy of the public site (no admin panel), which GitHub
Pages serves from the `main` branch `/docs` folder. Rebuild it after
publishing products or changing the public pages:

```
php tools/build-pages.php
```

The database must be running while it builds.
