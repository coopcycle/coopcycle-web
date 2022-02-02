<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nonprofit organisation
 *
 * @ApiResource(
 *     iri="http://schema.org/NGO",
 *     shortName="Nonprofit",
 * )
 */
class Nonprofit
{

    use Timestampable;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     * @Assert\NotBlank
     */
    protected string $name;

    /**
     * @var bool
     */
    protected bool $enabled;

    /**
     * @var string|null
     * @Assert\Url
     * @ApiProperty(iri="https://schema.org/URL")
     */
    protected ?string $url;

    /**
     * @var string|null
     */
    protected ?string $logoName;

    /**
     * @var string
     */
    protected string $description;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param string|null $url
     */
    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getLogoName(): ?string
    {
        return $this->logoName;
    }

    /**
     * @param string $logoName
     */
    public function setLogoName(string $logoName): void
    {
        $this->logoName = $logoName;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

}
