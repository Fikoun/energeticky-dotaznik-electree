# EnergyForms - Deployment Guide

## Overview

- **Frontend**: React SPA (Vite + TailwindCSS)
- **Backend**: PHP APIs with MySQL database

---

## Development Mode

### Requirements
- Node.js 18+
- PHP 8.0+
- MySQL database (remote: s2.onhost.cz)

### Running Development

**Terminal 1** - PHP Server (from project root):
```bash
cd /path/to/EnergyForms
php -S localhost:8080
```

**Terminal 2** - Vite Dev Server:
```bash
npm run dev
```

**Access**: http://localhost:3000

### How Dev Mode Works
1. Vite runs on port 3000, serving React app
2. Vite proxy forwards `/public/*.php` and `/*.php` to PHP server on port 8080
3. PHP server runs from project root, serving both root-level and `public/` PHP files

---

## Production Build

### Build Command
```bash
npm run build
```

This runs:
1. `vite build` - builds React app to `dist/`
2. `./post-build.sh` - copies PHP files and creates Apache config

### Build Output Structure
```
dist/
├── index.html              # React SPA entry
├── auth.php                # Login API
├── company-lookup.php      # IČO lookup wrapper
├── .htaccess               # Apache config (SPA routing + CORS)
├── assets/
│   ├── index-*.css
│   └── index-*.js
├── config/
│   └── database.php
├── includes/
├── uploads/
└── public/                 # API endpoints (React calls /public/*.php)
    ├── submit-form.php
    ├── get-user-forms.php
    ├── delete-form.php
    ├── immediate-upload.php
    ├── upload-handler.php
    ├── admin-*.php
    └── uploads/
```

---

## Apache Production Deployment

### Server Requirements
- Apache 2.4+
- PHP 8.0+ (mod_php or PHP-FPM)
- MySQL 5.7+ / MariaDB 10.3+
- Required Apache modules: `mod_rewrite`, `mod_headers`

### Deployment Steps

1. **Build the application**:
   ```bash
   npm run build
   ```

2. **Upload to server**:
   ```bash
   rsync -avz dist/ user@server:/var/www/html/energyforms/
   ```

3. **Set permissions**:
   ```bash
   chmod 755 /var/www/html/energyforms/uploads
   chmod 755 /var/www/html/energyforms/public/uploads
   chown -R www-data:www-data /var/www/html/energyforms/uploads
   ```

4. **Apache VirtualHost configuration**:
   ```apache
   <VirtualHost *:80>
       ServerName energyforms.example.com
       DocumentRoot /var/www/html/energyforms
       
       <Directory /var/www/html/energyforms>
           Options -Indexes +FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/energyforms_error.log
       CustomLog ${APACHE_LOG_DIR}/energyforms_access.log combined
   </VirtualHost>
   ```

5. **Enable modules and site**:
   ```bash
   sudo a2enmod rewrite headers
   sudo a2ensite energyforms
   sudo systemctl reload apache2
   ```

---

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/auth.php` | GET/POST | Authentication |
| `/company-lookup.php?ico=XXX` | GET | IČO lookup |
| `/public/submit-form.php` | POST | Save form |
| `/public/get-user-forms.php?userId=X` | GET | Get user's forms |
| `/public/delete-form.php` | POST | Delete form |
| `/public/immediate-upload.php` | POST | File upload |
| `/public/admin-dashboard.php` | GET | Admin panel |

---

## Troubleshooting

### API returns 404 in development
- Ensure PHP server is running from project ROOT: `php -S localhost:8080`
- Check Vite proxy config in `vite.config.js`

### API returns HTML instead of JSON
- Verify Apache `mod_rewrite` is enabled
- Check `.htaccess` is processed (`AllowOverride All`)

### File uploads fail
- Check `uploads/` directory permissions (755)
- Verify PHP upload limits

### Test endpoints
```bash
# Development
curl http://localhost:8080/auth.php
curl http://localhost:8080/public/submit-form.php

# Production
curl https://your-domain.com/auth.php
```

---

## Quick Reference

```bash
# Development (2 terminals)
php -S localhost:8080    # Terminal 1
npm run dev              # Terminal 2
# → http://localhost:3000

# Production Build
npm run build
# → Deploy dist/ folder to Apache

# Test Production Locally
cd dist && php -S localhost:8000
# → http://localhost:8000
```
