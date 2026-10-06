<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Pricing\Matrix\GeneratedRuleGuard;
use AppBundle\Pricing\Matrix\PricingMatrixRuleGenerator;
use AppBundle\Service\PricingRuleSetManager;
use Doctrine\ORM\EntityManagerInterface;

class PricingRuleSetProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $decorated,
        private readonly EntityManagerInterface $entityManager,
        private readonly PricingRuleSetManager $pricingRuleSetManager,
        private readonly GeneratedRuleGuard $generatedRuleGuard,
        private readonly PricingMatrixRuleGenerator $pricingMatrixRuleGenerator,
    ) {
    }

    public function process(
        $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ) {
        if ($data instanceof PricingRuleSet) {
            // Matrix rules stay ahead of the hand-written ones, whatever order was sent.
            //
            // This runs before anything computes a change set. Doctrine snapshots an
            // entity the first time its change set is computed, and a rule queued for
            // insertion is then written from that change set alone: moving it
            // afterwards leaves a change set holding nothing but its new position, and
            // the INSERT goes out with one value for eight columns.
            $this->pricingMatrixRuleGenerator->reorder($data);

            // Rules generated from a matrix are rewritten whenever that matrix is saved,
            // so an edit made through this endpoint would silently disappear
            $violations = $this->generatedRuleGuard->findViolations($data);
            if (count($violations) > 0) {
                throw new ValidationException($violations);
            }

            // Handle ProductOption creation/update for each rule before processing
            $this->processRulesChanges($data);
        }

        // Process the PricingRuleSet using the default processor
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        return $result;
    }

    private function processRulesChanges(PricingRuleSet $pricingRuleSet): void
    {
        $rules = $pricingRuleSet->getRules();

        foreach ($rules as $rule) {
            $nameInput = $rule->getNameInput();
            if ($nameInput !== null && !empty(trim($nameInput))) {
                $name = trim($nameInput);
            } else {
                $name = null;
            }

            if ($rule->getId()) {
                $uow = $this->entityManager->getUnitOfWork();
                $uow->computeChangeSets();
                $changeSet = $uow->getEntityChangeSet($rule);
            }

            // Skip rules for which neither name nor price has changed
            // $name === $rule->getName() compare name provided in request with current name in database
            if ($name === $rule->getName() && (isset($changeSet) && !isset($changeSet['price']))) {
                continue;
            }

            $this->pricingRuleSetManager->updateProductOptionValues($rule, $name);
        }
    }
}
