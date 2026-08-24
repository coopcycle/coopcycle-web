<?php

declare(strict_types=1);

namespace CoopCycle\ShopifyGateway;

/**
 * Remembers which CoopCycle cooperative a Shopify shop was installed on.
 *
 * The gateway is otherwise stateless — the install flow carries everything in a
 * signed `state` parameter. This mapping exists for one reason: Shopify's
 * compliance webhooks are app-level, delivered to a single URI, and carry only
 * `shop_domain`. Without a mapping the gateway could not tell which cooperative
 * holds a merchant's data, and broadcasting a payload containing a customer's
 * email and phone to every cooperative would leak personal data to unrelated
 * parties — the opposite of what these webhooks are for.
 *
 * SQLite because it needs to survive `shop/redact`, which arrives 48 hours
 * after an uninstall.
 */
class ShopStore
{
    private ?\PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->path;
    }

    public function remember(string $shopDomain, string $tenantUrl): void
    {
        $this->connection()->prepare(
            'INSERT INTO shops (shop_domain, tenant_url, updated_at) VALUES (:shop, :tenant, :now)
             ON CONFLICT(shop_domain) DO UPDATE SET tenant_url = :tenant, updated_at = :now'
        )->execute([
            'shop'   => $shopDomain,
            'tenant' => $tenantUrl,
            'now'    => gmdate('c'),
        ]);
    }

    public function tenantFor(string $shopDomain): ?string
    {
        $statement = $this->connection()->prepare(
            'SELECT tenant_url FROM shops WHERE shop_domain = :shop'
        );
        $statement->execute(['shop' => $shopDomain]);

        $tenantUrl = $statement->fetchColumn();

        return false === $tenantUrl ? null : (string) $tenantUrl;
    }

    public function forget(string $shopDomain): void
    {
        $this->connection()
            ->prepare('DELETE FROM shops WHERE shop_domain = :shop')
            ->execute(['shop' => $shopDomain]);
    }

    private function connection(): \PDO
    {
        if (null !== $this->pdo) {
            return $this->pdo;
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('SHOPS_DB_PATH is not configured.');
        }

        $directory = \dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create "%s".', $directory));
        }

        $this->pdo = new \PDO('sqlite:' . $this->path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS shops (
                shop_domain TEXT PRIMARY KEY,
                tenant_url  TEXT NOT NULL,
                updated_at  TEXT NOT NULL
            )'
        );

        return $this->pdo;
    }
}
