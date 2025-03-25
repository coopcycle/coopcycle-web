<?php

namespace AppBundle\Annotation;

use Attribute;

#[Attribute(\Attribute::TARGET_CLASS|\Attribute::TARGET_METHOD)]
final class HideSoftDeleted
{
}
