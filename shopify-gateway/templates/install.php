<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect to CoopCycle</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,.1);
            padding: 2.5rem;
            width: 100%;
            max-width: 480px;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 700;
            color: #e84e4e;
            margin-bottom: 0.25rem;
        }

        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        p { color: #555; line-height: 1.5; margin-bottom: 1.5rem; }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 0.375rem;
        }

        input[type="text"], input[type="url"] {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            color: #1a1a1a;
            margin-bottom: 1.25rem;
            transition: border-color .15s;
        }

        input[type="text"]:focus, input[type="url"]:focus {
            outline: none;
            border-color: #e84e4e;
            box-shadow: 0 0 0 3px rgba(232,78,78,.15);
        }

        .hint {
            font-size: 0.8rem;
            color: #888;
            margin-top: -1rem;
            margin-bottom: 1.25rem;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: #e84e4e;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }

        button:hover { background: #cf3a3a; }

        .footer {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">CoopCycle</div>
    <h1>Connect your Shopify store</h1>
    <p>
        CoopCycle is used by many local cooperatives, each with their own platform.
        Tell us which cooperative manages deliveries for your area.
    </p>

    <form method="POST" action="/shopify/start">
        <input type="hidden" name="shop" value="<?= htmlspecialchars($shop ?? '', ENT_QUOTES) ?>">

        <label for="tenant_url">Your CoopCycle cooperative's URL</label>
        <input
            type="url"
            id="tenant_url"
            name="tenant_url"
            placeholder="https://paris.coopcycle.org"
            required
            autofocus
        >
        <p class="hint">
            Not sure? Contact your local cooperative or visit
            <a href="https://coopcycle.org" target="_blank" rel="noopener">coopcycle.org</a>
            to find one near you.
        </p>

        <button type="submit">Connect &#x2192;</button>
    </form>

    <p class="footer">
        You will be asked to authorise CoopCycle on the next screen.<br>
        Your Shopify credentials are never stored by this gateway.
    </p>
</div>
</body>
</html>
