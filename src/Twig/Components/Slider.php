<?php

namespace AppBundle\Twig\Components;

use Carbon\Carbon;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Slider
{
    public $slides;

    public function getFilteredSlides(): array
    {
        return array_values(array_filter($this->slides, function ($s) {

            if (!isset($s['expiresAt']) || !$s['expiresAt']) {
                return true;
            }

            $now = Carbon::now();
            $expiresAt = new \DateTime($s['expiresAt']);

            return $now < $expiresAt;
        }));
    }

}
