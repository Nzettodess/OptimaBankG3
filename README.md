# OptimaBankG3 Setup Guide

This is a PHP + MySQL application running under **XAMPP**, with support for **Google OAuth Login**.

## 📂 Repository Placement

Clone the repository into your XAMPP `htdocs` directory:

```bash
cd C:/Xampp/htdocs/
git clone <your-repo-url> OptimaBankG3
```

The project should be located at:

```
C:/Xampp/htdocs/OptimaBankG3/
```

## 🗄️ Database Setup

1. Start XAMPP (Apache + MySQL).
2. Open phpMyAdmin at `http://localhost/phpmyadmin`.
3. Create a database:

```sql
CREATE DATABASE optimabank;
```

4. Import the SQL schema:
   - Navigate to `OptimaBankG3/SQL/`.
   - Import the `.sql` file into the `optimabank` database.
   
This will create all required tables, including the updated `users` schema.

## 📦 Install Dependencies (Google API + Guzzle)

The project uses Google API Client and Guzzle. To install the correct versions:

```bash
cd E:/Xampp/htdocs/OptimaBankG3
composer require google/apiclient:^2.15 -W
composer require guzzlehttp/guzzle:^7.0 -W
```

**Note**: Ensure Composer is installed and available in your system PATH.

## ▶️ Running the Application

1. Start Apache and MySQL in XAMPP.
2. Open the application in your browser:

```
http://localhost/OptimaBankG3/home.php
```
