<?php

namespace AppBundle\Utils;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Twig\Environment as TwigEnvironment;

class OrderTextEncoder implements EncoderInterface
{
    public function __construct(private TwigEnvironment $templating)
    {}

    public function encode($data, $format, array $context = array())
    {
        return $this->templating->render('order/summary.txt.twig', [
            'order' => $data
        ]);
    }

    public function supportsEncoding($format)
    {
        return 'txt' === $format;
    }
}
