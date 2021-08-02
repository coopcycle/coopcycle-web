<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Api\Resource\Centrifugo;
use phpcent\Client as CentrifugoClient;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CentrifugoToken
{
    use TokenStorageTrait;

    private $centrifugo;
    private $centrifugoNamespace;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        CentrifugoClient $centrifugo,
        string $centrifugoNamespace)
    {
        $this->tokenStorage = $tokenStorage;
        $this->centrifugo = $centrifugo;
        $this->centrifugoNamespace = $centrifugoNamespace;
    }

    public function __invoke(): Centrifugo
    {
        $data = new Centrifugo();
        $data->token =
            $this->centrifugo->generateConnectionToken($this->getUser()->getUsername(), (time() + 3600));
        $data->namespace = $this->centrifugoNamespace;

        return $data;
    }
}
