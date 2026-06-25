<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoopCycle — Active</title>
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

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            background: #ecfdf5;
            color: #065f46;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.25rem 0.625rem;
            border-radius: 999px;
            margin-bottom: 1.5rem;
        }

        .badge::before { content: "●"; font-size: 0.6rem; }

        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        p { color: #555; line-height: 1.5; margin-bottom: 1rem; }

        .steps {
            background: #f9f9f9;
            border-radius: 6px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .steps ol {
            padding-left: 1.25rem;
            color: #444;
            line-height: 1.8;
            font-size: 0.9rem;
        }

        .reconnect {
            font-size: 0.8rem;
            color: #aaa;
            text-align: center;
            margin-top: 1rem;
        }

        .btn-back {
            display: block;
            text-align: center;
            padding: 0.75rem;
            background: #e84e4e;
            color: #fff;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 1rem;
            transition: background .15s;
        }

        .btn-back:hover { background: #cf3a3a; }

        .reconnect a { color: #e84e4e; text-decoration: none; }
        .reconnect a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">CoopCycle</div>
    <div class="badge">Active</div>

    <h1>Delivery customization is running</h1>

    <?php if ($shop): ?>
        <p>Your Shopify store <strong><?= htmlspecialchars($shop, ENT_QUOTES) ?></strong> is connected to CoopCycle.</p>
    <?php else: ?>
        <p>Your Shopify store is connected to CoopCycle.</p>
    <?php endif; ?>

    <p>The delivery zone filter is active at checkout. Buyers outside your cooperative's delivery area will not see the CoopCycle shipping option.</p>

    <div class="steps">
        <p style="font-size:0.875rem;font-weight:600;color:#333;margin-bottom:0.5rem;">To configure delivery postal codes:</p>
        <ol>
            <li>Log in to your CoopCycle admin</li>
            <li>Go to <strong>Stores → your store → Shopify</strong></li>
            <li>Enter the postal codes for your delivery zone</li>
        </ol>
    </div>

    <?php if ($backUrl): ?>
        <a class="btn-back" href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>">
            &#x2190; Back to Shopify settings
        </a>
    <?php endif; ?>

    <div class="reconnect">
        Need to reconnect or switch cooperative?
        <a href="/shopify/install?shop=<?= urlencode($shop ?? '') ?>">Reinstall</a>
    </div>
</div>
</body>
</html>
