# Installation Guide

## Quick Start (5 Minutes)

### 1. Install Package

```bash
composer require emon/larabot-ai
```

**Laravel will auto-discover the package!** No manual service provider registration needed.

### 2. Publish Assets

```bash
# Publish config
php artisan vendor:publish --tag=larabot-config

# Publish migrations
php artisan vendor:publish --tag=larabot-migrations
```

### 3. Configure Environment

Add to `.env`:

```env
GEMINI_API_KEY=your_api_key_here
GEMINI_EMBED_MODEL=models/text-embedding-004
GEMINI_LLM_MODEL=models/gemini-2.0-flash-exp
```

**Get your free API key:** https://aistudio.google.com/

### 4. Configure Read-Only Database

Add to `config/database.php` in the `connections` array:

```php
'mysql_readonly' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_READONLY_USERNAME', env('DB_USERNAME')),
    'password' => env('DB_READONLY_PASSWORD', env('DB_PASSWORD')),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
],
```

**Optional:** Create dedicated read-only user:

```sql
CREATE USER 'readonly'@'localhost' IDENTIFIED BY 'secure_password';
GRANT SELECT ON your_database.* TO 'readonly'@'localhost';
FLUSH PRIVILEGES;
```

Then in `.env`:
```env
DB_READONLY_USERNAME=readonly
DB_READONLY_PASSWORD=secure_password
```

### 5. Run Migrations

```bash
php artisan migrate
```

This creates 3 tables:
- `schema_embeddings` - Stores database schema vectors
- `knowledge_chunks` - Stores documentation embeddings
- `query_logs` - Tracks all bot queries

### 6. Embed Your Schema

```bash
php artisan schema:embed
```

**What this does:**
- Scans all tables in your database
- Extracts columns, types, relationships
- Generates AI embeddings (768-dimensional vectors)
- Stores for semantic search

**Time:** 2-5 minutes (depends on DB size, API rate limits apply)

**Example Output:**
```
Starting schema embedding process...
Found 25 tables to embed
 25/25 [============================] 100%
✅ Schema embedding completed successfully!
```

### 7. (Optional) Enable Authentication

By default, routes are **publicly accessible**. To require authentication:

```env
# Add to .env
GEMINI_REQUIRE_AUTH=true
GEMINI_AUTH_GUARD=sanctum
```

**Important:** Make sure you have Laravel Sanctum (or your chosen auth) installed:

```bash
# If using Sanctum
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 8. (Optional) Embed Documentation

If you have markdown docs in `/docs` directory:

```bash
php artisan docs:embed
```

### 9. Test It!

```bash
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -d '{"query": "How many users do we have?"}'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "answer": "There are 1,523 users in the system.",
    "intent": "sql",
    "response_time_ms": 1200,
    "sql": "SELECT COUNT(*) as total FROM users;",
    "sources": null
  }
}
```

## 🎉 Done!

The bot is now ready to answer questions about your database!

---

## What Gets Auto-Registered

Thanks to Laravel's package auto-discovery, these are automatically available:

### ✅ Commands
- `php artisan schema:embed` - Embed database schema
- `php artisan docs:embed` - Embed documentation

### ✅ Routes
- `POST /api/bot/ask` - Ask questions
- `GET /api/bot/history` - Query history
- `GET /api/bot/stats` - Statistics

### ✅ Middleware
- `bot.rate-limit` - Rate limiting (10 req/min default)

### ✅ Services (Auto-Injected)
- `Emon\LarabotAi\Services\HybridBotService`
- `Emon\LarabotAi\Services\GeminiService`
- `Emon\LarabotAi\Services\SqlGenerationService`
- `Emon\LarabotAi\Services\SchemaRetrievalService`
- `Emon\LarabotAi\Services\RagService`

---

## Verify Installation

### Check Routes
```bash
php artisan route:list | grep bot
```

Expected output:
```
POST     api/bot/ask       gemini-bot.ask
GET      api/bot/history   gemini-bot.history
GET      api/bot/stats     gemini-bot.stats
```

### Check Commands
```bash
php artisan list | grep embed
```

Expected output:
```
schema:embed   Embed database schema with Gemini
docs:embed     Embed documentation with Gemini
```

### Check Config
```bash
php artisan config:show gemini
```

Should show your Gemini configuration.

---

## Troubleshooting

### Issue: Routes not found

**Solution:** Clear cache
```bash
php artisan route:clear
php artisan cache:clear
```

### Issue: Commands not found

**Solution:** Run
```bash
composer dump-autoload
php artisan clear-compiled
```

### Issue: Gemini API 403 Error

**Solution:** Remove API key restrictions at https://aistudio.google.com/

### Issue: 429 Rate Limit

**Solution:** Free tier has limits:
- 60 requests/minute for embeddings
- 15 requests/minute for text generation

Wait or upgrade to paid tier.

### Issue: Schema not embedded

**Symptoms:** "No relevant tables found" errors

**Solution:**
```bash
php artisan schema:embed
```

---

## Advanced Configuration

### Custom API Endpoint

In `config/gemini.php`:

```php
'base_url' => env('GEMINI_BASE_URL', 'https://your-proxy.com/api/'),
```

### Different Models

```php
'embed_model' => env('GEMINI_EMBED_MODEL', 'models/text-embedding-004'),
'llm_model' => env('GEMINI_LLM_MODEL', 'models/gemini-pro'),
```

### Timeout

```php
'timeout' => env('GEMINI_TIMEOUT', 60), // 60 seconds
```

---

## Next Steps

1. ✅ Read the [README](README.md) for usage examples
2. ✅ Check query logs: `DB::table('query_logs')->latest()->get()`
3. ✅ Test with your specific queries
4. ✅ Set up read-only DB user (production)
5. ✅ Configure rate limits for your needs

---

## Uninstallation

```bash
# Remove package
composer remove emon/larabot-ai

# Drop tables (optional)
php artisan migrate:rollback --path=database/migrations/*_schema_embeddings_table.php
php artisan migrate:rollback --path=database/migrations/*_knowledge_chunks_table.php
php artisan migrate:rollback --path=database/migrations/*_query_logs_table.php
```

---

**Need help?** Open an issue on GitHub!

