# HTML Visualization Feature

## Overview

The bot now intelligently generates **beautiful HTML/CSS visualizations** along with natural language answers. The AI analyzes the query results and decides the best way to present the data.

## Key Features

✅ **Natural Language Answer (Mandatory)** - Every response includes a clear NL explanation  
✅ **Smart Visualization Detection** - AI decides when HTML visualization adds value  
✅ **Multiple Visualization Types** - Cards, tables, charts, timelines, etc.  
✅ **Self-Contained HTML** - No external dependencies, ready to render  
✅ **Beautiful Design** - Modern gradients, shadows, responsive layouts  

---

## API Response Structure

```json
{
  "success": true,
  "data": {
    "answer": "Natural language explanation (ALWAYS present)",
    "html": "Ready-to-render HTML with inline CSS (when applicable)",
    "visualization_type": "stats_card|table|bar_chart|list|timeline|none",
    "insights": ["Key insight 1", "Key insight 2"],
    "intent": "sql|rag|hybrid",
    "response_time_ms": 1250,
    "sql": "SELECT * FROM users..."
  }
}
```

---

## Visualization Types

### 1. **Stats Card** (Single Value)
**Use Case:** Count queries, sum totals, averages

**Example Query:**
```
"How many users do we have?"
```

**Response:**
```json
{
  "answer": "You currently have 1,234 users in your system.",
  "html": "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 8px 16px rgba(0,0,0,0.1);'><div style='font-size: 14px; opacity: 0.9; margin-bottom: 8px;'>Total Users</div><div style='font-size: 48px; font-weight: bold; margin-bottom: 4px;'>1,234</div><div style='font-size: 12px; opacity: 0.8;'>Active accounts</div></div>",
  "visualization_type": "stats_card"
}
```

---

### 2. **Table** (5-20 Rows)
**Use Case:** List of items with multiple columns

**Example Query:**
```
"Show me the top 10 customers by revenue"
```

**Response:**
```json
{
  "answer": "Here are your top 10 customers by revenue, led by Acme Corp with $125,000.",
  "html": "<table style='width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'><thead><tr style='background: #f8fafc;'><th style='padding: 16px; text-align: left; font-weight: 600; color: #475569;'>Customer</th><th style='padding: 16px; text-align: right; font-weight: 600; color: #475569;'>Revenue</th></tr></thead><tbody>...</tbody></table>",
  "visualization_type": "table"
}
```

---

### 3. **Bar Chart** (Categories + Numbers)
**Use Case:** Comparing categories

**Example Query:**
```
"Show me orders by status"
```

**Response:**
```json
{
  "answer": "Most orders are completed (450), followed by pending (120) and cancelled (30).",
  "html": "<div style='background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'><div style='display: flex; align-items: center; margin-bottom: 16px;'><div style='width: 120px; font-weight: 500; color: #475569;'>Completed</div><div style='flex: 1; height: 32px; background: linear-gradient(90deg, #10b981, #059669); border-radius: 6px; display: flex; align-items: center; padding: 0 12px; color: white; font-weight: bold;'>450</div></div>...</div>",
  "visualization_type": "bar_chart"
}
```

---

### 4. **Metric Grid** (Multiple Stats)
**Use Case:** Dashboard-like metrics

**Example Query:**
```
"Give me user statistics"
```

**Response:**
```json
{
  "answer": "You have 1,234 total users with 856 active today. Average session time is 5.2 minutes.",
  "html": "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;'><div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 20px; color: white;'>...</div>...</div>",
  "visualization_type": "metric_grid"
}
```

---

### 5. **List** (2-5 Items)
**Use Case:** Short lists with details

**Example Query:**
```
"Show me recent orders"
```

**Response:**
```json
{
  "answer": "Here are your 3 most recent orders from today.",
  "html": "<div style='background: white; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'><div style='display: flex; align-items: center; padding: 16px; border-bottom: 1px solid #e2e8f0;'><div style='width: 40px; height: 40px; background: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-right: 16px;'>1</div><div style='flex: 1;'>...</div></div>...</div>",
  "visualization_type": "list"
}
```

---

### 6. **Timeline** (Date-Ordered Events)
**Use Case:** Events over time

**Example Query:**
```
"Show me order timeline for this week"
```

**Response:**
```json
{
  "answer": "This week you received 45 orders spread across 7 days, with peak on Wednesday.",
  "html": "<div style='position: relative; padding-left: 40px;'><div style='position: absolute; left: 20px; top: 0; bottom: 0; width: 2px; background: #e2e8f0;'></div><div style='position: relative; margin-bottom: 24px;'>...</div></div>",
  "visualization_type": "timeline"
}
```

