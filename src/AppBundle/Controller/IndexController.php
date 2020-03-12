<?php

namespace AppBundle\Controller;

use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Entity\RestaurantRepository;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;

class IndexController extends AbstractController
{
    use UserTrait;

    const MAX_RESULTS = 3;

    /**
     * @HideSoftDeleted
     */
    public function indexAction(PublisherInterface $publisher, RestaurantRepository $repository)
    {
        $update = new Update(
            'http://example.com/user/admin',
            json_encode(['status' => 'OutOfStock'])
        );

        // The Publisher service is an invokable object
        $publisher($update);

        $restaurants = $repository->findAllSorted();

        $username = $this->getUser()->getUsername();
        $token = (new Builder())
            // set other appropriate JWT claims, such as an expiration date
            ->withClaim('mercure', ['subscribe' => ["http://example.com/user/$username"]]) // could also include the security roles, or anything else
            ->getToken(new Sha256(), new Key('!ChangeMe!')); // don't forget to set this parameter! Test value: aVerySecretKey

        // $response = $this->json(['@id' => '/demo/books/1', 'availability' => 'https://schema.org/InStock']);

        $response = new Response();

        $response->headers->set(
            'set-cookie',
            sprintf('mercureAuthorization=%s; path=/.well-known/mercure; httponly; SameSite=strict', $token)
        );

        return $this->render('@App/index/index.html.twig', array(
            'restaurants' => array_slice($restaurants, 0, self::MAX_RESULTS),
            'max_results' => self::MAX_RESULTS,
            'show_more' => count($restaurants) > self::MAX_RESULTS,
            'addresses_normalized' => $this->getUserAddresses(),
        ), $response);
    }
}
