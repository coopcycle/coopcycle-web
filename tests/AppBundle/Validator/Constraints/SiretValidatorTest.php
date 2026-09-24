<?php

namespace Tests\AppBundle\Validator\Constraints;

use AppBundle\Validator\Constraints\Siret;
use AppBundle\Validator\Constraints\SiretValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * The INSEE lookup hangs on a subtlety: Symfony's HTTP client is lazy, so a
 * 4xx/5xx only becomes an exception once the response is read — and dropping
 * the response unread counts, because its destructor checks the status code.
 * That is what made the catches fire before they read anything explicitly, and
 * why PHPStan reports them as dead code (it cannot see a destructor throw).
 *
 * These tests pin the behaviour itself, so the explicit read stays equivalent
 * to what the destructor was doing by accident.
 */
class SiretValidatorTest extends ConstraintValidatorTestCase
{
    private MockResponse $response;

    private LoggerInterface $logger;

    protected function createValidator(): SiretValidator
    {
        $this->logger = $this->createMock(LoggerInterface::class);

        return new SiretValidator(
            new MockHttpClient(fn () => $this->response),
            $this->logger
        );
    }

    public function testAKnownEstablishmentPasses(): void
    {
        $this->response = new MockResponse('{"etablissement":{}}', ['http_code' => 200]);

        $this->validator->validate('81146060900018', new Siret());

        $this->assertNoViolation();
    }

    public function testInseeRejectingTheNumberIsReportedWithItsOwnMessage(): void
    {
        $this->response = new MockResponse(
            '{"header":{"statut":404,"message":"no results found"}}',
            ['http_code' => 404]
        );

        $this->validator->validate('81146060900018', new Siret());

        $this->buildViolation('no results found')->assertRaised();
    }

    public function testSpacesAreIgnored(): void
    {
        $this->response = new MockResponse('{"etablissement":{}}', ['http_code' => 200]);

        $this->validator->validate(' 811 460 609 00018 ', new Siret());

        $this->assertNoViolation();
    }

    public function testAnEmptyValueIsNotLookedUp(): void
    {
        $this->response = new MockResponse('', ['http_code' => 500]);

        $this->validator->validate('', new Siret());

        $this->assertNoViolation();
    }

    /**
     * An INSEE outage is not the shop's fault: it is logged, and the number is
     * left alone rather than reported as invalid.
     */
    public function testAnInseeOutageIsLoggedAndLetsTheNumberThrough(): void
    {
        $this->response = new MockResponse('Service Unavailable', ['http_code' => 503]);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Service Unavailable');

        $this->validator->validate('81146060900018', new Siret());

        $this->assertNoViolation();
    }
}
