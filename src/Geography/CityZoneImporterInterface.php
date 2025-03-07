<?php

namespace AppBundle\Geography;

use AppBundle\Entity\CityZone;

interface CityZoneImporterInterface
{
	/**
	 * @return CityZone[]
	 */
	public function import(string $url, array $options = []): array;
}
