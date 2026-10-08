<?php

namespace Tests\AppBundle\Service\Referral;

use AppBundle\Service\Referral\ReferralEmailCanonizerFactory;
use PHPUnit\Framework\TestCase;

/**
 * Pins the composition built by the factory -- the strategies are applied in
 * sequence, so their order is load-bearing, not cosmetic.
 */
class ReferralEmailCanonizerFactoryTest extends TestCase
{
    public function canonicalFormProvider(): array
    {
        return [
            'plus alias, any domain' => ['foo+alias@example.com', 'foo@example.com'],
            'mixed case' => ['FOO+Alias@Example.COM', 'foo@example.com'],
            'gmail ignores dots' => ['f.o.o@gmail.com', 'foo@gmail.com'],
            'googlemail is gmail' => ['foo+a@googlemail.com', 'foo@gmail.com'],
            // Regression: GmailStrategy matches its domains case-sensitively,
            // so the domain has to be lowercased before it runs, or the dots
            // (meaningless to Gmail) survive and two aliases look distinct.
            'gmail with a capitalised domain' => ['F.o.o+alias@Gmail.com', 'foo@gmail.com'],
            'dots are kept elsewhere' => ['f.oo@example.com', 'f.oo@example.com'],
            'nothing to strip' => ['alice@example.com', 'alice@example.com'],
        ];
    }

    /**
     * @dataProvider canonicalFormProvider
     */
    public function testGetCanonicalEmailAddress(string $input, string $expected): void
    {
        self::assertSame(
            $expected,
            ReferralEmailCanonizerFactory::create()->getCanonicalEmailAddress($input)
        );
    }
}
