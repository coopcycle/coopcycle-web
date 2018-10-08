<?php

namespace AppBundle\Utils;

use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class OrderTextEncoder implements EncoderInterface
{
    public function __construct(TwigEngine $templating)
    {
        $this->templating = $templating;
    }

    public function encode($data, $format, array $context = array())
    {
        return $this->templating->render('@App/order/summary.txt.twig', [
            'order' => $data
        ]);
    }

    public function supportsEncoding($format)
    {
        return 'txt' === $format;
    }
}
