# Insurance Policy Management System

A comprehensive system for managing insurance policies, claims, and client information.

## Features

- User authentication and authorization (Admin, Agent, User roles)
- Dashboard with key metrics and charts
- Policy management
- Claims tracking
- Client management
- Report generation
- Activity logging

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server with mod_rewrite enabled
- Composer (for PHP dependencies)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/ipmsystem.git
cd ipmsystem
```

2. Create a MySQL database:
```sql
CREATE DATABASE ipmsystem;
```

3. Configure the database connection:
   - Copy `backend/config/database.example.php` to `backend/config/database.php`
   - Update the database credentials in `database.php`

4. Set up the web server:
   - Point your web server's document root to the `frontend` directory
   - Ensure the `backend` directory is accessible via API
   - Enable mod_rewrite for Apache

5. Set environment variables:
   - Create a `.env` file in the root directory
   - Add the following variables:
     ```
     DB_HOST=localhost
     DB_NAME=ipmsystem
     DB_USER=your_username
     DB_PASS=your_password
     JWT_SECRET=your_secret_key
     ```

6. Install dependencies:
```bash
composer install
```

7. Initialize the database:
   - The system will automatically create necessary tables and a default admin user
   - Default admin credentials:
     - Email: admin@zamsure.com
     - Password: admin123

## Directory Structure

```
ipmsystem/
├── frontend/           # Frontend files
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   └── index.html     # Main entry point
├── backend/           # Backend files
│   ├── api/           # API endpoints
│   ├── config/        # Configuration files
│   ├── controllers/   # Controller classes
│   ├── helpers/       # Helper classes
│   └── middleware/    # Middleware classes
└── README.md          # This file
```

## API Endpoints

### Authentication
- POST `/api/auth/login` - User login
- POST `/api/auth/register` - User registration
- POST `/api/auth/change-password` - Change password
- GET `/api/auth/validate` - Validate token

### Dashboard
- GET `/api/dashboard/stats` - Get dashboard statistics
- GET `/api/dashboard/activities` - Get recent activities

## Security

- All API endpoints (except login and register) require authentication
- Passwords are hashed using PHP's password_hash()
- JWT tokens are used for authentication
- CORS is enabled for API access
- SQL injection prevention using prepared statements

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details. 
