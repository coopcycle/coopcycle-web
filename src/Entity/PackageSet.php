<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\PackageSet\Applications;
use AppBundle\Validator\Constraints\PackageSetDelete as AssertCanDelete;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    itemOperations: [
        'get' => [
            'method' => 'GET',
            'access_control' => "is_granted('ROLE_ADMIN')",
            'controller' => NotFoundAction::class
        ],
        'delete' => [
            'method' => 'DELETE',
            'security' => "is_granted('ROLE_ADMIN')",
            'validation_groups' => ['deleteValidation']
        ],
        'applications' => [
            'method' => 'GET',
            'path' => '/package_sets/{id}/applications',
            'controller' => Applications::class,
            'security' => "is_granted('ROLE_ADMIN')",
            'openapi_context' => ['summary' => 'Get the objects to which this pricing rule set is applied']]
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
