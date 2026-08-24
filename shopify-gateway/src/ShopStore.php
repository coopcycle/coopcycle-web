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
 *
 * The same database also holds pending installs. Shopify requires OAuth to
 * happen before any UI, so the access token now exists *before* the merchant
 * has told us which cooperative to attach it to. It is parked here for the few
 * redirects that takes, rather than in a cookie — Shopify warns that
 * third-party cookies are unreliable, and the flow leaves our origin for the
 * cooperative and comes back.
 */
class ShopStore
{
    /**
     * How long a half-finished install may hold an access token. Long enough for
     * a merchant to log in to their cooperative, short enough that an abandoned
     * install does not leave a usable token lying around.
     */
    private const INSTALL_TTL = 3600;

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

    /**
     * Park a freshly issued access token until the merchant has picked their
     * cooperative. Returns the id used to reclaim it.
     */
    public function beginInstall(string $shopDomain, string $accessToken, ?string $host): string
    {
        $id = bin2hex(random_bytes(16));

        $connection = $this->connection();
        $this->purgeExpiredInstalls($connection);

        $connection->prepare(
            'INSERT INTO pending_installs (id, shop_domain, access_token, host, created_at)
             VALUES (:id, :shop, :token, :host, :now)'
        )->execute([
            'id'    => $id,
            'shop'  => $shopDomain,
            'token' => $accessToken,
            'host'  => $host,
            'now'   => time(),
        ]);

        return $id;
    }

    /**
     * @return array{shop_domain: string, access_token: string, host: ?string}|null
     */
    public function pendingInstall(string $id): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT shop_domain, access_token, host FROM pending_installs
             WHERE id = :id AND created_at > :cutoff'
        );
        $statement->execute([
            'id'     => $id,
            'cutoff' => time() - self::INSTALL_TTL,
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return false === $row ? null : $row;
    }

    public function finishInstall(string $id): void
    {
        $this->connection()
            ->prepare('DELETE FROM pending_installs WHERE id = :id')
            ->execute(['id' => $id]);
    }

    private function purgeExpiredInstalls(\PDO $connection): void
    {
        $connection->prepare('DELETE FROM pending_installs WHERE created_at <= :cutoff')
            ->execute(['cutoff' => time() - self::INSTALL_TTL]);
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

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS pending_installs (
                id           TEXT PRIMARY KEY,
                shop_domain  TEXT NOT NULL,
                access_token TEXT NOT NULL,
                host         TEXT,
                created_at   INTEGER NOT NULL
            )'
        );

        return $this->pdo;
    }
}