---

### 7. **Comparison** (2-3 Items Side-by-Side)
**Use Case:** Comparing specific items

**Example Query:**
```
"Compare sales between Product A and Product B"
```

**Response:**
```json
{
  "answer": "Product A generated $45,000 while Product B generated $38,000, showing Product A leads by 18%.",
  "html": "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 16px;'><div style='background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'>...</div>...</div>",
  "visualization_type": "comparison"
}
```

---

### 8. **Text Only** (No Visualization)
**Use Case:** Simple questions, explanations

**Example Query:**
```
"What is our return policy?"
```

**Response:**
```json
{
  "answer": "Our return policy allows customers to return items within 30 days of purchase...",
  "html": null,
  "visualization_type": "text"
}
```

---

## How It Works

### 1. Query Processing Flow

```
User Query → Intent Detection (SQL/RAG/Hybrid)
    ↓
SQL Generation & Execution
    ↓
Data Analysis (Structure, Types, Row Count)
    ↓
AI Prompt (with visualization guidelines)
    ↓
AI Response (JSON with answer + HTML)
    ↓
Parse & Return to User
```

### 2. AI Decision Making

The AI considers:
- **Row count** (1 row = stat card, 10 rows = table, etc.)
- **Column types** (dates = timeline, categories = bar chart)
- **Query intent** ("how many" = stat card, "show me" = list/table)
- **Data relationships** (comparisons, trends, distributions)

### 3. HTML Generation

The AI generates:
- ✅ **Inline CSS** (no external stylesheets)
- ✅ **Responsive design** (works on mobile/desktop)
- ✅ **Modern aesthetics** (gradients, shadows, rounded corners)
- ✅ **Semantic HTML** (proper structure)
- ✅ **Accessibility** (readable colors, proper contrast)

---

## Frontend Integration

### Simple HTML Rendering

```javascript
// Fetch from API
const response = await fetch('/api/bot/ask', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ query: 'How many users?' })
});

const result = await response.json();

// Always show the natural language answer
document.getElementById('answer').textContent = result.data.answer;

// Render HTML if provided
if (result.data.html) {
  document.getElementById('visualization').innerHTML = result.data.html;
}

// Show insights if available
if (result.data.insights?.length > 0) {
  result.data.insights.forEach(insight => {
    // Display insights
  });
}
```

### React Example

```jsx
function BotResponse({ data }) {
  return (
    <div className="bot-response">
      {/* Natural Language Answer (Always) */}
      <div className="answer">
        <p>{data.answer}</p>
      </div>

      {/* HTML Visualization (When Available) */}
      {data.html && (
        <div 
          className="visualization"
          dangerouslySetInnerHTML={{ __html: data.html }}
        />
      )}

      {/* Insights (Optional) */}
      {data.insights?.length > 0 && (
        <div className="insights">
          <h4>Key Insights:</h4>
          <ul>
            {data.insights.map((insight, i) => (
              <li key={i}>{insight}</li>
            ))}
          </ul>
        </div>
      )}

      {/* Metadata */}
      <div className="metadata">
        <span>Type: {data.visualization_type}</span>
        <span>Response time: {data.response_time_ms}ms</span>
      </div>
    </div>
  );
}
```

### Vue.js Example

```vue
<template>
  <div class="bot-response">
    <!-- Natural Language Answer -->
    <div class="answer">
      <p>{{ data.answer }}</p>
    </div>

    <!-- HTML Visualization -->
    <div 
      v-if="data.html" 
      class="visualization"
      v-html="data.html"
    ></div>

    <!-- Insights -->
    <div v-if="data.insights?.length" class="insights">
      <h4>Key Insights:</h4>
      <ul>
        <li v-for="(insight, i) in data.insights" :key="i">
          {{ insight }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
export default {
  props: ['data']
}
</script>
```

---

## Configuration

The feature uses existing configuration in `config/gemini.php`:

```php
'llm_model' => env('GEMINI_LLM_MODEL', 'models/gemini-pro'),
'timeout' => env('GEMINI_TIMEOUT', 30),
```

For better HTML generation, you can increase the token limit:

```php
// In GeminiService::generateText()
'maxOutputTokens' => 4096, // Allows for complex HTML
'temperature' => 0.4, // Balanced creativity
```

---

## Benefits

