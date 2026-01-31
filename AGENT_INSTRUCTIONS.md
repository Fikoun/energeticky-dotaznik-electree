# EnergyForms Development & Production Environment Fix

## Summary of Issues Found

### Core Problem
The application had inconsistent file paths and configurations between development (npm dev) and production (built files), causing:
- Login returning empty responses or "1" instead of JSON
- Autosave returning HTML (404 pages) instead of JSON
- API endpoints not working in production
- PHP files not being served correctly

### Root Causes Identified

1. **Authentication File Chaos**
   - Multiple `submit-form.php` files (root, public/, dist/) with different purposes
   - Login component calling `./submit-form.php` (simple auth) while form submission called `/public/submit-form.php` (full handler)
   - User ID missing error because wrong endpoint was being used
   - `auth.php` had duplicate code (582 lines of old debug code after clean implementation)
   - Output buffering (`ob_start`/`ob_end_clean`) was suppressing all output including errors

2. **Path Resolution Issues**
   - Dev mode: Vite proxy forwards `/public/*.php` to `localhost:8080/public/*.php` ✅
   - Production: App calls `/public/*.php` but dist folder had no `/public/` subdirectory ❌
   - All API calls returned HTML 404 pages instead of JSON

3. **Missing User Persistence**
   - User data stored only in React state
   - Page refresh in production lost authentication
   - Form submissions failed with "Chybí identifikace uživatele" (Missing user ID)

4. **Vite Proxy Configuration**
   - Only proxied `/api/*` and specific files, not all `.php` files
   - Admin links went to Vite dev server instead of PHP server

## What Was Fixed

### 1. Created Proper Authentication Endpoint (`auth.php`)
- **Purpose**: Handle login/logout ONLY
- **Location**: Root directory (alongside index.html)
- **Key features**:
  - No output buffering (guaranteed JSON output)
  - Comprehensive error handling with try-catch
  - Explicit `exit(0)` after every response
  - Error suppression only on safe operations (`@session_start()`)
  - JSON output on ALL code paths including fatal errors

### 2. Separated Concerns
- `auth.php` → Authentication (login/logout)
- `/public/submit-form.php` → Form submissions and draft saves
- `/public/get-user-forms.php` → Load user's forms
- `/public/delete-form.php` → Delete forms
- `/public/upload-handler.php` & `immediate-upload.php` → File uploads

### 3. Added User Persistence
**File**: `src/App.jsx`
- Save user to localStorage on login
- Load user from localStorage on app initialization
- Clear user from localStorage on logout
- Prevents user loss on page refresh

### 4. Fixed All API Paths
Changed all React components to use consistent paths:
- Login: `auth.php` (no prefix)
- All other APIs: `/public/*.php` (absolute path)

### 5. Created Post-Build Process
**File**: `post-build.sh`
- Automatically runs after `npm run build`
- Creates `dist/public/` directory
- Copies all PHP API files to correct locations
- Creates `dist/public/uploads/` directory
- Ensures production structure matches what React expects

### 6. Updated Vite Proxy
**File**: `vite.config.js`
- Changed proxy pattern to `^/.*\\.php$` to catch ALL .php files
- Ensures dev environment proxies all PHP requests to localhost:8080

## File Structure

### Development Structure
```
EnergyForms/
├── auth.php              ← Auth endpoint (source)
├── public/
│   ├── submit-form.php   ← Form API
│   ├── get-user-forms.php
│   ├── delete-form.php
│   └── admin-*.php       ← Admin panel files
├── upload-handler.php    ← Upload handlers (root level)
├── immediate-upload.php
├── src/                  ← React app source
└── vite.config.js        ← Dev proxy config
```

### Production Structure (dist/)
```
dist/
├── index.html
├── auth.php              ← Copied from root
├── assets/
│   ├── index-xxx.css
│   └── index-xxx.js
└── public/               ← Created by post-build.sh
    ├── submit-form.php
    ├── get-user-forms.php
    ├── delete-form.php
    ├── upload-handler.php
    ├── immediate-upload.php
    └── uploads/          ← Writable directory
```

## How Dev & Production Work Now

### Development Mode (`npm run dev`)
1. Vite starts on port 3000
2. Vite proxy forwards `*.php` requests to `localhost:8080`
3. PHP dev server runs on port 8080 serving project root
4. All paths resolve correctly through proxy

**⚠️ CRITICAL**: After changing paths to `/public/*.php`, dev mode may break because:
- React calls `/public/submit-form.php`
- Proxy forwards to `localhost:8080/public/submit-form.php`
- This requires the PHP server to serve from project root where `public/` directory exists

