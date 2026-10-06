<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Delivery\PricingMatrix;
use AppBundle\Pricing\Matrix\PricingMatrixRuleGenerator;
use AppBundle\Service\PricingRuleSetManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Saving a matrix is what generates its rules: the grid is the only way to edit them.
 */
class PricingMatrixProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $decorated,
        private readonly EntityManagerInterface $entityManager,
        private readonly PricingMatrixRuleGenerator $pricingMatrixRuleGenerator,
        private readonly PricingRuleSetManager $pricingRuleSetManager,
    ) {
    }

    public function process(
        $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ) {
        if ($data instanceof PricingMatrix) {
            $this->pricingMatrixRuleGenerator->generate($data);
            $this->syncProductOptionValues($data);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Same contract as for a hand-written rule: a ProductOptionValue is never modified,
     * a new one is created whenever the price or the name changes, because past orders
     * reference the old one. A cell that did not change is therefore left alone.
     */
    private function syncProductOptionValues(PricingMatrix $matrix): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        foreach ($matrix->getRules() as $rule) {
            $nameInput = $rule->getNameInput();
            $name = (null !== $nameInput && '' !== trim($nameInput)) ? trim($nameInput) : null;

            if (null !== $rule->getId()) {
                $changeSet = $unitOfWork->getEntityChangeSet($rule);

                if ($name === $rule->getName() && !isset($changeSet['price'])) {
                    continue;
                }
            }

            $this->pricingRuleSetManager->updateProductOptionValues($rule, $name);
        }
    }
}
