<?php

namespace Tests\AppBundle\EventSubscriber;

use AppBundle\EventSubscriber\ZeltyWebhookSignatureSubscriber;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ZeltyWebhookSignatureSubscriberTest extends TestCase
{
    use ProphecyTrait;

    const SECRET = 'a-shared-secret';

    const BODY = '{"eventName":"dish.delete","data":{"dishes":[{"id":"1"}]}}';

    private function handle(
        string $path,
        string $body = self::BODY,
        ?string $signature = null,
        string $secret = self::SECRET,
        int $requestType = HttpKernelInterface::MAIN_REQUEST
    ): void {
        $server = $signature === null ? [] : ['HTTP_X_ZELTY_HMAC_SHA256' => $signature];
        $request = Request::create($path, 'POST', [], [], [], $server, $body);

        (new ZeltyWebhookSignatureSubscriber($secret))->onKernelRequest(
            new RequestEvent($this->prophesize(HttpKernelInterface::class)->reveal(), $request, $requestType)
        );
    }

    private function sign(string $body, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    public function testAcceptsCorrectlySignedWebhook(): void
    {
        $this->handle('/api/zelty/webhook/dish.delete', self::BODY, $this->sign(self::BODY));

        $this->expectNotToPerformAssertions();
    }

    public function testRejectsUnsignedWebhook(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Missing webhook signature');

        $this->handle('/api/zelty/webhook/dish.delete');
    }

    public function testRejectsInvalidSignature(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $this->handle('/api/zelty/webhook/dish.delete', self::BODY, 'nope');
    }

    /**
     * The signature covers the body, so a signature lifted from a genuine
     * webhook cannot be replayed over a different payload.
     */
    public function testRejectsSignatureOfADifferentBody(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $this->handle('/api/zelty/webhook/dish.delete', '{"eventName":"dish.delete","data":{"dishes":[{"id":"2"}]}}',
            $this->sign(self::BODY));
    }

    /**
     * Fail closed: an instance with no secret cannot tell a real webhook from a
     * forged one, so it must not accept either.
     */
    public function testRejectsEverythingWhenNoSecretIsConfigured(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Zelty webhook secret is not configured');

        $this->handle('/api/zelty/webhook/dish.delete', self::BODY, $this->sign(self::BODY, ''), '');
    }

    /**
     * Every handler under the path is covered, including ones added later.
     */
    public function testCoversEveryZeltyWebhookPath(): void
    {
        $paths = [
            '/api/zelty/webhook/catalog/1',
            '/api/zelty/webhook/order.status.update',
            '/api/zelty/webhook/dish.update',
            '/api/zelty/webhook/dish.delete',
            '/api/zelty/webhook/dish.availability_update',
            '/api/zelty/webhook/menu.update',
            '/api/zelty/webhook/menu.delete',
            '/api/zelty/webhook/menu.availability_update',
            '/api/zelty/webhook/option.update',
            '/api/zelty/webhook/option_value.availability_update',
            '/api/zelty/webhook/something.added.later',
        ];

        foreach ($paths as $path) {
            try {
                $this->handle($path);
                $this->fail(sprintf('%s accepted an unsigned webhook', $path));
            } catch (AccessDeniedHttpException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testIgnoresOtherPaths(): void
    {
        $this->handle('/api/orders');

        $this->expectNotToPerformAssertions();
    }

    public function testIgnoresSubRequests(): void
    {
        $this->handle('/api/zelty/webhook/dish.delete', self::BODY, null, self::SECRET,
            HttpKernelInterface::SUB_REQUEST);

        $this->expectNotToPerformAssertions();
    }
}
