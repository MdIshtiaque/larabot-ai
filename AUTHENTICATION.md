# Authentication Guide

## 🔐 Overview

By default, Laravel Gemini Bot routes are **publicly accessible**. This guide shows you how to secure them with authentication.

---

## Quick Setup (2 Minutes)

### 1. Enable Authentication

Add to `.env`:

```env
GEMINI_REQUIRE_AUTH=true
GEMINI_AUTH_GUARD=sanctum
```

### 2. Restart Server

```bash
php artisan config:clear
php artisan route:clear
```

### 3. Test

```bash
# This will now return 401 Unauthorized
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -d '{"query": "How many users?"}'
```

---

## Authentication Methods

### Option 1: Laravel Sanctum (Recommended)

**Best for:** SPAs, mobile apps, simple token authentication

**Setup:**

```bash
# Install Sanctum
composer require laravel/sanctum

# Publish config
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run migrations
php artisan migrate
```

**Configure:**

```env
GEMINI_REQUIRE_AUTH=true
GEMINI_AUTH_GUARD=sanctum
```

**Create Token:**

```php
// In your AuthController or similar
$user = User::find(1);
$token = $user->createToken('bot-access')->plainTextToken;

// Return token to user
return response()->json(['token' => $token]);
```

**Make Authenticated Request:**

```bash
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{"query": "How many active users?"}'
```

---

### Option 2: Laravel Passport (OAuth2)

**Best for:** Third-party API access, complex OAuth flows

**Setup:**

```bash
# Install Passport
composer require laravel/passport

# Run migrations
php artisan migrate

# Install Passport
php artisan passport:install
```

**Configure:**

```env
GEMINI_REQUIRE_AUTH=true
GEMINI_AUTH_GUARD=passport
```

**Usage:** Follow [Laravel Passport documentation](https://laravel.com/docs/passport)

---

### Option 3: Session-Based (Web Guard)

**Best for:** Same-domain web applications

**Configure:**

```env
GEMINI_REQUIRE_AUTH=true
GEMINI_AUTH_GUARD=web
```

**Usage:** Users must be logged in via Laravel's standard web authentication.

---

### Option 4: Custom API Token

**Best for:** Simple custom token systems

**Configure:**

```env
GEMINI_REQUIRE_AUTH=true
GEMINI_AUTH_GUARD=api
```

**Setup:** Configure `api` guard in `config/auth.php`

---

## Advanced Configuration

### Per-Route Authentication

If you want different authentication rules for different routes, edit `config/gemini.php`:

```php
'route_middleware' => [
    'api',
    // Leave auth out here
],
```

Then in your `routes/api.php`, override the routes:

```php
// Public route
Route::post('api/bot/ask', [BotController::class, 'ask'])
    ->middleware(['api', 'bot.rate-limit']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('api/bot/history', [BotController::class, 'history']);
    Route::get('api/bot/stats', [BotController::class, 'stats']);
});
```

---

### Multiple Middleware

Add additional middleware in `config/gemini.php`:

```php
'route_middleware' => [
    'api',
    'auth:sanctum',
    'verified',              // Require email verification
    'bot.rate-limit',
    'throttle:60,1',        // Additional throttling
    'role:admin',           // Require admin role (if using Spatie)
],
```

---

### Custom Authorization Logic

Create a custom middleware for fine-grained control:

**1. Create Middleware:**

```bash
php artisan make:middleware BotAccessMiddleware
```

**2. Implement Logic:**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BotAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Check if user has bot access permission
        if (!$user || !$user->hasPermission('bot-access')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access the bot.',
            ], 403);
        }

        return $next($request);
    }
}
```

**3. Register Middleware:**

In `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ...
    'bot.access' => \App\Http\Middleware\BotAccessMiddleware::class,
];
```

**4. Use in Config:**

```php
// config/gemini.php
'route_middleware' => [
    'api',
    'auth:sanctum',
    'bot.access',           // Your custom middleware
    'bot.rate-limit',
],
```

---

## User Identification

The bot automatically tracks `user_id` in query logs. This works with any auth system:

```php
// In BotController
$userId = $request->user()?->id;  // Gets authenticated user ID
$result = $this->botService->ask($request->input('query'), $userId);
```

**Query logs will include:**
- `user_id` - Who made the query
- `query` - What they asked
- `intent` - How it was handled
- `success` - If it succeeded
- `created_at` - When it happened

---

## Frontend Integration Examples

### JavaScript (Sanctum)

```javascript
// Login and get token
const loginResponse = await fetch('/api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
});
const { token } = await loginResponse.json();

// Store token
localStorage.setItem('bot_token', token);

// Make bot request
const askBot = async (query) => {
    const token = localStorage.getItem('bot_token');
    
    const response = await fetch('/api/bot/ask', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ query })
    });
    
    return await response.json();
};

