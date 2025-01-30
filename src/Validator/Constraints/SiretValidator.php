<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Entity\Task;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintValidator;

class SiretValidator extends ConstraintValidator
{
    public function __construct(
        private HttpClientInterface $inseeClient,
        private string $apiKey,
        private string $apiSecret)
    {}

    public function validate($value, Constraint $constraint)
    {
        $value = trim($value);

        if (empty($value)) {
            return;
        }

        if (empty($this->apiKey) && empty($this->apiSecret)) {
            return;
        }

        // Remove spaces
        $value = preg_replace('/\s+/', '', $value);

        try {

            $this->inseeClient->request('GET', sprintf('entreprises/sirene/V3.11/siret/%s', $value), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getToken()),
                ]
            ]);

        } catch (ClientException $e) {

            $data = $e->getResponse()->toArray(false);

            $this->context->buildViolation($data['header']['message'])
                ->addViolation();
        }

    }

    /**
     * @see https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/help.jag
     */
    private function getToken(): string
    {
        $base64 = base64_encode(sprintf('%s:%s', $this->apiKey, $this->apiSecret));

        $response = $this->inseeClient->request('POST', 'token', [
            'body' => [
                'grant_type' => 'client_credentials',
                'validity_period' => '604800',
            ],
            'headers' => [
                'Authorization' => sprintf('Basic %s', $base64),
            ]
        ]);

        $data = $response->toArray();

        return $data['access_token'];
    }
}

