# Travel Planner Application

A mobile-first personal travel planner built with PHP and MySQL to organize all your travel information including flights, trains, hotels, documents, and screenshots in a beautiful timeline view.

## Features

- **User Authentication**: Secure registration and login system
- **Trip Management**: Create, edit, and delete trips with dates and descriptions
- **Travel Items**: Add flights, trains, buses, hotels, car rentals, activities, and more
- **Timeline View**: Chronological display of all travel items
- **Document Management**: Upload and view screenshots, PDFs, and images
- **Search & Filter**: Search trips and items, filter by date range
- **Export**: Export trips as CSV or PDF
- **Mobile-First Design**: Optimized for mobile devices with touch interactions

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server (XAMPP recommended)
- mod_rewrite enabled (optional, for clean URLs)

## Installation

1. **Database Setup**
   - Open phpMyAdmin or MySQL command line
   - Create a database named `travelplanner` (or update `config/database.php` with your preferred name)
   - Run the SQL from `database.sql` or visit `install.php` in your browser

2. **Configuration**
   - Edit `config/database.php` and update database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'travelplanner');
     ```

3. **File Permissions**
   - Ensure the `uploads/` directory is writable:
     ```bash
     chmod 755 uploads/
     ```

4. **Access the Application**
   - Navigate to `http://localhost/travelplanner/` in your browser
   - Register a new account or login

## File Structure

```
travelplanner/
├── api/              # API endpoints for CRUD operations
├── assets/           # CSS and JavaScript files
├── config/           # Configuration files
├── includes/         # Header, footer, and auth helpers
├── pages/            # Main application pages
├── uploads/          # Uploaded documents (auto-created)
├── database.sql      # Database schema
├── install.php       # Database installation script
└── index.php         # Entry point
```

## Usage

### Creating a Trip
1. Login to your account
2. Click "Add New Trip" or use the bottom navigation
3. Fill in trip details (title, dates, description)
4. Click "Create Trip"

### Adding Travel Items
1. Open a trip from the dashboard
2. Click "+ Add Item" in the Timeline section
3. Select item type (flight, train, hotel, etc.)
4. Fill in details including dates, location, confirmation numbers
5. Add documents/screenshots if needed

### Uploading Documents
- Upload documents to a specific travel item or to the trip itself
- Supported formats: JPEG, PNG, GIF, WebP, PDF
- Maximum file size: 10MB

### Exporting Trips
- On the trip detail page, click "Export CSV" or "Export PDF"
- CSV includes all trip data in spreadsheet format
- PDF provides a printable summary

## Security Features

- Password hashing with bcrypt
- SQL injection prevention (prepared statements)
- XSS prevention (htmlspecialchars)
- File upload validation
- Session-based authentication
- Secure file serving

## Mobile Optimization

- Touch-friendly buttons (minimum 44x44px)
- Swipe gestures for navigation
- Responsive design for all screen sizes
- Bottom navigation for mobile devices
- Optimized timeline view for small screens

## Troubleshooting

### Database Connection Error
- Check database credentials in `config/database.php`
- Ensure MySQL is running
- Verify database exists

### File Upload Issues
- Check `uploads/` directory permissions
- Verify PHP upload settings in php.ini
- Check file size limits

### Session Issues
- Ensure PHP sessions are enabled
- Check session directory permissions

## Development

The application uses:
- **Backend**: PHP with PDO
- **Database**: MySQL with InnoDB
- **Frontend**: Vanilla JavaScript, CSS3
- **Architecture**: MVC-inspired structure

## License

This is a personal project. Feel free to use and modify as needed.


