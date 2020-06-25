<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\Task\CreateImage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ApiResource(iri="http://schema.org/MediaObject",
 *   attributes={
 *     "normalization_context"={"groups"={"task_image"}}
 *   },
 *   itemOperations={
 *     "get"
 *   },
 *   collectionOperations={
 *     "post"={
 *         "method"="POST",
 *         "controller"=CreateImage::class,
 *         "access_control"="is_granted('ROLE_ADMIN') or is_granted('ROLE_COURIER')",
 *         "defaults"={"_api_receive"=false},
 *     },
 * })
 * @Vich\Uploadable
 */
class TaskImage
{
    /**
     * @Groups({"task"})
     */
    private $id;

    private $task;

    /**
     * @Vich\UploadableField(mapping="task_image", fileNameProperty="imageName")
     * @Assert\File(
     *   maxSize = "5M",
     *   mimeTypes = {"image/jpg", "image/jpeg", "image/png"}
     * )
     * @var File
     */
    private $file;

    /**
     * @Groups({"task_image"})
     */
    private $imageName;

    private $createdAt;

    public function getId()
    {
        return $this->id;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|UploadedFile|null $image
     */
    public function setFile(File $image = null)
    {
        $this->file = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->createdAt = new \DateTimeImmutable();
        }

        return $this;
    }

    /**
     * @return File|null
     */
    public function getFile()
    {
        return $this->file;
    }

    public function setImageName($imageName)
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getImageName()
    {
        return $this->imageName;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function setTask($task)
    {
        $this->task = $task;

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
