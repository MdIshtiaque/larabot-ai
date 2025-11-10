# HTML Visualization Examples

## Real-World Response Examples

Below are actual examples of what the API will return for different query types.

---

## Example 1: Simple Count Query

### Request
```json
POST /api/bot/ask
{
  "query": "How many users are there?"
}
```

### Response
```json
{
  "success": true,
  "data": {
    "answer": "You currently have 1,234 users in your database.",
    "html": "<div style=\"font-family: system-ui, -apple-system, sans-serif; max-width: 400px;\"><div style=\"background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 32px; color: white; box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3); text-align: center;\"><div style=\"font-size: 16px; opacity: 0.9; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;\">Total Users</div><div style=\"font-size: 64px; font-weight: bold; margin-bottom: 8px; line-height: 1;\">1,234</div><div style=\"font-size: 14px; opacity: 0.85;\">📊 Active database records</div></div></div>",
    "visualization_type": "stats_card",
    "insights": [
      "User count represents total registered accounts",
      "This includes both active and inactive users"
    ],
    "intent": "sql",
    "response_time_ms": 1250,
    "sql": "SELECT COUNT(*) as count FROM users LIMIT 100;"
  }
}
```

### Rendered HTML Preview
```html
┌─────────────────────────────┐
│   TOTAL USERS               │
│                             │
│        1,234                │
│                             │
│   📊 Active database records│
└─────────────────────────────┘
  (Purple gradient background)
```

---

## Example 2: List of Items

### Request
```json
POST /api/bot/ask
{
  "query": "Show me the last 5 orders"
}
```

### Response
```json
{
  "success": true,
  "data": {
    "answer": "Here are your 5 most recent orders. The latest order #1234 was placed 2 hours ago for $89.99.",
    "html": "<div style=\"font-family: system-ui, -apple-system, sans-serif; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 600px;\"><div style=\"background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0;\"><h3 style=\"margin: 0; color: #1e293b; font-size: 18px; font-weight: 600;\">Recent Orders</h3></div><div style=\"padding: 16px;\"><div style=\"display: flex; align-items: center; padding: 16px; border-radius: 8px; margin-bottom: 12px; background: #f0f9ff; border-left: 4px solid #3b82f6;\"><div style=\"flex: 1;\"><div style=\"font-weight: 600; color: #1e293b; margin-bottom: 4px;\">Order #1234</div><div style=\"font-size: 14px; color: #64748b;\">2 hours ago</div></div><div style=\"font-size: 20px; font-weight: bold; color: #3b82f6;\">$89.99</div></div><div style=\"display: flex; align-items: center; padding: 16px; border-radius: 8px; margin-bottom: 12px; background: #f0f9ff; border-left: 4px solid #3b82f6;\"><div style=\"flex: 1;\"><div style=\"font-weight: 600; color: #1e293b; margin-bottom: 4px;\">Order #1233</div><div style=\"font-size: 14px; color: #64748b;\">5 hours ago</div></div><div style=\"font-size: 20px; font-weight: bold; color: #3b82f6;\">$124.50</div></div><div style=\"display: flex; align-items: center; padding: 16px; border-radius: 8px; margin-bottom: 12px; background: #f0f9ff; border-left: 4px solid #3b82f6;\"><div style=\"flex: 1;\"><div style=\"font-weight: 600; color: #1e293b; margin-bottom: 4px;\">Order #1232</div><div style=\"font-size: 14px; color: #64748b;\">1 day ago</div></div><div style=\"font-size: 20px; font-weight: bold; color: #3b82f6;\">$45.00</div></div></div></div>",
    "visualization_type": "list",
    "insights": [
      "Average order value: $86.50",
      "Most orders placed in afternoon hours"
    ],
    "intent": "sql",
    "response_time_ms": 1450,
    "sql": "SELECT id, created_at, total FROM orders ORDER BY created_at DESC LIMIT 5;"
  }
}
```

---

## Example 3: Comparison Data

### Request
```json
POST /api/bot/ask
{
  "query": "Compare sales between January and February"
}
```

