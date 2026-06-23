<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connected — CoopCycle</title>
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
            text-align: center;
        }

        .icon { font-size: 3rem; margin-bottom: 1rem; }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.75rem;
        }

        p { color: #555; line-height: 1.6; margin-bottom: 1rem; }

        .shop { font-weight: 600; color: #1a1a1a; }

        a.button {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: #e84e4e;
            color: #fff;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s;
        }

        a.button:hover { background: #cf3a3a; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">&#x2705;</div>
    <h1>You're connected!</h1>
    <p>
        <span class="shop"><?= htmlspecialchars($shop ?? '', ENT_QUOTES) ?></span>
        has been successfully linked to your CoopCycle cooperative at
        <strong><?= htmlspecialchars($tenantUrl ?? '', ENT_QUOTES) ?></strong>.
    </p>
    <p>
        New orders placed through your Shopify store will now automatically
        be dispatched as bike deliveries. Your cooperative will contact you
        to confirm the setup.
    </p>
    <a class="button"
       href="https://<?= htmlspecialchars($shop ?? '', ENT_QUOTES) ?>/admin"
       target="_blank" rel="noopener">
        Back to Shopify admin &#x2192;
    </a>
</div>
</body>
</html>
