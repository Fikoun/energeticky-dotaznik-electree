#!/bin/bash

echo "================================="
echo "Upload Folder Permissions Check"
echo "================================="
echo ""

# Define paths
PROJECT_DIR="$(pwd)"
PUBLIC_DIR="$PROJECT_DIR/public"
UPLOADS_DIR="$PUBLIC_DIR/uploads"

echo "Project directory: $PROJECT_DIR"
echo "Uploads directory: $UPLOADS_DIR"
echo ""

# Check if uploads directory exists
if [ -d "$UPLOADS_DIR" ]; then
    echo "✅ Uploads directory EXISTS"
else
    echo "❌ Uploads directory DOES NOT EXIST"
    echo ""
    echo "To create it, run:"
    echo "  mkdir -p $UPLOADS_DIR"
    exit 1
fi

echo ""
echo "=== Current Permissions ==="
ls -la "$UPLOADS_DIR" | head -1
ls -ld "$UPLOADS_DIR"

echo ""
echo "=== Directory Owner/Group ==="
stat -c "Owner: %U (UID: %u)" "$UPLOADS_DIR" 2>/dev/null || stat -f "Owner: %Su (UID: %u)" "$UPLOADS_DIR"
stat -c "Group: %G (GID: %g)" "$UPLOADS_DIR" 2>/dev/null || stat -f "Group: %Sg (GID: %g)" "$UPLOADS_DIR"

echo ""
echo "=== Web Server User ==="
WEB_USER=$(ps aux | grep -E 'apache|httpd|nginx|www-data|php-fpm' | grep -v grep | head -1 | awk '{print $1}')
if [ -n "$WEB_USER" ]; then
    echo "Detected web server process user: $WEB_USER"
else
    echo "Could not detect web server user"
    echo "Common users: www-data, apache, nginx, nobody"
fi

echo ""
echo "=== Current PHP User ==="
php -r "echo 'PHP runs as: ' . get_current_user() . PHP_EOL;"
if command -v php-fpm &> /dev/null; then
    echo "PHP-FPM user: $(ps aux | grep php-fpm | grep -v grep | head -1 | awk '{print $1}')"
fi

echo ""
echo "=== Recommended Fix ==="
echo ""
echo "Option 1: Set permissions to 775 (recommended)"
echo "  chmod 775 $UPLOADS_DIR"
echo ""
echo "Option 2: Set permissions to 777 (less secure, but guaranteed to work)"
echo "  chmod 777 $UPLOADS_DIR"
echo ""
echo "Option 3: Change owner to web server user (may require sudo)"
echo "  sudo chown -R www-data:www-data $UPLOADS_DIR"
echo "  chmod 755 $UPLOADS_DIR"
echo ""
echo "=== Quick Fix Command ==="
echo "Run this to fix immediately:"
echo ""
echo "  chmod 777 $UPLOADS_DIR && echo 'Done! Permissions set to 777'"
echo ""
