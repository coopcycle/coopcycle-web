<?php

namespace AppBundle\Annotation;

use Doctrine\Common\Annotations\Annotation\Attribute;

#[Attribute(\Attribute::TARGET_CLASS|\Attribute::TARGET_METHOD)]
final class HideSoftDeleted
{
}
