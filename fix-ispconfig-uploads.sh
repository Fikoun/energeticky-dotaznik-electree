#!/bin/bash

echo "================================="
echo "ISPConfig Upload Folder Fix"
echo "================================="
echo ""

# Detect ISPConfig paths
CURRENT_DIR="$(pwd)"
echo "Current directory: $CURRENT_DIR"

# Extract web user from path (e.g., web99 from /var/www/clients/client13/web99/...)
if [[ $CURRENT_DIR =~ /var/www/clients/([^/]+)/([^/]+) ]]; then
    CLIENT="${BASH_REMATCH[1]}"
    WEB_USER="${BASH_REMATCH[2]}"
    echo "✅ Detected ISPConfig setup"
    echo "   Client: $CLIENT"
    echo "   Web user: $WEB_USER"
else
    echo "❌ Not in ISPConfig directory structure"
    echo "Please run this from your website directory"
    exit 1
fi

UPLOADS_DIR="$CURRENT_DIR/public/uploads"

echo ""
echo "Uploads directory: $UPLOADS_DIR"
echo ""

# Create directory if not exists
if [ ! -d "$UPLOADS_DIR" ]; then
    echo "Creating uploads directory..."
    mkdir -p "$UPLOADS_DIR"
fi

# Set ownership to website user
echo "Setting ownership to $WEB_USER:$CLIENT..."
chown -R "$WEB_USER:$CLIENT" "$UPLOADS_DIR"

# Set permissions
echo "Setting permissions to 755..."
chmod 755 "$UPLOADS_DIR"

echo ""
echo "=== Verification ==="
ls -ld "$UPLOADS_DIR"

echo ""
echo "=== Testing write permission ==="
TEST_FILE="$UPLOADS_DIR/test_write.txt"
if sudo -u "$WEB_USER" touch "$TEST_FILE" 2>/dev/null; then
    echo "✅ $WEB_USER can write to uploads folder!"
    sudo -u "$WEB_USER" rm -f "$TEST_FILE"
else
    echo "❌ $WEB_USER cannot write to uploads folder"
    echo ""
    echo "This script needs to be run with sudo:"
    echo "  sudo ./fix-ispconfig-uploads.sh"
fi

echo ""
echo "Done!"
