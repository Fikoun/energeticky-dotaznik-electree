# Production Deployment Checklist

## Files to Deploy from `dist/` folder:

### Required Core Files:
- ✅ index.html (main app entry)
- ✅ assets/ folder (CSS and JS)
- ✅ **auth.php** (authentication endpoint - CRITICAL!)

### Required API Files (must be in same directory as index.html):
- ✅ auth.php - Authentication (login/logout)
- ✅ submit-form.php - Form submissions (in /public/ on production)
- ✅ get-user-forms.php - Load user forms
- ✅ delete-form.php - Delete forms
- ✅ company-lookup.php - Company IČO lookup
- ✅ upload-handler.php - File uploads
- ✅ immediate-upload.php - Immediate file uploads

### Directory Structure on Production:
```
your-web-root/
├── index.html
├── auth.php              ← MUST BE HERE
├── test-api.php          ← Test file to verify PHP works
├── assets/
│   ├── index-xxx.css
│   └── index-xxx.js
└── public/              ← API files  
    ├── submit-form.php
    ├── get-user-forms.php
    ├── delete-form.php
    ├── company-lookup.php
    ├── upload-handler.php
    └── immediate-upload.php
```

## Troubleshooting Empty Response from auth.php:

1. **Test PHP execution:**
   Visit: `https://your-domain.com/test-api.php`
   Should show: `{"test":"ok","time":"2026-01-31 01:30:00"}`

2. **If test-api.php shows source code:**
   - PHP is not enabled on server
   - Add `.htaccess` with: `AddHandler application/x-httpd-php .php`

3. **If test-api.php returns 404:**
   - File not uploaded
   - Check file permissions (should be 644)

4. **If auth.php returns empty (0 bytes):**
   - Check server PHP error logs
   - Fatal error before output
   - Verify PHP version >= 7.4

5. **Check .htaccess exists in dist/ folder**

## Quick Fixes:

### Empty Response Issue:
The "Unexpected EOF" error means auth.php returned 0 bytes. This is usually because:
- File doesn't exist on server
- PHP fatal error (check error logs)
- Wrong file path in deployment

### Verify on Production:
```bash
# SSH into production server
curl -X POST https://your-domain.com/auth.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","username":"admin","password":"admin123"}'
```

Expected response:
```json
{"success":true,"message":"Login successful","user":{"id":1,"name":"Administrator","email":"admin@electree.cz","role":"admin"}}
```
