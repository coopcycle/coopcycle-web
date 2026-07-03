<?php

namespace AppBundle\Twig\Components;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryForm as DeliveryFormEntity;
use AppBundle\Form\DeliveryEmbedType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class DeliveryForm
{
    public string $form;
    public string $title = '';
    public string $text = '';

    public function __construct(
        private FormFactoryInterface $formFactory,
        private IriConverterInterface $iriConverter)
    {}

    public function getDeliveryForm(): ?DeliveryFormEntity
    {
        $qb = $this->entityManager
            ->getRepository(DeliveryFormEntity::class)
            ->createQueryBuilder('f');

        $qb->where('f.showHomepage = :showHomepage');
        $qb->setParameter('showHomepage', ($showHomepage = true));
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function createDeliveryFormView(DeliveryFormEntity $deliveryForm): FormView
    {
        return $this->formFactory->createNamed('delivery', DeliveryEmbedType::class, new Delivery(), [
            'with_weight'      => $deliveryForm->getWithWeight(),
            'with_vehicle'     => $deliveryForm->getWithVehicle(),
            'with_time_slot'   => $deliveryForm->getTimeSlot(),
            'with_package_set' => $deliveryForm->getPackageSet(),
        ])->createView();
    }

    public function getResourceFromIri(string $iri): ?DeliveryFormEntity
    {
        return $this->iriConverter->getResourceFromIri($iri);
    }
}