// Usage
const result = await askBot('How many users do we have?');
console.log(result.data.answer);
```

---

### Vue.js (Axios + Sanctum)

```vue
<template>
  <div>
    <input v-model="query" placeholder="Ask a question..." />
    <button @click="askBot">Ask</button>
    <div v-if="answer">{{ answer }}</div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      query: '',
      answer: null
    };
  },
  methods: {
    async askBot() {
      try {
        const response = await axios.post('/api/bot/ask', {
          query: this.query
        }, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        });
        
        this.answer = response.data.data.answer;
      } catch (error) {
        console.error('Bot error:', error.response?.data);
      }
    }
  }
};
</script>
```

---

### React (fetch + Sanctum)

```jsx
import React, { useState } from 'react';

function BotWidget() {
  const [query, setQuery] = useState('');
  const [answer, setAnswer] = useState(null);
  const [loading, setLoading] = useState(false);

  const askBot = async () => {
    setLoading(true);
    try {
      const token = localStorage.getItem('bot_token');
      
      const response = await fetch('/api/bot/ask', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ query })
      });
      
      const data = await response.json();
      
      if (data.success) {
        setAnswer(data.data.answer);
      } else {
        console.error('Bot error:', data.error);
      }
    } catch (error) {
      console.error('Request failed:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <input 
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder="Ask a question..."
      />
      <button onClick={askBot} disabled={loading}>
        {loading ? 'Asking...' : 'Ask'}
      </button>
      {answer && <div className="answer">{answer}</div>}
    </div>
  );
}

export default BotWidget;
```

---

## Security Best Practices

### 1. Always Use HTTPS in Production

```env
# .env
APP_URL=https://yourdomain.com
```

### 2. Rotate Tokens Regularly

```php
// Revoke old tokens
$user->tokens()->delete();

// Create new token
$token = $user->createToken('bot-access')->plainTextToken;
```

### 3. Token Expiration (Sanctum)

In `config/sanctum.php`:

```php
'expiration' => 60 * 24, // 24 hours
```

### 4. Limit Token Abilities

```php
$token = $user->createToken('bot-access', ['bot:ask'])->plainTextToken;
```

Then in middleware:

```php
if (!$request->user()->tokenCan('bot:ask')) {
    abort(403);
}
```

### 5. IP Whitelisting (Optional)

Create middleware to restrict by IP:

```php
public function handle(Request $request, Closure $next)
{
    $allowedIps = ['192.168.1.1', '10.0.0.1'];
    
    if (!in_array($request->ip(), $allowedIps)) {
        abort(403, 'IP not allowed');
    }
    
    return $next($request);
}
```

---

## Troubleshooting

### Issue: 401 Unauthorized

**Solution:** Check if token is being sent correctly:

```bash
# Verify token in request
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -v  # Verbose mode to see headers
```

### Issue: 419 CSRF Token Mismatch

**Solution:** Sanctum routes need CSRF exemption. Add to `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'api/*',
];
```

### Issue: Token Not Working

**Solution:** Clear config cache:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### Issue: CORS Errors

**Solution:** Configure CORS in `config/cors.php`:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'supports_credentials' => true,
```

---

## Testing

### Test Public Access (Default)

```bash
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -d '{"query": "How many users?"}'
```

### Test Protected Access

```bash
# Without token (should fail)
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -d '{"query": "How many users?"}'

# Expected: 401 Unauthorized

# With token (should succeed)
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"query": "How many users?"}'

# Expected: 200 OK with answer
```

---

## FAQ

**Q: Do I need authentication?**
A: It depends. For internal tools or prototypes, public access is fine. For production with sensitive data, enable authentication.

**Q: Which auth method should I use?**
A: Sanctum for most modern apps, Passport for OAuth2, Web guard for same-domain web apps.

**Q: Can I have some routes public and others protected?**
A: Yes! Set `require_auth` to `false` and manually add auth middleware to specific routes.

**Q: How do I track anonymous users?**
A: The bot uses IP address for rate limiting when no user is authenticated.

**Q: Can I use custom authentication?**
A: Yes! Create a custom guard in `config/auth.php` and use it in `GEMINI_AUTH_GUARD`.

---

## Summary

✅ **Default:** Public access (good for development)  
✅ **Production:** Enable authentication with `GEMINI_REQUIRE_AUTH=true`  
✅ **Flexible:** Supports Sanctum, Passport, Web, API, and custom guards  
✅ **Secure:** All queries logged with user ID for audit trail  

For more details, see:
- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [Laravel Passport Documentation](https://laravel.com/docs/passport)
- [Laravel Authentication Documentation](https://laravel.com/docs/authentication)

