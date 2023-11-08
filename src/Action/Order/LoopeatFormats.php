<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\LoopeatFormats as LoopeatFormatsObject;
use AppBundle\Api\Dto\LoopeatFormat;
use Sylius\Component\Order\Model\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Entity\ReusablePackagings;
use AppBundle\Entity\ReusablePackaging;
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
                        'quantity' => $this->getQuantity($data, $item, $reusablePackaging, $reusablePackaging->getReusablePackaging()),
                    ];
                }

                $output->items[] = $format;
            }
        }

        return $output;
    }

    private function getQuantity(
        OrderInterface $order,
        OrderItemInterface $item,
        ReusablePackagings $reusablePackaging,
        ReusablePackaging $pkg): float
    {
        $pkgData = $pkg->getData();
        $loopeatDeliver = $order->getLoopeatDeliver();
        if (isset($loopeatDeliver[$item->getId()])) {
            foreach ($loopeatDeliver[$item->getId()] as $loopeatDeliverFormat) {
                if ($loopeatDeliverFormat['format_id'] === $pkgData['id']) {

                    return $loopeatDeliverFormat['quantity'];
                }
            }
        }

        return ceil($reusablePackaging->getUnits() * $item->getQuantity());
    }
}
