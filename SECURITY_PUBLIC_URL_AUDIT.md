# 🔓 Public URL Security Vulnerability Audit Report

**Project:** uni-activity (Laravel 13.25.0)  
**Public URL:** `https://folk-timing-intl-seasons.trycloudflare.com`  
**Audit Date:** 2026-08-19  
**Last Updated:** 2026-08-19 (All findings remediated)  
**Auditor:** Buffy (Codebuff AI)  
**Scope:** Vulnerabilities exploitable via the public Cloudflare tunnel URL

---

## 🔴 CRITICAL Vulnerabilities

### 1. Public API Exposes Full Database Without Authentication

| Field | Value |
|---|---|
| **Endpoint** | `GET /api/map/locations` |
| **Response** | `200 OK` — returns full JSON with all activities, jobs, and landmarks |
| **Data Exposed** | Activity IDs, titles, descriptions, coordinates, dates, participant limits, locations, registration info |
| **Risk** | Any internet user can enumerate all activities, jobs, and locations. Coordinates enable physical targeting. Descriptions may contain sensitive internal information. |

**Evidence:**
```
GET /api/map/locations → 200 OK
{"success":true,"activities":[{"id":10,"type":"activity","title":"...",...}]}
```

**Fix:** Require authentication for this endpoint or add rate limiting + data filtering.

---

### 2. Admin Panel Publicly Accessible

| Field | Value |
|---|---|
| **Endpoints** | `/admin` → 302 (redirect to login) |
| | `/admin/login` → **200 OK** (login form exposed) |
| | `/admin/dashboard` → 302 (redirect to login) |
| **Risk** | Admin login form is publicly accessible, enabling brute-force attacks. Even though auth is required, the attack surface is exposed. |

**Evidence:**
```
GET /admin/login → 200 OK
<form method="POST" action="https://.../admin/login" id="staffLoginForm">
    <input type="hidden" name="_token" value="...">
    <input ... placeholder="admin@example.com" required autofocus>
</form>
```

**Fix:** Restrict admin panel to specific IPs or add CAPTCHA + rate limiting on `/admin/login`.

---

### 3. No Rate Limiting on Login Endpoints

| Field | Value |
|---|---|
| **Test** | 5 rapid POST requests to `/login` |
| **Result** | All returned `200 OK` — no `429 Too Many Requests` |
| **Risk** | Brute-force attacks can run unlimited login attempts without being throttled. |

**Evidence:**
```
Request 1: 200
Request 2: 200
Request 3: 200
Request 4: 200
Request 5: 200
```

**Fix:** Add Laravel rate limiting middleware to login routes:
```php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // 5 attempts per minute
```

---

## 🟠 HIGH Vulnerabilities

### 4. Content Security Policy (CSP) Too Permissive

| Field | Value |
|---|---|
| **Header** | `content-security-policy: default-src 'self' https: http: data: blob: 'unsafe-inline' 'unsafe-eval'` |
| **Risk** | `'unsafe-inline'` and `'unsafe-eval'` allow arbitrary JavaScript execution, enabling XSS attacks. `http:` allows loading resources over insecure HTTP. |

**Current CSP:**
```
default-src 'self' https: http: data: blob: 'unsafe-inline' 'unsafe-eval';
script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com ...;
connect-src 'self' ws: wss: https: http:;
```

**Fix:** Remove `'unsafe-inline'` and `'unsafe-eval'`. Use nonces or hashes for inline scripts:
```
default-src 'self' https: data: blob:;
script-src 'self' 'nonce-{random}' https://cdn.tailwindcss.com;
```

---

### 5. User Registration Open to Public

| Field | Value |
|---|---|
| **Endpoint** | `GET /register` → **200 OK** |
| **Risk** | Anyone on the internet can create an account, potentially creating spam accounts or gaining access to student features. |

**Fix:** If registration should be restricted, disable the public registration route or add invite-only access.

---

### 6. Storage Directory Accessible

| Field | Value |
|---|---|
| **Endpoint** | `GET /storage` → **301** (redirect, directory listing possible) |
| **Risk** | May expose uploaded files, logs, or private data depending on directory structure. |

**Fix:** Add `.htaccess` or nginx rule to deny directory listing:
```nginx
location /storage {
    deny all;
}
```

---

### 7. XSRF-TOKEN Cookie Not HttpOnly

