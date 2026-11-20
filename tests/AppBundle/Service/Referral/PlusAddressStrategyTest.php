<?php

namespace Tests\AppBundle\Service\Referral;

use AppBundle\Service\Referral\PlusAddressStrategy;
use PHPUnit\Framework\TestCase;

class PlusAddressStrategyTest extends TestCase
{
    private PlusAddressStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new PlusAddressStrategy();
    }

    public function canonicalFormProvider(): array
    {
        return [
            'plus alias on an arbitrary domain' => ['foo+alias@example.com', 'foo@example.com'],
            'several plus signs' => ['foo+a+b@example.com', 'foo@example.com'],
            'no alias' => ['foo@example.com', 'foo@example.com'],
            'uppercase local part' => ['FOO@example.com', 'foo@example.com'],
            'uppercase local part with an alias' => ['Foo+Alias@example.com', 'foo@example.com'],
            // Dots are only meaningless at some providers (Gmail), so this
            // strategy leaves them alone -- GmailStrategy handles that case.
            'dots are kept' => ['f.oo@example.com', 'f.oo@example.com'],
            // Would otherwise collapse every "+x@domain" into "@domain".
            'leading plus is left alone' => ['+alias@example.com', '+alias@example.com'],
        ];
    }

    /**
     * @dataProvider canonicalFormProvider
     */
    public function testGetCanonicalEmailAddress(string $input, string $expected): void
    {
        self::assertSame($expected, $this->strategy->getCanonicalEmailAddress($input));
    }

    public function testDoesNotSupportAnInvalidEmailAddress(): void
    {
        self::assertFalse($this->strategy->supportsEmailAddress('not-an-email'));
    }
}
