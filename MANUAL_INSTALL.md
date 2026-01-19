# Manual Installation Instructions

If the automated installer is having permission issues, you can manually configure the database.

## Step 1: Edit the Database Config File

Open `config/database.php` in your editor and update these values:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password (empty for XAMPP default)
define('DB_NAME', 'travelplanner'); // Your database name
```

## Step 2: Create the Database

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database called `travelplanner` (or whatever name you used above)
3. Import the `database.sql` file:
   - Click on your database
   - Go to "Import" tab
   - Choose file: `database.sql`
   - Click "Go"

## Step 3: Create Admin User (Optional)

You can create an admin user directly in the database or register through the application.

To create via SQL in phpMyAdmin:

```sql
INSERT INTO users (email, password_hash, first_name, last_name) 
VALUES ('admin@example.com', '$2y$10$YourHashedPasswordHere', 'Admin', 'User');
```

Or just register through the application's registration page.

## Step 4: Test Installation

Visit: http://localhost/travelplanner/

You should be able to login or register.
