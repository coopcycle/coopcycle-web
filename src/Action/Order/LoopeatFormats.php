<?php

namespace AppBundle\Action\Order;

use AppBundle\Api\Dto\LoopeatFormats as LoopeatFormatsObject;
use AppBundle\Api\Dto\LoopeatFormat;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Validator\Constraints\LoopeatStock as AssertLoopeatStock;
use Sylius\Component\Order\Model\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Entity\ReusablePackagings;
use AppBundle\Entity\ReusablePackaging;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LoopeatFormats
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private LoopEatClient $loopeatClient,
        private ValidatorInterface $validator)
    {}

    public function __invoke($data)
    {
        $output = new LoopeatFormatsObject();

        $restaurantContainers = $this->loopeatClient->getRestaurantContainers($data);

        foreach ($data->getItems() as $item) {

            $format = new LoopeatFormat();
            $format->orderItem = $this->normalizer->normalize($item, 'jsonld', ['groups' => ['order']]);

            $product = $item->getVariant()->getProduct();

            if ($product->isReusablePackagingEnabled()) {

                if (!$product->hasReusablePackagings()) {
                    continue;
                }

                $violations = $this->validator->validate($item, new AssertLoopeatStock());

                foreach ($product->getReusablePackagings() as $reusablePackaging) {

                    $packagingData = $reusablePackaging->getReusablePackaging()->getData();

                    $originalQuantity = ceil($reusablePackaging->getUnits() * $item->getQuantity());
                    $overridenQuantity = $this->getQuantity($data, $item, $reusablePackaging, $reusablePackaging->getReusablePackaging());

                    $missingQuantity = 0;
                    foreach ($violations as $violation) {
                        /** @var ConstraintViolation $violation */
                        if ($violation->getCause() === $reusablePackaging->getReusablePackaging()) {
                            $missingQuantity = $violation->getInvalidValue();
                            break;
                        }
                    }

                    $format->formats[] = [
                        'format_id' => $packagingData['id'],
                        'format_name' => $reusablePackaging->getReusablePackaging()->getName(),
                        'quantity' => $overridenQuantity,
                        'missing_quantity' => $missingQuantity,
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
