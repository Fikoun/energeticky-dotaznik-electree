#!/bin/bash
# Post-build script to copy PHP files to dist/

echo "📦 Post-build: Copying PHP API files to dist/..."

# Create public directory
mkdir -p dist/public/uploads

# Copy PHP API files
cp public/submit-form.php dist/public/
cp public/get-user-forms.php dist/public/
cp public/delete-form.php dist/public/
cp upload-handler.php dist/public/
cp immediate-upload.php dist/public/

# Copy auth.php to root
cp auth.php dist/

# Set proper permissions for uploads
chmod 755 dist/public/uploads

echo "✅ Post-build complete!"
echo "   - auth.php → dist/auth.php"
echo "   - PHP APIs → dist/public/*.php"
echo "   - Uploads → dist/public/uploads/"
