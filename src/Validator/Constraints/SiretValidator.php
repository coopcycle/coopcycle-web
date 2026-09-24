<?php

namespace AppBundle\Validator\Constraints;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SiretValidator extends ConstraintValidator
{
    public function __construct(
        private HttpClientInterface $inseeClient,
        private LoggerInterface $logger)
    {}

    public function validate($value, Constraint $constraint)
    {
        $value = trim($value);

        if (empty($value)) {
            return;
        }

        // Remove spaces
        $value = preg_replace('/\s+/', '', $value);

        try {

            // The client is lazy: request() only queues the call, and a 4xx/5xx
            // becomes an exception when the response is read. Reading the headers
            // is that read. Dropping the response without reading it would throw
            // just the same — its destructor checks the status code — but only as
            // a side effect of the object going out of scope inside this try, which
            // is both easy to break by holding on to the response and invisible to
            // static analysis (PHPStan reports both catches below as dead).
            $this->inseeClient->request('GET', sprintf('siret/%s', $value))->getHeaders();

        } catch (ClientException $e) {

            $data = $e->getResponse()->toArray(false);

            $this->context->buildViolation($data['header']['message'])
                ->addViolation();

        } catch (ServerException $e) {
            $this->logger->error($e->getResponse()->getContent(throw: false));
        }
    }
}
