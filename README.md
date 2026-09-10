# SecureBank

SecureBank is a PHP and MariaDB online banking portal built as a security-focused demonstration application. It includes account registration, login, optional multi-factor authentication, account balances, transaction history, and account-to-account transfers.

## Features

- User registration and login
- Bcrypt password hashing
- Session-based authentication
- CSRF protection for forms
- Optional MFA using email or SMS OTP codes
- OTP expiry and failed-attempt limits
- Account balance and transaction history views
- Transfers between SecureBank accounts
- Prepared SQL statements through PDO
- PHPMailer SMTP integration for email OTP delivery
- Telerivet integration for SMS OTP delivery

## Requirements

- PHP 8.0 or newer
- MariaDB or MySQL
- Apache, such as the Apache server included with XAMPP
- PHP extensions: `pdo_mysql`, `curl`, and `openssl`
- Composer
- SMTP credentials for email MFA
- Telerivet credentials for SMS MFA

## Installation

1. Place the project in your web server directory. For XAMPP on Windows:

   ```text
   C:\xampp\htdocs\web_security_app
   ```

2. Start Apache and MySQL from the XAMPP Control Panel.

3. Create the database and import the schema:

   ```sql
   CREATE DATABASE securebank_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```

   Then import `database.sql` into `securebank_db` using phpMyAdmin or the MariaDB client.

4. Install the PHP dependency:

   ```bash
   composer install
   ```

5. Create the local environment file from the sample:

   ```text
   Copy .env.sample to .env
   ```

6. Edit `.env` and provide your database, email, and Telerivet values:

   ```dotenv
   DB_HOST=localhost
   DB_USER=your_database_user
   DB_PASS=your_database_password
   DB_NAME=securebank_db

   TELERIVET_API_KEY=your_telerivet_api_key
   TELERIVET_PROJECT_ID=your_telerivet_project_id
   TELERIVET_PHONE_ID=your_telerivet_phone_id

   MAIL_USERNAME=your_email_address
   MAIL_PASSWORD=your_email_password
   ```

7. Open the application:

   ```text
   http://localhost/web_security_app/
   ```

## MFA Configuration

MFA is configured from the Profile page after creating an account. Email MFA uses Gmail SMTP on port `587` with STARTTLS. SMS MFA requires a valid Telerivet project, phone ID, and API key.

For Gmail, use an app password when two-step verification is enabled. Do not place real credentials in `.env.sample`, source control, or screenshots.

## Project Structure

| File | Purpose |
| --- | --- |
| `index.php` | Login and registration page |
| `auth.php` | Registration, login, session, and user helpers |
| `config.php` | Session configuration and database connection |
| `security.php` | Input handling, CSRF, and access-control helpers |
| `mfa.php` | OTP generation, verification, email, and SMS delivery |
| `verify_otp.php` | MFA verification page |
| `dashboard.php` | Account overview and recent transactions |
| `profile.php` | Profile and MFA settings |
| `transfer.php` | Account-to-account transfers |
| `transaction_history.php` | Full transaction history |
| `database.sql` | MariaDB schema and development seed data |
| `css/style.css` | Application styles |
| `.env.sample` | Environment variable template |

## Testing Telerivet

After configuring `.env`, use the development helper to test the Telerivet connection:

```text
http://localhost/web_security_app/test_telerivet.php
```

Remove or restrict access to this helper before deploying publicly.

## Security Notes

- Keep `.env` private and never commit it.
- Use HTTPS outside local development.
- Replace the sample database records before using the application with real data.
- Review and remove development utilities such as `test_telerivet.php` before production deployment.
- Use strong, unique database and mail credentials.
- The included `database.sql` contains demo user and transaction records for development only.

## License

No license has been specified for this project.
