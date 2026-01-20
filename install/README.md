# Travel Planner Installer

This installer will help you set up the Travel Planner application quickly and easily.

## How to Use

1. **Access the Installer**
   - Navigate to `http://your-domain/travelplanner/install/` in your web browser
   - Or if running locally: `http://localhost/travelplanner/install/`

2. **Fill in Database Information**
   - Database Host: Usually `localhost` for local development
   - Database Username: Your MySQL username (default: `root` for XAMPP)
   - Database Password: Your MySQL password (leave empty for default XAMPP)
   - Database Name: Name for your database (default: `travelplanner`)

3. **Create Admin Account (Optional)**
   - You can create an admin account during installation
   - Or register later from the application login page

4. **Complete Installation**
   - Click "Install Now" to begin
   - The installer will:
     - Create the database (if it doesn't exist)
     - Set up all required tables
     - Create configuration files
     - Set up collaboration features
     - Create admin account (if provided)

5. **After Installation**
   - You'll be redirected to the application
   - **Security Note**: Consider deleting or renaming the `install` folder after installation

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)
- Write permissions for the `config` directory

## Troubleshooting

### Permission Errors
If you get permission errors:
```bash
chmod 755 config/
chmod 644 config/database.php
```

### Database Connection Errors
- Verify your database credentials
- Ensure MySQL is running
- Check that the database user has CREATE DATABASE privileges

### Already Installed
If the application is already installed, you'll see a message indicating this. You can still access the installer, but it will update existing configuration.

## Manual Installation

If you prefer to install manually:
1. Create the database manually
2. Import `database.sql` and `database_collaboration.sql` via phpMyAdmin
3. Edit `config/database.php` with your credentials
4. Ensure `uploads/` directory is writable
