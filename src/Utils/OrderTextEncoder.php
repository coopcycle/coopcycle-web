<?php

namespace AppBundle\Utils;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Twig\Environment as TwigEnvironment;

class OrderTextEncoder implements EncoderInterface
{
    public function __construct(private TwigEnvironment $templating)
    {}

    public function encode($data, $format, array $context = []): string
    {
        return $this->templating->render('order/summary.txt.twig', [
            'order' => $data
        ]);
    }

    public function supportsEncoding($format): bool
    {
        return 'txt' === $format;
    }
}
