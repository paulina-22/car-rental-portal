# Car Rental Portal

I built this car rental web application as a university project using PHP 8+, MySQL/MariaDB, HTML, CSS and JavaScript. My application has a Lithuanian interface.

## Features
- Registration and sign-in with hashed passwords.
- Customer, vehicle owner and controller roles.
- Vehicle listings, image uploads and vehicle details.
- Date-based reservations, approval and cancellation.
- Reviews and ratings.

## Reconstructed database
I no longer have my original database export, so this repository includes a reconstructed schema based on the PHP queries. `database.sql` defines six tables: users, vehicle_groups, vehicles, vehicle_images, reservations and reviews. This is a compatible reconstruction, not a recovered copy of my original database. Both the legacy `grupe` and `group_id` fields are retained for compatibility with my original pages.

`seed-demo.php` adds three fictional accounts, three cars, two reservations and a review. All names, emails and records are demonstration data. `demo-car.svg` is a local illustration; original uploaded photographs are excluded.

## Run locally on Windows with XAMPP
1. Start MySQL in the XAMPP Control Panel.
2. Open Git Bash in this repository (the folder containing `login.php`).
3. Create the demonstration database:

```bash
/c/xamppnew/php/php.exe setup-demo.php
```

If XAMPP is installed in `C:/xampp`, replace `/c/xamppnew` with `/c/xampp` in these commands. Setup defaults to the database `nuomosportalas_demo`, local MySQL port 3306, user `root`, and an empty password. It refuses to modify a database that already contains tables.

4. Start the local PHP server:

```bash
/c/xamppnew/php/php.exe -S 127.0.0.1:8080
```

5. Open `http://127.0.0.1:8080/login.php` in your browser. Keep Git Bash open while using the application. Press Ctrl+C to stop the PHP server.

Alternatively, put the project in XAMPP's `htdocs/car-rental-portal`, run setup from that folder, start Apache and open `http://localhost/car-rental-portal/login.php`.

### Demo accounts
All three accounts use the demonstration password **Demo2026!**.

| Role | Email |
| --- | --- |
| Customer | customer@example.test |
| Vehicle owner | owner@example.test |
| Controller | admin@example.test |

These are intentionally documented local demo accounts. Use this dataset only in a local demonstration environment.

### Custom database settings
Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` before running setup and the server. For example, in Git Bash:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=nuomosportalas_demo
export DB_USER=root
# Set DB_PASSWORD locally if your MySQL account requires one.
```

For manual phpMyAdmin setup, create an empty database named `nuomosportalas_demo`, import `database.sql`, then run `/c/xamppnew/php/php.exe seed-demo.php` from this repository. The seed refuses to add data to populated tables. Setup and seed scripts reject web requests and can only run from the command line.

## Validation
Validated with an isolated MariaDB instance and PHP HTTP server:
- All PHP files pass syntax checks.
- Database creation, foreign keys and fictional seed records load successfully.
- All three demo accounts sign in and display their dashboards.
- Vehicle details, JSON output and search work.
- A reservation calculates the inclusive price and rejects overlapping bookings.
- Owners can approve and cancel a reservation; a customer with approval can submit a review.
- New account registration works, including a missing birth date.
- Setup refuses an existing populated database and is inaccessible over HTTP.

My application remains an academic prototype. Authorization boundaries, form protection and image-upload validation have not undergone a complete security review; this validation covers local demonstration flows, not public deployment readiness.
