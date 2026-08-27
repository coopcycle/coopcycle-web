<?php

namespace Tests\AppBundle\Controller;

use PHPUnit\Framework\TestCase;

/**
 * The Shopify access scopes are spelled out in three places that cannot share
 * code: the app config Shopify itself reads (shopify.app.toml), the gateway
 * microservice that builds the consent URL for App Store installs, and the
 * tenant controller that builds it for the direct install path.
 *
 * They drifted once already — the gateway asked for write_shipping and the two
 * delivery_customizations scopes that nothing in the codebase ever used, which
 * is both a broader consent screen than merchants need and a documented App
 * Store rejection cause. These tests fail the moment they diverge again.
 */
class ShopifyScopesTest extends TestCase
{
    /**
     * Every scope the app is entitled to ask a merchant for. Adding one here is
     * meant to be deliberate: App Store review requires a demonstrated use for
     * each, so a new entry needs the code that uses it in the same change.
     */
    private const EXPECTED = 'read_orders,write_fulfillments,read_fulfillments,read_merchant_managed_fulfillment_orders,'
                           . 'read_metafields,write_metafields';

    private static function projectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function testAppTomlDeclaresTheExpectedScopes()
    {
        $this->assertSame(self::EXPECTED, $this->scopesFromToml('shopify-app/shopify.app.toml'));
    }

    public function testAppTomlExampleDeclaresTheExpectedScopes()
    {
        $this->assertSame(self::EXPECTED, $this->scopesFromToml('shopify-app/shopify.app.toml.example'));
    }

    public function testTenantControllerRequestsTheExpectedScopes()
    {
        $source = $this->read('src/Controller/ShopifyController.php');

        $this->assertSame(
            1,
            preg_match('/\$scopes\s*=\s*(.+?);/s', $source, $matches),
            'Could not find the $scopes assignment in ShopifyController::install().'
        );

        $this->assertSame(self::EXPECTED, $this->joinLiterals($matches[1]));
    }

    public function testGatewayRequestsTheExpectedScopes()
    {
        $source = $this->read('shopify-gateway/src/OAuthHandler.php');

        $this->assertSame(
            1,
            preg_match('/private const SCOPES\s*=\s*(.+?);/s', $source, $matches),
            'Could not find the SCOPES constant in OAuthHandler.'
        );

        $this->assertSame(self::EXPECTED, $this->joinLiterals($matches[1]));
    }

    /**
     * Both scope lists are written as concatenated string literals so they stay
     * readable at this length; collapse one back to the value PHP would build.
     */
    private function joinLiterals(string $expression): string
    {
        preg_match_all("/'([^']*)'/", $expression, $parts);

        return implode('', $parts[1]);
    }

    /**
     * The scope table in the README and the list in the privacy policy are what
     * merchants and App Store reviewers actually read. A scope missing from
     * either is an undisclosed permission.
     */
    public function testDocumentationMentionsEveryScope()
    {
        $readme  = $this->read('shopify-app/README.md');
        $privacy = $this->read('shopify-app/PRIVACY.md');

        foreach (explode(',', self::EXPECTED) as $scope) {
            $this->assertStringContainsString($scope, $readme, sprintf('%s is undocumented in the README.', $scope));
            $this->assertStringContainsString($scope, $privacy, sprintf('%s is undisclosed in the privacy policy.', $scope));
        }
    }

    private function scopesFromToml(string $relativePath): string
    {
        $this->assertSame(
            1,
            preg_match('/^\s*scopes\s*=\s*"([^"]*)"/m', $this->read($relativePath), $matches),
            sprintf('Could not find [access_scopes] scopes in %s.', $relativePath)
        );

        return $matches[1];
    }

    private function read(string $relativePath): string
    {
        $path = self::projectDir() . '/' . $relativePath;

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
