<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Action\NotFoundAction;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"data_schema_file"}}
 *   },
 *   collectionOperations={
 *     "get": {
 *       "method"="GET"
 *     },
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET",
 *       "normalization_context"={"groups"={"data_schema_file_contents"}},
 *     },
 *   }
 * )
 */
final class CubeDataSchemaFile
{
	/**
     * @var string
     *
     * @ApiProperty(identifier=true)
     */
    public string $id;

    /**
     * @Groups({"data_schema_file"})
     */
    public string $extension;

    /**
     * @Groups({"data_schema_file_contents"})
     */
    public string $filename;

    /**
     * @Groups({"data_schema_file_contents"})
     */
    public string $contents = '';

    public function __construct($filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        $this->id = basename($filename, ".{$extension}");
        $this->extension = $extension;
        $this->filename = $filename;
    }
}
