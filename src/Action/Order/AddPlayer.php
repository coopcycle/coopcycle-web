<?php

namespace AppBundle\Action\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
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
    {}

    public function __invoke(Request $request, Order $data)
    {

        // Parse json content
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        if (
            !isset($body) ||
            !array_key_exists('email', $body) ||
            !array_key_exists('slug', $body)
        ) {
            throw new BadRequestHttpException();
        }

        // Generate or get a customer
        $customer = $this->getCustomer($body["email"]);

        // Validate if the invitation slug is real
        $invitation = $data->getInvitation();
        if (is_null($invitation) || $invitation->getSlug() !== $body['slug']) {
            throw new NotFoundHttpException("Invitation not found");
        }

        //TODO: get centrifugo token
        $order = $this->iriConverter->getIriFromItem($data);
        $player = $this->iriConverter->getIriFromItem($customer);

        $jws = $this->JWSProvider->create([
            'order' => $order,
            'player' => $player
        ])->getToken();

        return new JsonResponse([
            'token' => $jws,
            'centrifugo' => [
                'token' => $this->centrifugoClient->generateConnectionToken($player, time() + 6 * 3600),
                'channel' => sprintf('%s_player_events#%d', $this->container->getParameter('centrifugo_namespace'), $data->getId())
            ]
        ], 200);
    }

    /**
     * @param $email
     * @return CustomerInterface
     */
    private function getCustomer($email): CustomerInterface
    {
        $emailCanonical = $this->canonicalFieldsUpdater->canonicalizeEmail($email);

        if (!filter_var($emailCanonical, FILTER_VALIDATE_EMAIL)) {
            throw new ValidatorException(sprintf("[%s] is not a valid email", $email));
        }

        $customer =
            $this->entityManager->getRepository(CustomerInterface::class)->findOneBy(['emailCanonical' => $emailCanonical]);

        if (null === $customer) {
            $customer = new Customer();
            $customer->setEmail($email);
            $customer->setEmailCanonical($emailCanonical);

            $this->entityManager->persist($customer);
            $this->entityManager->flush();
        }
        return $customer;
    }
}
