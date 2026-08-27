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

        .setup-intro {
            font-size: 0.9rem;
            color: #333;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .step {
            background: #f9f9f9;
            border-radius: 6px;
            padding: 1rem 1.25rem 1.25rem;
            margin-bottom: 1rem;
        }

        .step h2 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        .step-num {
            flex: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 999px;
            background: #e84e4e;
            color: #fff;
            font-size: 0.8rem;
        }

        .step p { font-size: 0.875rem; margin-bottom: 1rem; }

        .step-note {
            font-size: 0.8rem;
            color: #777;
            line-height: 1.5;
            margin-bottom: 0;
        }

        .step details {
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #444;
        }

        .step summary {
            cursor: pointer;
            color: #e84e4e;
            font-weight: 500;
        }

        .step details ol {
            padding-left: 1.25rem;
            line-height: 1.8;
            margin: 0.75rem 0;
        }

        .step code {
            font-family: monospace;
            background: #eee;
            border-radius: 3px;
            padding: 1px 4px;
        }

        .reconnect {
            font-size: 0.8rem;
            color: #aaa;
            text-align: center;
            margin-top: 1rem;
        }

        .btn-setup {
            display: block;
            text-align: center;
            padding: 0.75rem;
            background: #059669;
            color: #fff;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 0.75rem;
            transition: background .15s;
        }

        .btn-setup:hover { background: #047857; }

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

    <h1>CoopCycle is connected</h1>

    <?php if ($shop): ?>
        <p>Your Shopify store <strong><?= htmlspecialchars($shop, ENT_QUOTES) ?></strong> is connected to
        <?php if (!empty($tenantUrl)): ?>your cooperative at <strong><?= htmlspecialchars($tenantUrl, ENT_QUOTES) ?></strong><?php else: ?>CoopCycle<?php endif; ?>.
        Orders placed with local delivery will automatically appear in your CoopCycle dispatch.</p>
    <?php else: ?>
        <p>Your Shopify store is connected to CoopCycle. Orders placed with local delivery will automatically appear in your CoopCycle dispatch.</p>
    <?php endif; ?>

    <p class="setup-intro">Two steps are left before your first order can be dispatched.</p>

    <?php if ($shop): ?>
        <section class="step">
            <h2><span class="step-num">1</span> Set your delivery zone</h2>
            <p>
                Choose the postal codes or radius you deliver to, and what delivery costs.
                Shopify only offers local delivery to customers inside that zone.
            </p>
            <a class="btn-setup" href="https://<?= htmlspecialchars($shop, ENT_QUOTES) ?>/admin/settings/shipping/local-delivery" target="_blank" rel="noopener">
                Open local delivery settings &#x2197;
            </a>
        </section>
    <?php endif; ?>

    <section class="step">
        <h2><span class="step-num"><?= $shop ? '2' : '1' ?></span> Add the delivery date picker to your cart page</h2>
        <p>
            This is the dropdown where customers choose the day and time slot they want
            their order delivered. Until it is added, orders reach your cooperative with
            no requested delivery time.
        </p>

        <?php if (!empty($pickerDeepLink)): ?>
            <a class="btn-setup" href="<?= htmlspecialchars($pickerDeepLink, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                Add the date picker to my cart page &#x2197;
            </a>
            <p class="step-note">
                This opens your theme editor on the <strong>Cart</strong> template with the
                block already added. Drag it where you want it, then press <strong>Save</strong>.
            </p>
        <?php endif; ?>

        <details<?= empty($pickerDeepLink) ? ' open' : '' ?>>
            <summary>Add it manually instead</summary>
            <ol>
                <li><strong>Online Store &rarr; Themes &rarr; Customize</strong> on your active theme</li>
                <li>Switch the template selector at the top from <em>Default</em> to <strong>Cart</strong></li>
                <li>In the cart section, choose <strong>Add block &rarr; Apps &rarr; CoopCycle Date Picker</strong></li>
                <li>Press <strong>Save</strong></li>
            </ol>
            <p class="step-note">
                The picker is an app block, so it is <em>not</em> listed under
                <strong>App embeds</strong>. If the <strong>Apps</strong> group does not appear
                at all, your cart template is a vintage <code>cart.liquid</code> rather than an
                Online Store 2.0 JSON template; it has to be updated before any app block can
                be added to it.
            </p>
        </details>
    </section>

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
