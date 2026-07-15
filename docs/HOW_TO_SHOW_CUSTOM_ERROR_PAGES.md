# 🎨 How to Display Custom Error Pages

## The Problem

When `APP_DEBUG=true`, Laravel shows the debug page (Ignition) instead of your beautiful custom error pages.

## ✅ Solution: 3 Methods

---

## Method 1: Disable Debug Mode (Recommended for Testing)

### Quick Fix
Edit `.env` file:

```env
# Change this:
APP_DEBUG=true

# To this:
APP_DEBUG=false
```

Then clear cache:
```bash
php artisan config:clear
php artisan view:clear
```

**Result:** Your custom error pages will now display!

### ⚠️ Important
- Set `APP_DEBUG=false` to see custom error pages
- Set `APP_DEBUG=true` for development debugging
- Always use `APP_DEBUG=false` in production

---

## Method 2: Force Custom Pages Even in Debug Mode

If you want to see custom error pages while keeping `APP_DEBUG=true`, update `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    // Force custom error pages even in debug mode
    $exceptions->respond(function (Response $response) {
        if ($response->getStatusCode() === 404) {
            return response()->view('errors.404', [], 404);
        }
        
        if ($response->getStatusCode() === 403) {
            return response()->view('errors.403', [], 403);
        }
        
        if ($response->getStatusCode() === 500) {
            return response()->view('errors.500', [], 500);
        }
        
        if ($response->getStatusCode() === 503) {
            return response()->view('errors.503', [], 503);
        }
        
        if ($response->getStatusCode() === 419) {
            return response()->view('errors.419', [], 419);
        }

        return $response;
    });
})
```

---

## Method 3: Environment-Based Configuration

Best for development teams - show debug in local, custom pages in staging/production.

### Update `.env`:
```env
# For development (shows debug page)
APP_ENV=local
APP_DEBUG=true

# For testing custom pages (shows custom error pages)
APP_ENV=staging
APP_DEBUG=false

# For production (shows custom error pages)
APP_ENV=production
APP_DEBUG=false
```

---

## 🧪 Testing Your Custom Error Pages

### Option 1: Use Test Routes (Easiest)

Visit the test dashboard:
```
http://localhost:8000/test-errors
```

Click any error code to see your custom page!

### Option 2: Temporarily Disable Debug

1. Edit `.env`:
   ```env
   APP_DEBUG=false
   ```

2. Clear cache:
   ```bash
   php artisan config:clear
   ```

3. Test by visiting:
   ```
   http://localhost:8000/non-existent-page  (404)
   http://localhost:8000/admin/dashboard    (403 if not logged in)
   ```

4. Re-enable debug when done:
   ```env
   APP_DEBUG=true
   ```

### Option 3: Use Artisan Command

```bash
# Trigger 404 error
php artisan route:list | grep "non-existent"

# Or create a test route temporarily
```

---

## 📝 Step-by-Step Guide

### To See Your Custom Error Pages:

1. **Open `.env` file**
   ```
   E:\projects\uni-activity\.env
   ```

2. **Find this line:**
   ```env
   APP_DEBUG=true
   ```

3. **Change to:**
   ```env
   APP_DEBUG=false
   ```

4. **Save the file**

5. **Clear Laravel cache:**
   ```bash
   php artisan config:clear
   php artisan view:clear
   ```

6. **Test it:**
   - Visit: http://localhost:8000/test-errors
   - Or visit any non-existent page: http://localhost:8000/xyz

7. **You should now see your beautiful custom error pages!**

---

## 🎯 Quick Commands

### See Custom Error Pages
```bash
# 1. Disable debug
# Edit .env: APP_DEBUG=false

# 2. Clear cache
php artisan config:clear

# 3. Test
# Visit: http://localhost:8000/test-errors
```

### Return to Debug Mode
```bash
# 1. Enable debug
# Edit .env: APP_DEBUG=true

# 2. Clear cache
php artisan config:clear
```

---

## 🔍 Verification

### Check Current Configuration
```bash
php artisan config:show app.debug
```

### Check if Custom Pages Exist
```bash
dir resources\views\errors\
```

You should see:
- 403.blade.php
- 404.blade.php
- 419.blade.php
- 500.blade.php
- 503.blade.php
- database.blade.php

---

## ⚙️ Advanced Configuration

### Show Debug Info on Custom Pages

If you want custom pages but still show debug info, edit your error pages:

```blade
@if(config('app.debug') && isset($exception))
    <div class="debug-info">
        <h3>Debug Information:</h3>
        <pre>{{ $exception->getMessage() }}</pre>
        <pre>{{ $exception->getTraceAsString() }}</pre>
    </div>
@endif
```

This is already included in the 500.blade.php page!

---

## 🚀 Production Checklist

Before deploying to production:

- [ ] Set `APP_DEBUG=false` in production .env
- [ ] Set `APP_ENV=production`
- [ ] Clear all caches
- [ ] Test all error pages
- [ ] Verify no debug info shows
- [ ] Remove test routes (already disabled in production)

---

## 🎨 Your Custom Error Pages

You have these beautiful pages ready:

| Error | File | When Shown |
|-------|------|------------|
| 404 | 404.blade.php | Page not found |
| 403 | 403.blade.php | No permission |
| 419 | 419.blade.php | Session expired |
| 500 | 500.blade.php | Server error |
| 503 | 503.blade.php | Maintenance mode |
| DB | database.blade.php | Database error |

---

## 💡 Pro Tips

1. **Development:** Keep `APP_DEBUG=true` for debugging
2. **Testing Error Pages:** Temporarily set `APP_DEBUG=false`
3. **Production:** Always `APP_DEBUG=false`
4. **Use Test Routes:** Visit `/test-errors` to preview all pages
5. **Clear Cache:** Always run `php artisan config:clear` after changing .env

---

## 🆘 Troubleshooting

### Still Seeing Debug Page?

1. **Check .env:**
   ```env
   APP_DEBUG=false  # Must be false
   ```

2. **Clear cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

3. **Verify config:**
   ```bash
   php artisan config:show app.debug
   ```
   Should show: `false`

4. **Hard refresh browser:**
   - Chrome/Edge: Ctrl + Shift + R
   - Firefox: Ctrl + F5

### Custom Pages Not Found?

1. **Check files exist:**
   ```bash
   dir resources\views\errors\
   ```

2. **Check file names:**
   - Must be: `404.blade.php` (not `404.php`)
   - Must be in: `resources/views/errors/`

3. **Clear view cache:**
   ```bash
   php artisan view:clear
   ```

---

## ✅ Summary

**To see your custom error pages:**

1. Set `APP_DEBUG=false` in `.env`
2. Run `php artisan config:clear`
3. Visit `http://localhost:8000/test-errors`
4. Enjoy your beautiful error pages! 🎉

**To return to debug mode:**

1. Set `APP_DEBUG=true` in `.env`
2. Run `php artisan config:clear`
3. Continue development

---

**That's it! Your custom error pages are ready to shine! ✨**
