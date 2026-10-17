# Car Rental Portal

University web application by Paulina (@paulina-22), built with PHP, MySQL, HTML, CSS and JavaScript. The interface is in Lithuanian.

## Features implemented in the source
- Registration and sign-in with hashed passwords.
- Customer, vehicle owner and controller roles.
- Vehicle listings, image uploads and vehicle details.
- Date-based reservations, approval and cancellation.
- Reviews and ratings.

## Requirements
PHP with PDO MySQL support and a compatible MySQL/MariaDB database. XAMPP can provide the local Apache, PHP and database services.

## Local setup
1. Copy this repository into your XAMPP `htdocs/car-rental-portal` directory.
2. Restore the original `nuomosportalas` database separately. No database export was found with this source copy, so this repository is not yet a complete, ready-to-run distribution.
3. Configure database access using the environment variables `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASSWORD`. The defaults target a local XAMPP development setup.
4. Create a writable `uploads` directory locally for new vehicle images.
5. Start Apache and MySQL, then open `http://localhost/car-rental-portal/login.php`.

## Preparation and validation
A broken, partially commented-out birth-date handler was removed to restore PHP syntax. A misspelled `vehicle_id` column reference was corrected. All included PHP files pass PHP syntax checks. Full application behavior has not been verified against the original database.

Original uploaded photos, database records, archives and the diagnostic `phpinfo` page are excluded. This is an academic prototype; authorization, form protection and upload handling need a fuller review before deployment to a public server.