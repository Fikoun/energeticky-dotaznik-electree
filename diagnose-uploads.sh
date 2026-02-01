#!/bin/bash

echo "======================================"
echo "Deep Diagnostics - Upload Permissions"
echo "======================================"
echo ""

PROJECT_DIR="$(pwd)"
PUBLIC_DIR="$PROJECT_DIR/public"
UPLOADS_DIR="$PUBLIC_DIR/uploads"

echo "Checking: $UPLOADS_DIR"
echo ""

# 1. Check directory exists
echo "=== 1. Directory Existence ==="
if [ -d "$UPLOADS_DIR" ]; then
    echo "✅ EXISTS"
else
    echo "❌ DOES NOT EXIST - Creating now..."
    mkdir -p "$UPLOADS_DIR"
    chmod 777 "$UPLOADS_DIR"
    echo "Created with 777 permissions"
fi
echo ""

# 2. Check permissions
echo "=== 2. Current Permissions ==="
ls -ld "$UPLOADS_DIR"
PERMS=$(stat -c "%a" "$UPLOADS_DIR" 2>/dev/null || stat -f "%A" "$UPLOADS_DIR" 2>/dev/null)
echo "Octal: $PERMS"
echo ""

# 3. Check parent directory permissions
echo "=== 3. Parent Directory Permissions ==="
ls -ld "$PUBLIC_DIR"
PARENT_PERMS=$(stat -c "%a" "$PUBLIC_DIR" 2>/dev/null || stat -f "%A" "$PUBLIC_DIR" 2>/dev/null)
echo "Octal: $PARENT_PERMS"
if [ "$PARENT_PERMS" -lt "755" ]; then
    echo "⚠️  Parent directory may need execute permission!"
fi
echo ""

# 4. Test actual write
echo "=== 4. Write Test ==="
TEST_FILE="$UPLOADS_DIR/test_$(date +%s).txt"
if echo "test" > "$TEST_FILE" 2>/dev/null; then
    echo "✅ Shell can write files"
    rm -f "$TEST_FILE"
else
    echo "❌ Shell CANNOT write files"
fi
echo ""

# 5. Check disk space
echo "=== 5. Disk Space ==="
df -h "$UPLOADS_DIR" | tail -1
echo ""

# 6. Check SELinux (if available)
echo "=== 6. SELinux Status ==="
if command -v getenforce &> /dev/null; then
    SELINUX_STATUS=$(getenforce 2>/dev/null)
    echo "SELinux: $SELINUX_STATUS"
    if [ "$SELINUX_STATUS" = "Enforcing" ]; then
        echo "⚠️  SELinux is enforcing - this may block PHP writes"
        echo "To fix: sudo chcon -R -t httpd_sys_rw_content_t $UPLOADS_DIR"
    fi
else
    echo "SELinux: Not installed"
fi
echo ""

# 7. Check inode
echo "=== 7. Inode Information ==="
df -i "$UPLOADS_DIR" | tail -1
echo ""

# 8. Check if immutable
echo "=== 8. Immutable Flag Check ==="
if command -v lsattr &> /dev/null; then
    ATTR=$(lsattr -d "$UPLOADS_DIR" 2>/dev/null | awk '{print $1}')
    echo "Attributes: $ATTR"
    if [[ "$ATTR" == *"i"* ]]; then
        echo "⚠️  IMMUTABLE flag is set! Remove with: chattr -i $UPLOADS_DIR"
    else
        echo "✅ No immutable flag"
    fi
else
    echo "lsattr not available"
fi
echo ""

# 9. PHP write test
echo "=== 9. PHP Write Test ==="
PHP_TEST="$PUBLIC_DIR/test_write_$(date +%s).php"
cat > "$PHP_TEST" << 'PHPEOF'
<?php
$uploadDir = __DIR__ . '/uploads/';
$testFile = $uploadDir . 'php_test_' . time() . '.txt';

echo "Upload dir: $uploadDir\n";
echo "Is dir: " . (is_dir($uploadDir) ? 'YES' : 'NO') . "\n";
echo "Is writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "\n";
echo "Current user: " . get_current_user() . "\n";
echo "Process user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown') . "\n";

if (@file_put_contents($testFile, 'test')) {
    echo "✅ PHP CAN write to uploads folder!\n";
    unlink($testFile);
} else {
    echo "❌ PHP CANNOT write to uploads folder\n";
    echo "Last error: " . error_get_last()['message'] . "\n";
}
PHPEOF

php "$PHP_TEST"
rm -f "$PHP_TEST"
echo ""

# 10. Recommendations
echo "======================================"
echo "=== SOLUTIONS TO TRY ==="
echo "======================================"
echo ""
echo "1. Fix ownership (run as root/sudo):"
echo "   sudo chown -R \$(stat -c '%U' $PUBLIC_DIR):\$(stat -c '%G' $PUBLIC_DIR) $UPLOADS_DIR"
echo ""
echo "2. Fix permissions recursively:"
echo "   chmod -R 777 $UPLOADS_DIR"
echo "   chmod 755 $PUBLIC_DIR"
echo ""
echo "3. If SELinux is enabled:"
echo "   sudo chcon -R -t httpd_sys_rw_content_t $UPLOADS_DIR"
echo ""
echo "4. If immutable flag is set:"
echo "   sudo chattr -R -i $UPLOADS_DIR"
echo ""
echo "5. Move uploads OUTSIDE public_html:"
echo "   Create: $PROJECT_DIR/../uploads/"
echo "   Then update PHP to use: dirname(__DIR__) . '/uploads/'"
echo ""
echo "6. Check with hosting provider:"
echo "   - ISPConfig/cPanel restrictions"
echo "   - open_basedir limitations"
echo "   - suexec/mod_ruid2 settings"
echo ""