### Response
```json
{
  "success": true,
  "data": {
    "answer": "February sales ($45,230) exceeded January sales ($38,100) by 18.7%, showing strong growth.",
    "html": "<div style=\"font-family: system-ui, -apple-system, sans-serif; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 700px;\"><div style=\"background: white; border-radius: 16px; padding: 28px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 4px solid #ef4444;\"><div style=\"color: #64748b; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;\">January 2024</div><div style=\"font-size: 42px; font-weight: bold; color: #1e293b; margin-bottom: 8px;\">$38,100</div><div style=\"display: flex; align-items: center; color: #64748b; font-size: 14px;\"><span style=\"margin-right: 8px;\">📉</span>Previous month</div></div><div style=\"background: white; border-radius: 16px; padding: 28px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 4px solid #10b981;\"><div style=\"color: #64748b; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;\">February 2024</div><div style=\"font-size: 42px; font-weight: bold; color: #1e293b; margin-bottom: 8px;\">$45,230</div><div style=\"display: flex; align-items: center; color: #10b981; font-size: 14px; font-weight: 600;\"><span style=\"margin-right: 8px;\">📈</span>+18.7% growth</div></div></div>",
    "visualization_type": "comparison",
    "insights": [
      "February showed 18.7% increase over January",
      "Trend indicates positive growth trajectory"
    ],
    "intent": "sql",
    "response_time_ms": 1620,
    "sql": "SELECT MONTHNAME(created_at) as month, SUM(total) as total_sales FROM orders WHERE created_at >= '2024-01-01' AND created_at < '2024-03-01' GROUP BY MONTH(created_at) LIMIT 100;"
  }
}
```

---

## Example 4: Table Data

### Request
```json
POST /api/bot/ask
{
  "query": "Show me top 10 products by sales"
}
```

### Response
```json
{
  "success": true,
  "data": {
    "answer": "Your top-selling product is 'Wireless Headphones' with 342 units sold, generating $25,650 in revenue.",
    "html": "<div style=\"font-family: system-ui, -apple-system, sans-serif; max-width: 800px;\"><table style=\"width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);\"><thead><tr style=\"background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\"><th style=\"padding: 16px; text-align: left; color: white; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;\">Rank</th><th style=\"padding: 16px; text-align: left; color: white; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;\">Product</th><th style=\"padding: 16px; text-align: right; color: white; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;\">Units Sold</th><th style=\"padding: 16px; text-align: right; color: white; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;\">Revenue</th></tr></thead><tbody><tr style=\"border-bottom: 1px solid #e2e8f0;\"><td style=\"padding: 16px; font-weight: 600; color: #3b82f6;\">1</td><td style=\"padding: 16px; color: #1e293b; font-weight: 500;\">Wireless Headphones</td><td style=\"padding: 16px; text-align: right; color: #1e293b;\">342</td><td style=\"padding: 16px; text-align: right; color: #10b981; font-weight: 600;\">$25,650</td></tr><tr style=\"border-bottom: 1px solid #e2e8f0; background: #f8fafc;\"><td style=\"padding: 16px; font-weight: 600; color: #3b82f6;\">2</td><td style=\"padding: 16px; color: #1e293b; font-weight: 500;\">Smart Watch</td><td style=\"padding: 16px; text-align: right; color: #1e293b;\">298</td><td style=\"padding: 16px; text-align: right; color: #10b981; font-weight: 600;\">$44,700</td></tr><tr style=\"border-bottom: 1px solid #e2e8f0;\"><td style=\"padding: 16px; font-weight: 600; color: #3b82f6;\">3</td><td style=\"padding: 16px; color: #1e293b; font-weight: 500;\">USB-C Cable</td><td style=\"padding: 16px; text-align: right; color: #1e293b;\">256</td><td style=\"padding: 16px; text-align: right; color: #10b981; font-weight: 600;\">$5,120</td></tr></tbody></table></div>",
    "visualization_type": "table",
    "insights": [
      "Wireless Headphones is the best-selling product",
      "Smart Watch has highest revenue despite lower units",
      "Top 3 products account for 45% of total sales"
    ],
    "intent": "sql",
    "response_time_ms": 1890,
    "sql": "SELECT name, quantity_sold, revenue FROM products ORDER BY quantity_sold DESC LIMIT 10;"
  }
}
```

---

## Example 5: Bar Chart (Categories)

### Request
```json
POST /api/bot/ask
{
  "query": "Show me orders by status"
}
```

