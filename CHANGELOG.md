# Changelog

All notable changes to LaraBot AI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.2.0] - 2025-11-10

### 🎨 Added - HTML Visualizations

- **Intelligent HTML/CSS visualization generation** - AI automatically creates beautiful visualizations
- **Multiple visualization types**:
  - `stats_card` - Gradient cards for single values (counts, sums, averages)
  - `table` - Beautiful HTML tables for 5-20 rows of data
  - `bar_chart` - CSS-based horizontal bar charts for categories
  - `list` - Styled lists for 2-5 items with details
  - `comparison` - Side-by-side comparison cards
  - `metric_grid` - Dashboard-style metric grids
  - `timeline` - Vertical timelines for date-ordered events
  - `text` - Plain text responses when no visualization is needed
- **AI-powered insights extraction** - Key findings automatically highlighted
- **Smart detection** - AI decides when visualization adds value
- **Natural language answer mandatory** - Every response includes clear explanation
- **Self-contained HTML** - No external dependencies, ready to render
- **Modern design** - Gradients, shadows, emojis, responsive layouts

### 📝 Response Structure Changes

- Added `html` field - Ready-to-render HTML with inline CSS
- Added `visualization_type` field - Type of visualization generated
- Added `insights` array - Key insights extracted from data
- All changes are backward compatible

### 📚 Documentation

- Added `HTML_VISUALIZATION_FEATURE.md` - Complete feature guide (22 pages)
- Added `HTML_VISUALIZATION_EXAMPLES.md` - Real-world response examples
- Added `WHATS_NEW_HTML_VISUALIZATIONS.md` - Quick start guide
- Updated `README.md` with visualization examples and integration guide

### 🔧 Technical Changes

- Enhanced `HybridBotService::formatSqlResult()` - Now generates HTML + NL answer
- Added `HybridBotService::analyzeDataStructure()` - Intelligent data analysis
- Added `HybridBotService::detectColumnType()` - Column type detection
- Added `HybridBotService::parseFormattedResponse()` - AI response parsing
- Updated `BotController::ask()` - Returns visualization fields
- Increased Gemini token limit to 4096 for complex HTML generation

### ✨ Improvements

- Better data structure analysis
- Smarter column type detection (datetime, numeric, string, mixed)
- Enhanced AI prompts with visualization guidelines
- Professional CSS styling with modern design patterns

### 🔒 Security

- HTML is AI-generated (not user input) - safe to render
- No JavaScript execution - inline CSS only
- CSP-friendly - no external resources
- Optional DOMPurify sanitization supported

### 📦 Backward Compatibility

- ✅ All existing API fields maintained
- ✅ Old frontend code continues to work
- ✅ New fields can be safely ignored
- ✅ No breaking changes

---

## [1.1.1] - 2024-XX-XX

### Fixed
- Bug fixes and improvements

---

## [1.1.0] - 2024-XX-XX

### Added
- Enhanced authentication support
- Multiple auth guard options (Sanctum, Passport, API, Web)

---

## [1.0.0] - 2024-XX-XX

### 🎉 Initial Release

- Natural Language to SQL conversion
- Schema-RAG (Retrieval Augmented Generation)
- Semantic schema search with embeddings
- Read-only database security
- SQL injection prevention
- Rate limiting
- Query logging
- Auto-discovery of database structure
- Column-aware matching
- Relationship discovery via foreign keys
- Documentation embedding support
- Interactive table selection for schema embedding
- Hybrid query support (SQL + RAG)

---

## Links

- [GitHub Repository](https://github.com/MdIshtiaque/larabot-ai)
- [Packagist](https://packagist.org/packages/emon/larabot-ai)
- [Documentation](README.md)
- [Issues](https://github.com/MdIshtiaque/larabot-ai/issues)

---

## Version Notes

### Versioning Strategy

- **Major** (X.0.0) - Breaking changes, major features
- **Minor** (1.X.0) - New features, backward compatible
- **Patch** (1.1.X) - Bug fixes, small improvements

### Upgrade Guide

#### From 1.1.x to 1.2.0

No changes required! Version 1.2.0 is fully backward compatible.

**To use new visualization features:**

```javascript
// Frontend integration (optional)
if (result.data.html) {
  document.getElementById('viz').innerHTML = result.data.html;
}
```

**Benefits:**
- Existing code works without modification
- New visualization features available when frontend is updated
- Natural language answers always included
- No configuration changes needed

---

## Future Roadmap

### Planned Features

- [ ] Interactive JavaScript-based charts
- [ ] Export visualizations as images
- [ ] Dark mode support for visualizations
- [ ] Custom color themes
- [ ] SVG-based visualizations
- [ ] Animation effects
- [ ] Chart.js integration option
- [ ] Real-time query streaming
- [ ] Multi-language support
- [ ] Query suggestions/autocomplete
- [ ] Advanced analytics dashboard

### Under Consideration

- Integration with other LLM providers (OpenAI, Anthropic)
- Support for PostgreSQL, SQLite
- GraphQL API option
- WebSocket support for real-time updates
- Voice query support
- Query templates/saved queries
- User feedback and rating system

---

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## License

MIT License - see [LICENSE](LICENSE) file for details.

