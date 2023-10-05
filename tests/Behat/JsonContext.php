<?php

namespace Tests\Behat;

use Coduo\PHPMatcher\PHPMatcher;
use Behat\Gherkin\Node\PyStringNode;
use Behatch\Context\JsonContext as BaseJsonContext;

class JsonContext extends BaseJsonContext
{
    /**
     * @Then the JSON should match:
     */
    public function theJsonShouldMatch(PyStringNode $string)
    {
        $expectedJson = $string->getRaw();
        $responseJson = $this->httpCallResultPool->getResult()->getValue();

        if (null === $expectedJson) {
            throw new \RuntimeException("Can not convert given JSON string to valid JSON format.");
        }

        $matcher = new PHPMatcher();
        $match = $matcher->match($responseJson, $expectedJson);

        if ($match !== true) {
            throw new \RuntimeException(sprintf("Expected JSON doesn't match response JSON.\n%s",
                (string) $matcher->error()));
        }
    }
}
