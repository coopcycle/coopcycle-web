<?php

namespace AppBundle\Action\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Customer\CustomerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Nucleos\UserBundle\Util\CanonicalFieldsUpdater;
use phpcent\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Exception\ValidatorException;

class AddPlayer
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CanonicalFieldsUpdater $canonicalFieldsUpdater,
        private JWSProviderInterface $JWSProvider,
        private IriConverterInterface $iriConverter,
        private ContainerInterface $container,
        private Client $centrifugoClient,
    )
    { }

    public function __invoke(Request $request, Order $data)
    {
        // Parse json content
        $content = $request->getContent();
        $body = [];
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        // Generate or get a customer
        $customer = $this->getCustomer($body);

        // Validate if the invitation slug is real
        $invitation = $data->getInvitation();
        if (is_null($invitation) || $invitation->getSlug() !== $body['slug']) {
            throw new NotFoundHttpException("Invitation not found");
        }

        $order = $this->iriConverter->getIriFromItem($data);
        $player = $this->iriConverter->getIriFromItem($customer);

        // Generate X-Player-Token
        $jws = $this->JWSProvider->create([
            'order' => $order,
            'player' => $player
        ])->getToken();

        // Generate the response
        return new JsonResponse([
            'token' => $jws,
            'player' => $player,
            'centrifugo' => [
                'token' => $this->centrifugoClient->generateConnectionToken($data->getId(), time() + 6 * 3600),
                'channel' => sprintf('%s_order_events#%d', $this->container->getParameter('centrifugo_namespace'), $data->getId())
            ]
        ], 200);
    }

    /**
     * @param array $body
     * @return CustomerInterface
     */
    private function getCustomer(array $body): CustomerInterface
    {
        if (
            !array_key_exists('email', $body) ||
            !array_key_exists('name', $body) ||
            !array_key_exists('slug', $body)
        ) {
            throw new BadRequestHttpException();
        }

        $emailCanonical = $this->canonicalFieldsUpdater->canonicalizeEmail($body['email']);

        //FEAT: Maybe there is a way to validate it in a better way with API Platform
        if (!filter_var($emailCanonical, FILTER_VALIDATE_EMAIL)) {
            throw new ValidatorException(sprintf("[%s] is not a valid email", $body['email']));
        }

        $customer =
            $this->entityManager->getRepository(CustomerInterface::class)->findOneBy(['emailCanonical' => $emailCanonical]);

        if (null === $customer) {
            $customer = new Customer();
            $customer->setEmail($body['email']);
            $customer->setFirstName($body['name']);
            $customer->setEmailCanonical($emailCanonical);

            $this->entityManager->persist($customer);
            $this->entityManager->flush();
        }
        return $customer;
    }
}