### Response
```json
{
  "success": true,
  "data": {
    "answer": "Most of your orders are completed (450 orders), followed by pending (120 orders), processing (45 orders), and cancelled (30 orders).",
    "html": "<div style=\"font-family: system-ui, -apple-system, sans-serif; background: white; border-radius: 16px; padding: 28px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 600px;\"><h3 style=\"margin: 0 0 24px 0; color: #1e293b; font-size: 20px; font-weight: 600;\">Orders by Status</h3><div style=\"display: flex; flex-direction: column; gap: 16px;\"><div><div style=\"display: flex; justify-content: space-between; margin-bottom: 8px;\"><span style=\"font-weight: 500; color: #1e293b;\">✅ Completed</span><span style=\"font-weight: 600; color: #1e293b;\">450</span></div><div style=\"height: 32px; background: linear-gradient(90deg, #10b981, #059669); border-radius: 8px; position: relative; overflow: hidden;\"><div style=\"position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: white; font-weight: 600; font-size: 14px;\">69.8%</div></div></div><div><div style=\"display: flex; justify-content: space-between; margin-bottom: 8px;\"><span style=\"font-weight: 500; color: #1e293b;\">⏳ Pending</span><span style=\"font-weight: 600; color: #1e293b;\">120</span></div><div style=\"height: 32px; background: linear-gradient(90deg, #f59e0b, #d97706); border-radius: 8px; width: 26.7%; position: relative;\"><div style=\"position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: white; font-weight: 600; font-size: 14px;\">18.6%</div></div></div><div><div style=\"display: flex; justify-content: space-between; margin-bottom: 8px;\"><span style=\"font-weight: 500; color: #1e293b;\">🔄 Processing</span><span style=\"font-weight: 600; color: #1e293b;\">45</span></div><div style=\"height: 32px; background: linear-gradient(90deg, #3b82f6, #2563eb); border-radius: 8px; width: 10%; position: relative;\"><div style=\"position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: white; font-weight: 600; font-size: 14px;\">7%</div></div></div><div><div style=\"display: flex; justify-content: space-between; margin-bottom: 8px;\"><span style=\"font-weight: 500; color: #1e293b;\">❌ Cancelled</span><span style=\"font-weight: 600; color: #1e293b;\">30</span></div><div style=\"height: 32px; background: linear-gradient(90deg, #ef4444, #dc2626); border-radius: 8px; width: 6.7%; position: relative;\"><div style=\"position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: white; font-weight: 600; font-size: 14px;\">4.7%</div></div></div></div></div>",
    "visualization_type": "bar_chart",
    "insights": [
      "69.8% completion rate is healthy",
      "Consider addressing pending orders",
      "Low cancellation rate (4.7%) is positive"
    ],
    "intent": "sql",
    "response_time_ms": 1720,
    "sql": "SELECT status, COUNT(*) as count FROM orders GROUP BY status ORDER BY count DESC LIMIT 100;"
  }
}
```

---

## Example 6: Metric Grid (Dashboard)

### Request
```json
POST /api/bot/ask
{
  "query": "Give me today's statistics"
}
```

### Response
```json
{
  "success": true,
  "data": {
    "answer": "Today you have 145 new orders totaling $8,450, with 234 active users and an average order value of $58.28.",
    "html": "<div style=\"font-family: system-ui, -apple-system, sans-serif; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; max-width: 900px;\"><div style=\"background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);\"><div style=\"display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;\"><div style=\"font-size: 14px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;\">Orders</div><div style=\"font-size: 28px;\">📦</div></div><div style=\"font-size: 42px; font-weight: bold; margin-bottom: 8px;\">145</div><div style=\"font-size: 13px; opacity: 0.85;\">+12% from yesterday</div></div><div style=\"background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 8px 16px rgba(245, 87, 108, 0.3);\"><div style=\"display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;\"><div style=\"font-size: 14px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;\">Revenue</div><div style=\"font-size: 28px;\">💰</div></div><div style=\"font-size: 42px; font-weight: bold; margin-bottom: 8px;\">$8,450</div><div style=\"font-size: 13px; opacity: 0.85;\">Daily target: 85%</div></div><div style=\"background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 8px 16px rgba(79, 172, 254, 0.3);\"><div style=\"display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;\"><div style=\"font-size: 14px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;\">Active Users</div><div style=\"font-size: 28px;\">👥</div></div><div style=\"font-size: 42px; font-weight: bold; margin-bottom: 8px;\">234</div><div style=\"font-size: 13px; opacity: 0.85;\">Online right now</div></div><div style=\"background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 8px 16px rgba(67, 233, 123, 0.3);\"><div style=\"display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;\"><div style=\"font-size: 14px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;\">Avg Order</div><div style=\"font-size: 28px;\">📊</div></div><div style=\"font-size: 42px; font-weight: bold; margin-bottom: 8px;\">$58.28</div><div style=\"font-size: 13px; opacity: 0.85;\">+$3.50 vs average</div></div></div>",
    "visualization_type": "metric_grid",
    "insights": [
      "Strong daily performance with 145 orders",
      "Active user count is above average",
      "Average order value is healthy at $58.28"
    ],
    "intent": "sql",
    "response_time_ms": 2100,
    "sql": "SELECT COUNT(*) as orders, SUM(total) as revenue, AVG(total) as avg_order FROM orders WHERE DATE(created_at) = CURDATE() LIMIT 100;"
  }
}
```