**Dev Mode Requirements**:
- PHP server MUST run from project root: `php -S localhost:8080`
- NOT from inside public/: ~~`cd public && php -S localhost:8080`~~
- Vite proxy regex must catch all .php files: `^/.*\\.php$`

### Production Mode (built files)
1. Run `npm run build`
2. Post-build script runs automatically
3. Copies PHP files to `dist/public/`
4. Deploy entire `dist/` folder to server
5. All paths (`/public/*.php`, `auth.php`) work correctly

**Production Structure**: Files are physically at `/public/*.php` in dist/

---

## Instructions for AI Agent: Fixing Similar Issues

### Objective
Fix a React + PHP application where API paths work in dev but not production, causing empty responses or HTML instead of JSON.

### Investigation Steps

1. **Identify the mismatch**:
   ```bash
   # Check what React is calling
   grep -r "fetch.*\.php" src/
   
   # Check what exists in production build
   ls -la dist/
   ls -la dist/public/
   
   # Check Vite proxy config
   cat vite.config.js
   ```

2. **Test endpoints locally**:
   ```bash
   # Start PHP server
   php -S localhost:8080
   
   # Test each endpoint
   curl http://localhost:8080/auth.php
   curl http://localhost:8080/public/submit-form.php
   ```

3. **Check for output buffering issues**:
   - Look for `ob_start()` / `ob_end_clean()` that might suppress errors
   - Search for duplicate code in PHP files
   - Verify JSON headers are set FIRST

### Common Patterns to Fix

#### Pattern 1: Empty PHP Response
**Symptoms**: JSON parse error, "Unexpected EOF", 0-byte response

**Fixes**:
```php
<?php
// Remove output buffering for debugging
// ob_start(); ← REMOVE THIS temporarily

// Set headers IMMEDIATELY
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Wrap EVERYTHING in try-catch
try {
    // ... your code ...
    echo json_encode(['success' => true, 'data' => $result]);
    exit(0); // Always explicit exit
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit(0);
}

// Fallback - should never reach
echo json_encode(['success' => false, 'error' => 'Unknown error']);
exit(0);
```

#### Pattern 2: 404 HTML Instead of JSON
**Symptoms**: React gets HTML response, JSON.parse fails

**Root Cause**: File paths don't match between dev and production

**Fix**:
1. Create matching directory structure in `dist/`
2. Add post-build script to copy files
3. Update package.json: `"build": "vite build && ./post-build.sh"`

#### Pattern 3: User Session Lost on Refresh
**Symptoms**: "Missing user ID" errors after page reload

**Fix in React App.jsx**:
```javascript
// On login - save to localStorage
const handleLogin = (userData) => {
  setUser(userData);
  localStorage.setItem('batteryFormUser', JSON.stringify(userData));
};

// On app load - restore from localStorage
useEffect(() => {
  const savedUser = localStorage.getItem('batteryFormUser');
  if (savedUser) {
    setUser(JSON.parse(savedUser));
  }
}, []);

// On logout - clear localStorage
const handleLogout = () => {
  setUser(null);
  localStorage.removeItem('batteryFormUser');
};
```

#### Pattern 4: Vite Proxy Not Catching All PHP
**Fix vite.config.js**:
```javascript
server: {
  proxy: {
    // Use regex to catch ALL .php files
    '^/.*\\.php$': {
      target: 'http://localhost:8080',
      changeOrigin: true,
      secure: false
    }
  }
}
```

### Post-Build Script Template

```bash
#!/bin/bash
echo "📦 Post-build: Copying backend files..."

# Create necessary directories
mkdir -p dist/public/uploads

# Copy PHP API files to match production paths
cp public/*.php dist/public/ 2>/dev/null || true
cp auth.php dist/

# Copy other necessary files
cp upload-handler.php dist/public/ 2>/dev/null || true
cp immediate-upload.php dist/public/ 2>/dev/null || true

# Set permissions
chmod 755 dist/public/uploads

echo "✅ Post-build complete!"
```

### Caveats & Tips

#### 🚨 CRITICAL: After Fixing Production, Dev Mode May Break

**Symptom**: Production works but `npm run dev` now returns empty responses or 404s.

**Why**: When you change React code to call `/public/*.php` (to match production structure), the Vite dev proxy must forward these to a PHP server that has the `public/` directory.

**Fix**:
1. Ensure PHP server runs from project ROOT:
   ```bash
   # Correct - serves entire project including public/ directory
   cd /path/to/EnergyForms
   php -S localhost:8080
   
   # Wrong - can't access ../public/
   cd public
   php -S localhost:8080
   ```

