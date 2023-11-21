<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Action\NotFoundAction;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "get": {
 *       "method"="GET"
 *     },
 *   },
 *   itemOperations={
 *     "get": {
 *       "method"="GET"
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

    public string $contents = '';
}
