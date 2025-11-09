# LaraBot AI

[![Latest Version](https://img.shields.io/packagist/v/emon/larabot-ai.svg?style=flat-square)](https://packagist.org/packages/emon/larabot-ai)
[![Total Downloads](https://img.shields.io/packagist/dt/emon/larabot-ai.svg?style=flat-square)](https://packagist.org/packages/emon/larabot-ai)
[![License](https://img.shields.io/github/license/MdIshtiaque/larabot-ai.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/emon/larabot-ai?style=flat-square)](composer.json)

Conversational AI database assistant for Laravel that combines **Schema-RAG** and **NL→SQL** capabilities using Google Gemini AI.

## 🚀 Features

- 🤖 **Natural Language to SQL** - Ask questions about your database in plain English
- 📚 **Documentation RAG** - Retrieval Augmented Generation from your project docs
- 🔍 **Semantic Schema Search** - AI embeddings for intelligent table discovery
- 🔐 **Secure by Design** - Read-only DB connection + SQL injection prevention
- ⚡ **Auto-Discovery** - Automatically learns your database structure
- 📊 **Query Logging** - Track all queries with performance metrics
- 🛡️ **Rate Limiting** - Built-in protection against abuse
- 🎯 **Column-Aware** - Matches queries to specific columns
- 🔗 **Relationship Discovery** - Automatically follows foreign keys

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- MySQL 5.7+ / MariaDB 10.3+
- Google Gemini API key ([Get free key](https://aistudio.google.com/))

## 📦 Installation

### Step 1: Install via Composer

```bash
composer require emon/larabot-ai
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=larabot-config
```

### Step 3: Publish Migrations

```bash
php artisan vendor:publish --tag=larabot-migrations
```

### Step 4: Configure Environment

Add to your `.env` file:

```env
GEMINI_API_KEY=your_api_key_here
GEMINI_EMBED_MODEL=models/text-embedding-004
GEMINI_LLM_MODEL=models/gemini-2.0-flash-exp
```

Get your free API key from [Google AI Studio](https://aistudio.google.com/).

### Step 5: Add Read-Only Database Connection

Add this to `config/database.php`:

```php
'mysql_readonly' => [
    'driver' => 'mysql',
    'url' => env('DB_URL'),
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

**Security Tip:** Create a read-only MySQL user:

```sql
CREATE USER 'readonly'@'localhost' IDENTIFIED BY 'password';
GRANT SELECT ON your_database.* TO 'readonly'@'localhost';
FLUSH PRIVILEGES;
```

Then add to `.env`:
```env
DB_READONLY_USERNAME=readonly
DB_READONLY_PASSWORD=password
```

### Step 6: Run Migrations

```bash
php artisan migrate
```

### Step 7: Embed Your Database Schema

```bash
php artisan schema:embed
```

This command will:
- Discover all tables in your database
- Extract column information and relationships
- Generate AI embeddings for semantic search
- Store everything for lightning-fast queries

**Note:** This may take 2-5 minutes depending on database size due to API rate limits.

### Step 8 (Optional): Embed Documentation

If you have markdown documentation in a `docs/` directory:

```bash
php artisan docs:embed
```

## 🎯 Usage

### API Endpoints

The package automatically registers these routes:

```
POST   /api/bot/ask      - Ask a question
GET    /api/bot/history  - Get query history (requires auth)
GET    /api/bot/stats    - Get statistics
```

### Ask Questions

```bash
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -d '{"query": "How many users are active?"}'
```

**Response:**

```json
{
  "success": true,
  "data": {
    "answer": "There are 150 active users in the system.",
    "intent": "sql",
    "response_time_ms": 1250,
    "sql": "SELECT COUNT(*) FROM users WHERE is_active = 1;",
    "sources": null
  },
  "error": null
}
```

### Example Queries

**SQL Queries:**
```
- "How many orders were placed today?"
- "Show me the top 10 products by sales"
- "List all users who joined this month"
- "What's the average order value?"
- "Find customers with more than 5 orders"
```

**Documentation Queries:**
```
- "How do I set up authentication?"
- "Explain the payment flow"
- "What is the API rate limit?"
```

### Programmatic Usage

```php
use Emon\LarabotAi\Services\HybridBotService;

class MyController
{
    public function __construct(private HybridBotService $bot) {}

    public function askQuestion(Request $request)
    {
        $result = $this->bot->ask(
            query: $request->input('question'),
            userId: auth()->id()
        );

        return response()->json($result);
    }
}
```

## ⚙️ Configuration

### Authentication

By default, bot routes are **publicly accessible**. To require authentication:

**Option 1: Environment Variables (Recommended)**

Add to `.env`:

```env
GEMINI_REQUIRE_AUTH=true
GEMINI_AUTH_GUARD=sanctum  # or 'api', 'web'
```

**Option 2: Config File**

Edit `config/gemini.php`:

```php
'require_auth' => true,
'auth_guard' => 'sanctum', // or 'api', 'web', 'passport'
```

**Option 3: Custom Middleware (Advanced)**

Edit `config/gemini.php` and customize the middleware array:

```php
'route_middleware' => [
    'api',
    'auth:sanctum',           // Add authentication
    'bot.rate-limit',
    'verified',               // Add email verification
    'throttle:60,1',          // Additional rate limiting
],
```

**Testing Authenticated Requests:**

```bash
# With Sanctum token
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{"query": "How many users?"}'
```

**Common Auth Guards:**
- `sanctum` - Laravel Sanctum (SPA/mobile apps)
- `api` - Token-based authentication
- `web` - Session-based authentication
- `passport` - Laravel Passport OAuth2

### Rate Limiting

Default: 10 requests per minute per user/IP

To customize, modify `src/Http/Middleware/BotRateLimitMiddleware.php`:

```php
$executed = RateLimiter::attempt("bot-query:{$userId}", 20, fn () => true);
```

### Query Length

Default: 500 characters max

Change in middleware:

```php
if (strlen($query) > 1000) { // Increased to 1000
    // ...
}
```

### Gemini Models

In `config/gemini.php`:

```php
'embed_model' => env('GEMINI_EMBED_MODEL', 'models/text-embedding-004'),
'llm_model' => env('GEMINI_LLM_MODEL', 'models/gemini-2.0-flash-exp'),
```

## 🔒 Security Features

✅ **Read-Only Database** - Queries execute on separate read-only connection  
✅ **SQL Injection Prevention** - Blocks dangerous SQL patterns  
✅ **No Mutations** - DROP, DELETE, UPDATE, INSERT automatically blocked  
✅ **Optional Authentication** - Support for Sanctum, Passport, and custom guards  
✅ **Rate Limiting** - Prevents API abuse (10 req/min default)  
✅ **Query Validation** - Validates generated SQL before execution  
✅ **Audit Logging** - All queries logged with user ID and timestamps

## 🧪 How It Works

### Architecture

```
User Query → Intent Detection → Hybrid Bot Service
                                       ↓
                         ┌─────────────┴─────────────┐
                         ↓                           ↓
                   SQL Intent                    RAG Intent
                         ↓                           ↓
              Schema Retrieval              Knowledge Retrieval
            (Semantic Search + FKs)       (Document Embeddings)
                         ↓                           ↓
               SQL Generation                  Context Assembly
            (Gemini LLM + Rules)                     ↓
                         ↓                    Answer Generation
                   SQL Validation                 (Gemini LLM)
                         ↓                           ↓
                  Execute Query                      │
                         ↓                           │
                         └───────────┬───────────────┘
                                     ↓
                              Format Response
                                     ↓
                              Query Logging
                                     ↓
                            Return to User
```

### Key Components

1. **Schema Embeddings** - Vector representations of your database tables
2. **Semantic Search** - Finds relevant tables using AI similarity matching
3. **Relationship Discovery** - Automatically includes related tables via foreign keys
4. **Column-Aware Matching** - Matches query terms to specific columns
5. **SQL Generation** - Gemini LLM generates optimized SQL queries
6. **SQL Validation** - Multi-layer security checks before execution

## 📊 Query Logging

All queries are logged to `query_logs` table:

```php
DB::table('query_logs')
    ->where('user_id', auth()->id())
    ->orderBy('created_at', 'desc')
    ->get();
```

Fields: `query`, `intent`, `generated_sql`, `retrieved_tables`, `response_time_ms`, `success`, `error_message`

## 🔧 Maintenance

### Re-embed Schema After Changes

Run this after migrations or schema changes:

```bash
php artisan schema:embed
```

### Update Documentation

After updating docs:

```bash
php artisan docs:embed
```

## 🐛 Troubleshooting

### "403 Forbidden" from Gemini API

**Solution:** Remove API key restrictions in [Google AI Studio](https://aistudio.google.com/).

### "429 Too Many Requests"

**Solution:** Hitting free tier quota limit. Wait or upgrade plan.

### "Table X is not in allowed list"

**Solution:** Schema not embedded. Run `php artisan schema:embed`

### SQL Validation Errors

**Solution:** Generated SQL contains dangerous operations or syntax errors. Check query logs.

## 📚 Documentation

- [Installation Guide](INSTALLATION_GUIDE.md) - Step-by-step setup
- [Authentication Guide](AUTHENTICATION.md) - Secure your bot routes
- [Package Summary](PACKAGE_SUMMARY.md) - Technical architecture
- [API Reference](docs/API.md) - Coming soon

## 🤝 Contributing

Contributions welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new features
4. Submit a pull request

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

## 🙏 Credits

Built with:
- [Laravel](https://laravel.com/)
- [Google Gemini AI](https://deepmind.google/technologies/gemini/)
- [GuzzleHTTP](https://docs.guzzlephp.org/)

## 💬 Support

- **Issues:** [GitHub Issues](https://github.com/MdIshtiaque/larabot-ai/issues)
- **Discussions:** [GitHub Discussions](https://github.com/MdIshtiaque/larabot-ai/discussions)

## 🌟 Star History

If this package helped you, please star it on GitHub! ⭐

---

**Made with ❤️ for the Laravel community**

