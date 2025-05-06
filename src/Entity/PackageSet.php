<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Action\PackageSet\Applications;
use AppBundle\Api\State\ValidationAwareRemoveProcessor;
use AppBundle\Validator\Constraints\PackageSetDelete as AssertCanDelete;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(
            security: 'is_granted(\'ROLE_ADMIN\')',
            controller: NotFoundAction::class
        ),
        new Delete(
            security: 'is_granted(\'ROLE_ADMIN\')',
            processor: ValidationAwareRemoveProcessor::class,
            validationContext: ['groups' => ['deleteValidation']]
        ),
        new Get(
            uriTemplate: '/package_sets/{id}/applications',
            controller: Applications::class,
            security: 'is_granted(\'ROLE_ADMIN\')',
            openapiContext: ['summary' => 'Get the objects to which this pricing rule set is applied']
        ),
        new Post(),
        new GetCollection()
    ]
)]
#[AssertCanDelete(groups: ['deleteValidation'])]
class PackageSet
{
    use Timestampable;

    protected $id;
    protected $name;

    #[Assert\Unique(normalizer: 'AppBundle\Entity\Package::getPackageName', message: 'form.package_set.duplicatePackageNames')]
    protected $packages;

    public function __construct()
    {
        $this->packages = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPackages()
    {
        $filtered = $this->packages->filter(
            function ($package) {
                return !$package->isDeleted();
            }
        );

        // reset index after filtering
        return new ArrayCollection(array_values($filtered->toArray()));
    }

    /**
     * @param mixed $packages
     *
     * @return self
     */
    public function setPackages($packages)
    {
        $this->packages = $packages;

        return $this;
    }

    public function addPackage($package)
    {
        $package->setPackageSet($this);

        $this->packages->add($package);
    }

    public function removePackage($package)
    {
        $this->packages->removeElement($package);
    }
}
