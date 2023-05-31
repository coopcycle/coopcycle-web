<?php

namespace AppBundle\Controller\Utils;

trait InjectAuthTrait
{
    private function auth(array $options = []): array {

        $user = $this->getUser();

        return array_merge($options, [ '_auth' => [
            'user' => $user,
            'jwt' => $user ? $this->JWTTokenManager->create($user) : null
        ]]);
    }
}
