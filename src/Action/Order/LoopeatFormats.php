<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\LoopeatFormats as LoopeatFormatsObject;
use AppBundle\Api\Dto\LoopeatFormat;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class LoopeatFormats
{
    public function __construct(private NormalizerInterface $normalizer)
    {}

    public function __invoke($data)
    {
        $output = new LoopeatFormatsObject();

        foreach ($data->getItems() as $item) {

            $format = new LoopeatFormat();
            $format->orderItem = $this->normalizer->normalize($item, 'jsonld', ['groups' => ['order']]);

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                if (!$product->hasReusablePackagings()) {
                    continue;
                }

                foreach ($product->getReusablePackagings() as $reusablePackaging) {
                    $packagingData = $reusablePackaging->getReusablePackaging()->getData();
                    $format->formats[] = [
                        'format_id' => $packagingData['id'],
                        'format_name' => $reusablePackaging->getReusablePackaging()->getName(),
                        'quantity' => ($item->getQuantity() * $reusablePackaging->getUnits()),
                    ];
                }

                $output->items[] = $format;
            }
        }

        return $output;
    }
}