2. Verify Vite proxy is correct in `vite.config.js`:
   ```javascript
   server: {
     proxy: {
       '^/.*\\.php$': {  // Catches ALL .php files including /public/*.php
         target: 'http://localhost:8080',
         changeOrigin: true,
         secure: false
       }
     }
   }
   ```
Test both environments separately**:
   ```bash
   # Test production build locally
   npm run build
   cd dist
   php -S localhost:8000
   # Visit http://localhost:8000 and test login/autosave
   
   # Test dev mode
   # Terminal 1:
   php -S localhost:8080  # From project ROOT
   # Terminal 2:
   npm run dev
   # Visit http://localhost:3000 and test login/autosave
   ```

2. **Add test endpoints**:
   ```php
   // test-api.php - simplest possible endpoint
   <?php
   header('Content-Type: application/json');
   echo json_encode(['test' => 'ok', 'time' => date('Y-m-d H:i:s')]);
   ```

3  
   # Terminal 3: Test that PHP server has the files
   curl http://localhost:8080/public/submit-form.php  # Should work
   curl http://localhost:8080/auth.php                # Should work
   
   # Terminal 3: Test that Vite proxy forwards correctly
   curl http://localhost:3000/public/submit-form.php  # Should work
   curl http://localhost:3000/auth.php                # Should work
   ```

**If dev mode still breaks after production fixes**:
- The path change `/public/*.php` requires the file to exist at that path in dev
- Either: Keep PHP files in `public/` directory AND run PHP server from root
- Or: Use different API paths for dev vs prod (less ideal, but possible with env variables)

#### ⚠️ Common Pitfalls

1. **Don't use relative paths in React for PHP**
   - ❌ `fetch('./api.php')` - breaks in production
   - ❌ `fetch('api.php')` - inconsistent behavior
   - ✅ `fetch('/api.php')` - works if file is at root
   - ✅ `fetch('/public/api.php')` - works if file is in public/

2. **Output buffering can hide errors**
   - Only use `ob_start()` if you know why
   - Always call `ob_end_clean()` before JSON output
   - Better: don't use it for API endpoints

3. **Session management in PHP**
   - Always check `session_status()` before `session_start()`
   - Use `@session_start()` to suppress warnings
   - Sessions don't work across different domains/ports

4. **Vite base path**
   - `base: './'` in vite.config.js makes paths relative
   - Important for subdirectory deployments
   - Affects how assets are loaded

5. **File permissions on production**
   - Uploads directory needs to be writable (755 or 777)
   - PHP files should be 644
   - Never commit with 777 permissions

#### 💡 Debugging Tips

1. **Add test endpoints**:
   ```php
4. **Verify Vite proxy is working**:
   ```bash
   # With both servers running (PHP on 8080, Vite on 3000)
   
   # Test direct PHP server (should work)
   curl http://localhost:8080/public/submit-form.php
   
   # Test through Vite proxy (should also work)
   curl http://localhost:3000/public/submit-form.php
   
   # If direct works but proxy doesn't, check vite.config.js
   ```

5  // test-api.php - simplest possible endpoint
   <?php
   header('Content-Type: application/json');
   echo json_encode(['test' => 'ok', 'time' => date('Y-m-d H:i:s')]);
   ```

2. **Check what React is actually calling**:
   ```javascript
   // Add logging before fetch
   console.log('Calling API:', endpoint);
   const response = await fetch(endpoint);
   console.log('Response headers:', Object.fromEntries(response.headers));
   const text = await response.text();
   console.log('Response text:', text.substring(0, 200));
6  ```

3. **Use curl to test endpoints directly**:
   ```bash
   # Test GET
   curl -v http://localhost:8080/auth.php
   
   # Test POST with JSON
   curl -X POST http://localhost:8080/auth.php \
     -H "Content-Type: application/json" \
     -d '{"action":"login","username":"admin","password":"admin123"}'
   ```

4. **Check PHP error logs**:
   ```php
   // At top of PHP file
   error_reporting(E_ALL);
   ini_set('log_errors', 1);
   error_log("Debug: Request received - " . json_encode($_POST));
   ```

#### 🎯 Best Practices Going Forward

1. **Consistent directory structure**
   - Keep dev and prod structures identical
   - If React calls `/api/endpoint.php`, ensure it exists at that path in dist/

2. **Automate everything**
   - Post-build scripts prevent manual copy errors
   - Add to CI/CD pipeline

3. **Environment-specific configs**
   - Use .env files for different environments
   - Never hardcode URLs/ports

4. **Test production builds locally**
   ```bash
   npm run build
   cd dist
   php -S localhost:8000
   # Test at http://localhost:8000
   ```
**Test dev mode works BEFORE changing production**
- [ ] Create post-build script for production
- [ ] **Test BOTH dev and production builds after all changes**
- [ ] Verify PHP server runs from correct directory (project root, not public/)
- [ ] Verify upload directories are writable
- [ ] Check that all .php files exit explicitly with exit(0)
- [ ] Remove or fix output buffering in API endpoints
- [ ] **If one environment works and other doesn't, check path consistency**

### Quick Checklist for Similar Projects

- [ ] Check if React fetch paths match actual file locations
- [ ] Ensure PHP files return JSON with proper headers
- [ ] Add error handling in ALL PHP endpoints
- [ ] Implement user persistence (localStorage or session)
- [ ] Configure Vite proxy for development
- [ ] Create post-build script for production
- [ ] Test both dev and production builds
- [ ] Verify upload directories are writable
- [ ] Check that all .php files exit explicitly with exit(0)
- [ ] Remove or fix output buffering in API endpoints

---

## Quick Comm Setup (TWO terminals required)
# Terminal 1 - PHP server from PROJECT ROOT:
cd /path/to/EnergyForms
php -S localhost:8080

# Terminal 2 - Vite dev server:
npm run dev

# Verify dev setup:
curl http://localhost:8080/public/submit-form.php      # Direct PHP test
curl http://localhost:3000/public/submit-form.php      # Through Vite proxy

# Building
npm run build                  # Build + run post-build script
ls -la dist/public/            # Verify structure

# Test Production Build Locally
cd dist
php -S localhost:8000
# Visit http://localhost:8000

# Testing Individual Endpoints
curl http://localhost:8080/auth.php                     # Test auth endpoint
curl http://localhost:8080/public/submit-form.php       # Test API endpoint
curl -X POST http://localhost:8080/auth.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","username":"admin","password":"admin123"}'
# Testing
curl http://localhost:8080/auth.php                     # Test auth endpoint
curl http://localhost:8080/public/submit-form.php       # Test API endpoint

# Deployment
rsync -av dist/ user@server:/var/www/html/             # Deploy all files
```
one environment (usually production) but work in the other (dev).

**Root Causes**: 
1. File path mismatches - React calls `/public/*.php` but production build has no `/public/` directory
2. Auth endpoint had output buffering suppressing errors
3. Vite proxy not configured correctly for dev mode
4. PHP server running from wrong directory (inside public/ instead of project root)

**Solution Approach**:
1. Fix PHP endpoints to guarantee JSON output (remove output buffering, add try-catch)
2. Create matching directory structure in production build
3. Add post-build script to copy PHP files
4. Implement user persistence with localStorage
5. Update Vite proxy to catch all PHP files
6. **CRITICAL**: Ensure PHP dev server runs from project ROOT
7. **CRITICAL**: Test BOTH environments after each change

**Testing Strategy**:
```bash
# Test production build
npm run build && cd dist && php -S localhost:8000
# Visit http://localhost:8000, test all features

# Test dev mode (TWO terminals)
# Terminal 1: php -S localhost:8080 (from project ROOT)
# Terminal 2: npm run dev
# Visit http://localhost:3000, test all features

# Verify with curl
curl http://localhost:8080/public/submit-form.php      # Direct to PHP
curl http://localhost:3000/public/submit-form.php      # Through Vite proxy
```

**Common Trap**: Fixing production often breaks dev mode because path changes require PHP server to run from project root, not from public/ directory.

**Expected Result**: Both `npm run dev` and production build work identically, all APIs return JSON in both environments

## Agent Prompt Summary

**Context**: React + PHP form application with inconsistent dev/production environments.

**Problem**: API endpoints return empty responses or HTML instead of JSON in production but work in dev.

**Root Cause**: File path mismatches - React calls `/public/*.php` but production build has no `/public/` directory. Auth endpoint had output buffering suppressing errors.

**Solution Approach**:
1. Fix PHP endpoints to guarantee JSON output (remove output buffering, add try-catch)
2. Create matching directory structure in production build
3. Add post-build script to copy PHP files
4. Implement user persistence with localStorage
5. Update Vite proxy to catch all PHP files

**Testing**: Verify with curl that endpoints return valid JSON, test production build locally with `php -S`.

**Expected Result**: Both `npm run dev` and production build work identically, all APIs return JSON.
