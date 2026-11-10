<?php

declare(strict_types=1);

namespace AppBundle\Service;

use AppBundle\Entity\Package;
use AppBundle\Entity\PackageSet;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\ItemNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Turns a package mapping from the config (an outside type of goods => a
 * Package IRI, e.g. "/api/packages/1") into Package entities. Used by the
 * transporters and the RDC connections.
 *
 * IRIs are used rather than short codes because short codes are not unique
 * (several packages can share the same one), which made the mapping ambiguous.
 */
class PackageMappingResolver
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    ) {}

    /**
     * An entry that doesn't resolve to a package is skipped with a warning.
     *
     * @param array<string,mixed> $mapping From the config, so the IRIs are not checked yet
     * @param LoggerInterface $logger Each integration logs on its own channel
     * @param array<string,mixed> $context Added to the warnings
     * @param ?PackageSet $packageSet The packages the store offers: a package
     *   from another set is kept, but the back office won't show it
     * @return array<string,Package>
     */
    public function resolve(
        array $mapping,
        LoggerInterface $logger,
        array $context = [],
        ?PackageSet $packageSet = null,
    ): array {
        $resolved = [];
        foreach ($mapping as $type => $iri) {
            try {
                $package = is_string($iri) ? $this->iriConverter->getResourceFromIri($iri) : null;
            } catch (InvalidArgumentException|ItemNotFoundException $e) {
                $package = null;
            }

            if (!$package instanceof Package) {
                $logger->warning(
                    sprintf(
                        'Package mapping IRI "%s" for type "%s" is not a package, it is ignored',
                        is_string($iri) ? $iri : get_debug_type($iri),
                        $type
                    ),
                    $context
                );
                continue;
            }

            if (!is_null($packageSet) && $package->getPackageSet()?->getId() !== $packageSet->getId()) {
                $logger->warning(
                    sprintf('Package mapping IRI "%s" for type "%s" is not in the package set of the store', $iri, $type),
                    $context
                );
            }

            $resolved[(string) $type] = $package;
        }

        return $resolved;
    }
}
