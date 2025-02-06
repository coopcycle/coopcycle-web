<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Task;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class SiretValidator extends ConstraintValidator
{
    public function __construct(private HttpClientInterface $inseeClient)
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

            $this->inseeClient->request('GET', sprintf('siret/%s', $value));

        } catch (ClientException $e) {

            $data = $e->getResponse()->toArray(false);

            $this->context->buildViolation($data['header']['message'])
                ->addViolation();

        } catch (ServerException $e) {
            // Die in silence
        }
    }
}

