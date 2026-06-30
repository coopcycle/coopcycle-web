<?php

namespace AppBundle\Action;

use AppBundle\Api\Resource\Centrifugo;
use phpcent\Client as CentrifugoClient;
use Symfony\Bundle\SecurityBundle\Security;

class CentrifugoToken
{
    private $centrifugo;
    private $centrifugoNamespace;

    public function __construct(
        private Security $security,
        CentrifugoClient $centrifugo,
        string $centrifugoNamespace)
    {
        $this->centrifugo = $centrifugo;
        $this->centrifugoNamespace = $centrifugoNamespace;
    }

    public function __invoke(): Centrifugo
    {
        $data = new Centrifugo();
        $data->token =
            $this->centrifugo->generateConnectionToken($this->security->getUser()->getUsername(), (time() + 3600));
        $data->namespace = $this->centrifugoNamespace;

        return $data;
    }
}
