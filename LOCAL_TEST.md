# Local Package Testing

## Test the package locally before publishing to Packagist

### Method 1: Local Path in composer.json

1. **Add to your Laravel project's `composer.json`:**

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-gemini-bot"
    }
  ],
  "require": {
    "emon/laravel-gemini-bot": "@dev"
  }
}
```

2. **Install:**

```bash
cd /home/emon/Desktop/Personal/bosot-be
composer update emon/laravel-gemini-bot
```

3. **Verify:**

```bash
php artisan list | grep embed
php artisan route:list | grep bot
```

4. **Test:**

```bash
# Should work without any configuration!
php artisan schema:embed
```

---

### Method 2: Symlink

```bash
cd /home/emon/Desktop/Personal/bosot-be/vendor

# Create emon directory if not exists
mkdir -p emon

# Symlink the package
ln -s /home/emon/Desktop/Personal/laravel-gemini-bot emon/laravel-gemini-bot

# Update autoload
cd /home/emon/Desktop/Personal/bosot-be
composer dump-autoload
```

---

### Method 3: Direct Require (After Git Init)

```bash
cd /home/emon/Desktop/Personal/laravel-gemini-bot

# Initialize git
git init
git add .
git commit -m "Initial commit"

# In your Laravel project
cd /home/emon/Desktop/Personal/bosot-be

# Add to composer.json
"repositories": [
    {
        "type": "vcs",
        "url": "/home/emon/Desktop/Personal/laravel-gemini-bot"
    }
]

composer require emon/laravel-gemini-bot:dev-main
```

---

## Quick Test Script

```bash
#!/bin/bash

echo "🧪 Testing Laravel Gemini Bot Package..."
echo ""

cd /home/emon/Desktop/Personal/bosot-be

echo "1️⃣ Checking routes..."
php artisan route:list | grep bot && echo "✅ Routes registered" || echo "❌ Routes not found"

echo ""
echo "2️⃣ Checking commands..."
php artisan list | grep embed && echo "✅ Commands registered" || echo "❌ Commands not found"

echo ""
echo "3️⃣ Checking config..."
php artisan config:show gemini > /dev/null 2>&1 && echo "✅ Config loaded" || echo "❌ Config not found"

echo ""
echo "4️⃣ Testing service injection..."
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$service = \$app->make('Emon\\\\GeminiBot\\\\Services\\\\HybridBotService');
echo \$service ? '✅ Service injectable' : '❌ Service not found';
echo PHP_EOL;
"

echo ""
echo "5️⃣ Testing API endpoint..."
curl -s -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -d '{"query": "test"}' | grep -q "success" && echo "✅ API responding" || echo "❌ API not responding"

echo ""
echo "Done! 🎉"
```

Save as `test_package.sh` and run:

```bash
chmod +x test_package.sh
./test_package.sh
```

---

## Expected Output

### Routes

```
POST     api/bot/ask       gemini-bot.ask         › Emon\GeminiBot\Http\Controllers\BotController@ask
GET      api/bot/history   gemini-bot.history     › Emon\GeminiBot\Http\Controllers\BotController@history
GET      api/bot/stats     gemini-bot.stats       › Emon\GeminiBot\Http\Controllers\BotController@stats
```

### Commands

```
schema:embed   Interactively select and embed database tables with Gemini for AI-powered querying
docs:embed     Embed documentation with Gemini for RAG
```

### Config

```
gemini.api_key ........................ null
gemini.base_url ....................... "https://generativelanguage.googleapis.com/v1beta/"
gemini.embed_model .................... "models/text-embedding-004"
gemini.llm_model ...................... "models/gemini-2.0-flash-exp"
gemini.timeout ........................ 30
gemini.max_retries .................... 3
```

---

## Troubleshooting Local Testing

### Issue: Class not found

```bash
composer dump-autoload
php artisan clear-compiled
php artisan cache:clear
```

### Issue: Routes not registered

```bash
php artisan route:clear
php artisan route:cache
```

### Issue: Config not loaded

```bash
php artisan config:clear
php artisan config:cache
```

### Issue: Provider not loading

Check `composer.json` has:

```json
"extra": {
    "laravel": {
        "providers": [
            "Emon\\GeminiBot\\GeminiBotServiceProvider"
        ]
    }
}
```

---

## What to Test

### ✅ Basic Functionality

- [x] Commands available
- [x] Routes registered
- [x] Middleware works
- [x] Config accessible
- [x] Services injectable

### ✅ Core Features

- [ ] Schema embedding works
- [ ] Documentation embedding works
- [ ] SQL generation works
- [ ] Query validation works
- [ ] Rate limiting works

### ✅ Error Handling

- [ ] Invalid API key handled
- [ ] Rate limit errors handled
- [ ] SQL validation errors handled
- [ ] Missing schema handled

### ✅ Security

- [ ] Read-only DB connection enforced
- [ ] Dangerous SQL blocked
- [ ] Rate limiting active
- [ ] Query logging works

---

## Cleanup After Testing

```bash
cd /home/emon/Desktop/Personal/bosot-be

# Remove from composer.json repositories section
# Then:
composer remove emon/laravel-gemini-bot

# Or if symlinked:
rm -rf vendor/emon/laravel-gemini-bot
composer dump-autoload
```

---

## Ready for Production?

If all tests pass:

1. ✅ Initialize Git repository
2. ✅ Create GitHub repository
3. ✅ Push code
4. ✅ Tag v1.0.0
5. ✅ Submit to Packagist
6. ✅ Announce to community!

---

**Next:** See `PACKAGE_SUMMARY.md` for publishing checklist
