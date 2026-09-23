<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Incident\IncidentImage;
use AppBundle\Sylius\Taxation\TaxesHelper;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Service\FilterService;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use Symfony\Component\Routing\Attribute\Route;

trait IncidentTrait {

    public function incidentListAction(Request $request)
    {
        return $this->render($request->attributes->get('template'), $this->auth([
            'layout' => $request->attributes->get('layout'),
        ]));
    }

    public function incidentAction($id, Request $request,
        EntityManagerInterface $entityManager,
        StateMachineFactoryInterface $stateMachineFactory,
        TaxesHelper $taxesHelper) {
        /** @var ?Incident $incident */
        $incident = $entityManager->getRepository(Incident::class)->find($id);

        if (!$incident) {
            throw $this->createNotFoundException();
        }

        /** @var ?Delivery $delivery */
        $delivery = $incident->getTask()->getDelivery();

        $transporterEnabled = $delivery?->getStore()?->isTransporterEnabled() ?? false;

        $isLastmile = !is_null($delivery?->getStore());

        $order = $delivery?->getOrder();

        // An order can be refunded if at least one of its payments was actually captured.
        // Orders invoiced later (i.e B2B last mile) have payments stuck in state "new".
        $isRefundable = false;
        if (null !== $order) {
            foreach ($order->getPayments() as $payment) {
                if ($stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->can(PaymentTransitions::TRANSITION_REFUND)) {
                    $isRefundable = true;
                    break;
                }
            }
        }

        // The rate applied to incident price differences, see OrderTaxesProcessor
        $serviceTaxRate = $taxesHelper->getServiceTaxRate();

        return $this->render($request->attributes->get('template'), $this->auth([
            'incident' => $incident,
            'delivery' => $delivery,
            'order' => $order,
            'store' => $delivery?->getStore(),
            'transporterEnabled' => $transporterEnabled,
            'isLastmile' => $isLastmile,
            'isRefundable' => $isRefundable,
            'serviceTaxRate' => is_null($serviceTaxRate) ? null : [
                'amount' => $serviceTaxRate->getAmount(),
                'includedInPrice' => $serviceTaxRate->isIncludedInPrice(),
            ],
        ]));
    }
}
