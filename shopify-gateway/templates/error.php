<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error — CoopCycle</title>
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
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.75rem;
        }

        p { color: #555; line-height: 1.6; margin-bottom: 1.5rem; }

        .error-detail {
            background: #fff5f5;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            color: #b91c1c;
            font-size: 0.9rem;
            text-align: left;
            margin-bottom: 1.5rem;
            word-break: break-word;
        }

        a {
            color: #e84e4e;
            text-decoration: none;
            font-weight: 500;
        }

        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">&#x26A0;&#xFE0F;</div>
    <h1>Something went wrong</h1>
    <?php if (!empty($message)): ?>
        <div class="error-detail"><?= htmlspecialchars($message, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if (!empty($safe)): ?>
        <div class="error-detail"><?= $safe /* already escaped in index.php */ ?></div>
    <?php endif; ?>
    <p>
        Please try again or contact your CoopCycle cooperative for help.
    </p>
    <p>
        <a href="/shopify/install">&#x2190; Start over</a>
    </p>
</div>
</body>
</html>