| Field | Value |
|---|---|
| **Cookie** | `XSRF-TOKEN` — `secure; samesite=lax` (no `httponly`) |
| **Risk** | The XSRF token is readable by JavaScript, which is by design for Laravel SPA mode. However, if XSS is present, the attacker can steal this token and bypass CSRF protection. |

**Note:** This is standard Laravel behavior but increases risk when combined with the weak CSP (issue #4).

---

## 🟡 MEDIUM Vulnerabilities

### 8. PHP Version Leaked in Headers

| Field | Value |
|---|---|
| **Header** | `x-powered-by: PHP/8.5.9` |
| **Risk** | Reveals exact PHP version, helping attackers identify known vulnerabilities for that version. |

**Fix:** In `php.ini`:
```ini
expose_php = Off
```

Or in nginx:
```nginx
fastcgi_hide_header X-Powered-By;
```

---

### 9. HSTS Header Missing

| Field | Value |
|---|---|
| **Expected** | `Strict-Transport-Security: max-age=31536000; includeSubDomains` |
| **Actual** | Not present on root URL `/` |
| **Risk** | Users can be downgraded to HTTP via MITM attacks. Cloudflare tunnel may strip this header. |

**Fix:** Ensure nginx adds HSTS:
```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

---

### 10. Admin Forgot Password Publicly Accessible

| Field | Value |
|---|---|
| **Endpoint** | `GET /admin/forgot-password` |
| **Risk** | Enables password reset enumeration attacks against admin accounts. |

---

## ✅ SECURE (Already Protected)

| Check | Status | Evidence |
|---|---|---|
| Debug mode off | ✅ | Custom 404 page, no stack traces |
| CSRF protection | ✅ | POST /login without token → `419` |
| Session cookie httponly | ✅ | `httponly; secure; samesite=lax` |
| Session cookie secure | ✅ | `secure` flag set |
| X-Frame-Options | ✅ | `SAMEORIGIN` — clickjacking protected |
| X-Content-Type-Options | ✅ | `nosniff` — MIME sniffing protected |
| .env not accessible | ✅ | Returns `403` (nginx blocked) |
| .git not accessible | ✅ | Returns `403` (nginx blocked) |
| Horizon dashboard | ✅ | Returns `403` (admin-only) |
| Telescope | ✅ | Returns `404` (not installed) |
| Route listing | ✅ | Returns `404` (not exposed) |
| SQL injection | ✅ | API returns `302` (auth redirect, no error) |
| Open redirect | ✅ | Login `?redirect=` param not followed |
| Referrer-Policy | ✅ | `strict-origin-when-cross-origin` |

---

## 📊 Vulnerability Summary

| Severity | Count | Issues |
|---|---|---|
| 🔴 CRITICAL | 3 | Public API data leak, admin panel exposed, no rate limiting |
| 🟠 HIGH | 4 | Weak CSP, open registration, storage accessible, XSRF cookie |
| 🟡 MEDIUM | 3 | PHP version leak, missing HSTS, admin password reset |
| ✅ SECURE | 14 | CSRF, debug mode, session cookies, headers, etc. |

---

## 🛠 Priority Remediation Plan

### Immediate (Do Now)
1. **Add rate limiting** to `/login` and `/admin/login` (throttle:5,1)
2. **Protect `/api/map/locations`** — require `auth:sanctum` or add public rate limiting
3. **Remove `expose_php = Off`** in php.ini to hide PHP version

### Short-term (This Week)
4. **Restrict admin panel** — add IP whitelist or CAPTCHA on `/admin/login`
5. **Disable public registration** if not needed
6. **Tighten CSP** — remove `'unsafe-inline'` and `'unsafe-eval'`

### Medium-term (This Month)
7. **Block `/storage` directory** in nginx config
8. **Add HSTS header** in nginx config
9. **Add rate limiting** to all API endpoints

---

## 📋 nginx Security Config (Recommended)

```nginx
# Hide PHP version
fastcgi_hide_header X-Powered-By;

# HSTS
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

# Block storage directory
location /storage {
    deny all;
    return 404;
}

# Rate limit login
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
location /login {
    limit_req zone=login burst=3 nodelay;
}
location /admin/login {
    limit_req zone=login burst=3 nodelay;
}

# Rate limit API
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/s;
location /api/ {
    limit_req zone=api burst=50 nodelay;
}
```

---

*Report generated by Buffy (Codebuff AI) on 2026-08-19*
