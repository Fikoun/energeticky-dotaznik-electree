#!/bin/bash
# Post-build script for EnergyForms
# 
# IMPORTANT: Vite copies public/ folder contents to dist/ ROOT.
# But React code calls /public/*.php, so we need to create dist/public/ folder
# with the PHP files for production to match what React expects.

set -e  # Exit on error
echo "📦 Post-build: Configuring dist/ for Apache deployment..."

# ============================================
# Create required directories
# ============================================
mkdir -p dist/public/uploads
mkdir -p dist/config
mkdir -p dist/includes/Raynet
mkdir -p dist/uploads

# ============================================
# Root-level PHP files
# ============================================
cp auth.php dist/
cp company-lookup.php dist/ 2>/dev/null || true

# ============================================
# CRITICAL: Create dist/public/ with PHP files
# React code calls /public/*.php so we need this structure
# ============================================
echo "  Creating dist/public/ structure for /public/*.php API calls..."

# Copy all PHP files from source public/ to dist/public/
cp public/*.php dist/public/
cp public/UserActivityTracker.php dist/public/ 2>/dev/null || true

# Upload handlers need to be in dist/public/ (React calls /public/immediate-upload.php)
cp immediate-upload.php dist/public/
cp upload-handler.php dist/public/

# ============================================
# Configuration files
# ============================================
cp config/database.php dist/config/
cp config/raynet.php dist/config/ 2>/dev/null || true

# ============================================
# Include files (for PHP require statements)
# ============================================
cp includes/*.php dist/includes/ 2>/dev/null || true
cp -r includes/Raynet/* dist/includes/Raynet/ 2>/dev/null || true

# ============================================
# Apache .htaccess for SPA routing
# ============================================
cat > dist/.htaccess << 'HTACCESS'
# Enable rewrite engine
RewriteEngine On

# Handle CORS for API requests
<IfModule mod_headers.c>
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
</IfModule>

# Handle preflight OPTIONS requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]

# Don't rewrite existing files (PHP, images, CSS, JS, etc.)
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# SPA routing - all other requests go to index.html
RewriteRule ^ index.html [L]

# PHP settings
<IfModule mod_php.c>
    php_flag display_errors off
    php_flag log_errors on
    php_value upload_max_filesize 50M
    php_value post_max_size 55M
    php_value max_execution_time 120
</IfModule>

# Protect sensitive files
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

# Protect config directory from web access
<DirectoryMatch "config">
    <Files "*.php">
        Require all denied
    </Files>
</DirectoryMatch>

# Enable gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css application/json application/javascript text/xml application/xml
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
HTACCESS

# ============================================
# Set proper permissions
# ============================================
chmod 755 dist/uploads
chmod 755 dist/public/uploads
chmod 644 dist/.htaccess

echo "✅ Post-build complete!"
echo ""
echo "📁 Production structure (dist/):"
echo "   ├── index.html          (React SPA)"
echo "   ├── auth.php            (login API)"
echo "   ├── company-lookup.php  (IČO lookup)"
echo "   ├── .htaccess           (Apache config)"
echo "   ├── assets/             (Vite-built CSS/JS)"
echo "   ├── config/"
echo "   │   └── database.php"
echo "   ├── includes/"
echo "   ├── uploads/"
echo "   └── public/             (API endpoints)"
echo "       ├── submit-form.php"
echo "       ├── get-user-forms.php"
echo "       ├── delete-form.php"
echo "       ├── immediate-upload.php"
echo "       ├── admin-*.php"
echo "       └── uploads/"
echo ""
echo "🚀 Ready for Apache deployment!"
echo ""
echo "📝 Apache VirtualHost requirements:"
echo "   - DocumentRoot pointing to dist/"
echo "   - mod_rewrite enabled"
echo "   - mod_headers enabled"
echo "   - AllowOverride All"