### For Users
- 📊 **Visual clarity** - See data at a glance
- 📝 **Text explanation** - Always get a summary
- 💡 **Insights** - AI highlights important findings
- 🎨 **Beautiful UI** - Professional, modern design

### For Developers
- 🚀 **Easy integration** - Just render the HTML
- 🔧 **No dependencies** - Self-contained
- 📱 **Responsive** - Works on all devices
- ⚡ **Fast** - Pre-rendered HTML

---

## Advanced Customization

### Custom Styling

You can override the HTML with your own CSS:

```css
/* Wrap AI-generated HTML in a container */
.bot-visualization {
  /* Your custom styles */
}

.bot-visualization table {
  /* Override table styles */
}

.bot-visualization [style*="background"] {
  /* Override background colors */
}
```

### Extracting Data from HTML

If you need to use the data separately (e.g., for charts):

```javascript
// The raw data is still available
const sqlQuery = result.data.sql;

// Re-execute if needed, or parse from HTML
// Better: Extend the API to return both HTML and JSON data
```

---

## Examples of Real Queries

### E-commerce Dashboard

**Query:** "How many orders today?"
- **Answer:** "You received 45 orders today, totaling $3,250."
- **HTML:** Large stat card with gradient background
- **Type:** `stats_card`

**Query:** "Show top 5 products by sales"
- **Answer:** "Your top-selling products are led by Product A with 120 units sold."
- **HTML:** Horizontal bar chart with product names
- **Type:** `bar_chart`

### User Analytics

**Query:** "Show me user registration trend this week"
- **Answer:** "User registrations peaked on Wednesday with 34 new users."
- **HTML:** Timeline with daily breakdown
- **Type:** `timeline`

**Query:** "Compare active vs inactive users"
- **Answer:** "You have 856 active users (69%) and 378 inactive users (31%)."
- **HTML:** Side-by-side comparison cards
- **Type:** `comparison`

---

## Troubleshooting

### No HTML Generated

**Reason:** AI determined text is sufficient

**Example:** Simple counts, single words, yes/no answers

**Solution:** This is expected behavior. Not all queries need visualization.

### HTML Not Rendering

**Check:**
1. Is `result.data.html` null?
2. Is there a CSP (Content Security Policy) blocking inline styles?
3. Browser console errors?

**Solution:**
```javascript
if (result.data.html) {
  console.log('HTML length:', result.data.html.length);
  console.log('Visualization type:', result.data.visualization_type);
}
```

### Incomplete HTML

**Reason:** Token limit reached

**Solution:** Increase `maxOutputTokens` in `HybridBotService.php`:
```php
'maxOutputTokens' => 8192, // Double the limit
```

---

## Future Enhancements

- [ ] Interactive charts with JavaScript
- [ ] Export visualization as image
- [ ] Theme customization (dark mode)
- [ ] Custom color schemes per user
- [ ] Animation effects
- [ ] Chart.js integration option
- [ ] SVG-based visualizations

---

## Security Considerations

✅ **Safe:** HTML is generated by AI, not user input  
✅ **Safe:** No JavaScript execution (inline styles only)  
✅ **Safe:** No external resources loaded  
⚠️ **Caution:** Using `dangerouslySetInnerHTML` (React) or `v-html` (Vue)

**Best Practice:**
```javascript
// Sanitize if needed (though AI output should be safe)
import DOMPurify from 'dompurify';

const cleanHTML = DOMPurify.sanitize(result.data.html);
element.innerHTML = cleanHTML;
```

---

## Testing

### Manual Testing

```bash
# Test with various query types
curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -d '{"query": "How many users?"}'

curl -X POST http://localhost:8000/api/bot/ask \
  -H "Content-Type: application/json" \
  -d '{"query": "Show me top 10 orders"}'
```

### Automated Testing

```php
/** @test */
public function it_returns_html_visualization_for_stat_queries()
{
    $response = $this->postJson('/api/bot/ask', [
        'query' => 'How many users?'
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'answer',
            'html',
            'visualization_type'
        ]
    ]);
    
    $this->assertEquals('stats_card', $response['data']['visualization_type']);
    $this->assertNotNull($response['data']['html']);
}
```

---

## Conclusion

The HTML visualization feature makes your bot responses more engaging and informative. The AI intelligently decides when to show visualizations while **always** providing natural language explanations.

**Remember:** 
- Natural language answer is MANDATORY
- HTML is optional and context-dependent
- All visualizations are self-contained and ready to render

Happy coding! 🚀

