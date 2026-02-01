#!/bin/bash

# Setup script for creating uploads directory with proper permissions
# Run this on production server in the project root directory

echo "Setting up uploads directory for EnergyForms..."

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Define paths
PUBLIC_DIR="$SCRIPT_DIR/public"
UPLOADS_DIR="$PUBLIC_DIR/uploads"

# Create uploads directory
echo "Creating directory: $UPLOADS_DIR"
mkdir -p "$UPLOADS_DIR"

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to create uploads directory"
    exit 1
fi

# Set permissions (775 allows web server to write)
echo "Setting permissions to 775..."
chmod 775 "$UPLOADS_DIR"

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to set permissions"
    exit 1
fi

# Try to set ownership (this might require sudo)
echo "Attempting to set ownership..."
# Get current web server user (common values: www-data, apache, nginx, nobody)
WEB_USER=$(ps aux | grep -E 'apache|httpd|nginx|www-data' | grep -v grep | head -1 | awk '{print $1}')

if [ -n "$WEB_USER" ]; then
    echo "Detected web server user: $WEB_USER"
    echo "Setting owner to $WEB_USER (may require sudo)..."
    chown -R "$WEB_USER:$WEB_USER" "$UPLOADS_DIR" 2>/dev/null
    
    if [ $? -ne 0 ]; then
        echo "WARNING: Could not change ownership. Try running with sudo:"
        echo "  sudo chown -R $WEB_USER:$WEB_USER $UPLOADS_DIR"
    else
        echo "Ownership set successfully"
    fi
else
    echo "WARNING: Could not detect web server user"
    echo "You may need to manually set ownership:"
    echo "  sudo chown -R www-data:www-data $UPLOADS_DIR"
fi

# Verify the directory is writable
if [ -w "$UPLOADS_DIR" ]; then
    echo ""
    echo "✅ SUCCESS! Uploads directory is ready:"
    echo "   Path: $UPLOADS_DIR"
    echo "   Permissions: $(stat -c '%a' "$UPLOADS_DIR" 2>/dev/null || stat -f '%A' "$UPLOADS_DIR" 2>/dev/null)"
    echo "   Owner: $(stat -c '%U:%G' "$UPLOADS_DIR" 2>/dev/null || stat -f '%Su:%Sg' "$UPLOADS_DIR" 2>/dev/null)"
else
    echo ""
    echo "⚠️  WARNING: Directory created but may not be writable by web server"
    echo "   You may need to adjust permissions manually"
fi

echo ""
echo "Done!"
