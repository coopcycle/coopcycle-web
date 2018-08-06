<?php

namespace AppBundle\Service;

use AppBundle\Entity\ApiUser;
use Elastica\Client;
use Elastica\Document;
use Elastica\Query;
use Elastica\Query\MultiMatch;
use Elastica\Query\BoolQuery;
use FOS\UserBundle\Doctrine\UserManager;
use Symfony\Component\Yaml\Yaml;

class SearchManager
{
    private $client;
    private $userManager;
    private $prefix;
    private $settingsFilename;
    private $settings;

    const USER_DOCUMENT_TYPE = 'user';

    public function __construct(
        Client $client,
        UserManager $userManager,
        $prefix,
        $settingsFilename)
    {
        $this->client = $client;
        $this->userManager = $userManager;
        $this->prefix = $prefix;
        $this->settingsFilename = $settingsFilename;
    }

    public function createDocumentFromUser(ApiUser $user)
    {
        // FIXME Types are deprecated, to be removed in Elastic 7
        return new Document($user->getId(), [
            'username' => $user->getUsername(),
            'firstname' => $user->getGivenName(),
            'lastname' => $user->getFamilyName(),
        ], self::USER_DOCUMENT_TYPE);
    }

    public function getUsersIndex()
    {
        if (!$this->settings) {
            $this->settings = Yaml::parse(file_get_contents($this->settingsFilename));
        }

        $index = $this->client->getIndex(sprintf('%s:users', $this->prefix));

        if (!$index->exists()) {
            $index->create($this->settings, true);
        }

        return $index;
    }

    public function searchUsers($q)
    {
        $match = new MultiMatch();
        $match->setQuery($q);
        $match->setFields(['lastname', 'firstname', 'username']);
        $match->setFuzziness('AUTO');

        $bool = new BoolQuery();
        $bool->addShould($match);

        $elasticaQuery = new Query($bool);
        $elasticaQuery->setSize(10);

        return $this->getUsersIndex()->search($elasticaQuery);
    }
}