---

## Example 7: Text Only (No Visualization)

### Request
```json
POST /api/bot/ask
{
  "query": "What is the return policy?"
}
```

### Response
```json
{
  "success": true,
  "data": {
    "answer": "Our return policy allows customers to return items within 30 days of purchase with a valid receipt. Items must be in original condition with tags attached. Refunds are processed within 5-7 business days.",
    "html": null,
    "visualization_type": "text",
    "insights": [],
    "intent": "rag",
    "response_time_ms": 850
  }
}
```

---

## Frontend Implementation

### Simple JavaScript Example

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot with Visualizations</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 20px; background: #f1f5f9; }
        .container { max-width: 1000px; margin: 0 auto; }
        .query-box { margin-bottom: 20px; }
        .query-box input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px; }
        .response { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .answer { font-size: 18px; color: #1e293b; margin-bottom: 20px; line-height: 1.6; }
        .insights { background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 4px; margin-top: 20px; }
        .insights h4 { margin: 0 0 12px 0; color: #1e40af; }
        .insights li { color: #1e293b; margin-bottom: 8px; }
        .metadata { margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>AI Bot with Visualizations</h1>
        
        <div class="query-box">
            <input type="text" id="queryInput" placeholder="Ask me anything... (e.g., How many users?)" />
        </div>

        <div id="responseContainer"></div>
    </div>

    <script>
        const queryInput = document.getElementById('queryInput');
        const responseContainer = document.getElementById('responseContainer');

        queryInput.addEventListener('keypress', async (e) => {
            if (e.key === 'Enter' && queryInput.value.trim()) {
                await askBot(queryInput.value.trim());
                queryInput.value = '';
            }
        });

        async function askBot(query) {
            responseContainer.innerHTML = '<div class="response">Loading...</div>';

            try {
                const response = await fetch('/api/bot/ask', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ query })
                });

                const result = await response.json();

                if (result.success) {
                    displayResponse(result.data);
                } else {
                    responseContainer.innerHTML = `<div class="response">Error: ${result.error}</div>`;
                }
            } catch (error) {
                responseContainer.innerHTML = `<div class="response">Error: ${error.message}</div>`;
            }
        }

        function displayResponse(data) {
            let html = '<div class="response">';
            
            // Natural Language Answer (Always)
            html += `<div class="answer">${data.answer}</div>`;

            // HTML Visualization (If Available)
            if (data.html) {
                html += `<div class="visualization">${data.html}</div>`;
            }

            // Insights (If Available)
            if (data.insights && data.insights.length > 0) {
                html += '<div class="insights">';
                html += '<h4>💡 Key Insights:</h4>';
                html += '<ul>';
                data.insights.forEach(insight => {
                    html += `<li>${insight}</li>`;
                });
                html += '</ul></div>';
            }

            // Metadata
            html += '<div class="metadata">';
            html += `Type: ${data.visualization_type || 'text'} | `;
            html += `Intent: ${data.intent} | `;
            html += `Response time: ${data.response_time_ms}ms`;
            html += '</div>';

            html += '</div>';
            responseContainer.innerHTML = html;
        }
    </script>
</body>
</html>
```

---

## Testing Queries

Try these queries to see different visualization types:

### Stats Cards
- "How many users?"
- "What's the total revenue?"
- "Count of active orders"

### Tables
- "Show me top 10 products"
- "List all customers"
- "Recent transactions"

### Bar Charts
- "Orders by status"
- "Products by category"
- "Sales by region"

### Comparisons
- "Compare sales this month vs last month"
- "Active vs inactive users"
- "Product A vs Product B performance"

### Lists
- "Last 5 orders"
- "Recent sign-ups"
- "Latest transactions"

### Metric Grids
- "Today's statistics"
- "Dashboard overview"
- "Key metrics"

---

## Notes

1. **Natural Language is ALWAYS provided** - Even if visualization is null
2. **HTML is self-contained** - No external CSS/JS dependencies
3. **Responsive design** - Works on mobile and desktop
4. **Safe to render** - Generated by AI, not user input
5. **Flexible** - Can be styled/overridden by your CSS

Enjoy building with beautiful visualizations! 🎨

