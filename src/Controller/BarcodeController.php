<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Service\TaskManager;
use AppBundle\Utils\Barcode\BarcodeUtils;
use Doctrine\Persistence\ManagerRegistry;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment as TwigEnvironment;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class BarcodeController extends AbstractController
{
    public function __construct(
        private TwigEnvironment $twig,
        private HttpClientInterface $browserlessClient,
        private ManagerRegistry $doctrine,
        private TaskManager $taskManager,
        private BarcodeUtils $barcodeUtils,
    )
    { }



    /**
     * @Route("/api/barcode", name="barcode_api")
     */
    public function barcodeAction(
        IriConverterInterface $iriConverter,
        NormalizerInterface $normalizer,
        Request $request
    ): Response
    {

        if (!$request->get('code', null)) {
           return $this->json(['error' => 'No code provided.'], 400);
        }

        $barcode = $this->barcodeUtils::parse($request->get('code'));

        /** @var Task $task */
        $task = $this->getDoctrine()
            ->getRepository(Task::class)
            ->findByBarcode($barcode->getRawBarcode());

        if (is_null($task)) {
            return $this->json(['error' => 'No data found.'], 404);
        }

        $client_action = null;
        if ($task->isAssignedTo($this->getUser())) {
            $client_action = 'ask_unassign';
        } elseif ($task->isAssigned()) {
            $client_action = 'ask_assign';
        }

        if (!$task->isAssignedTo($this->getUser())) {
            if (is_null($task->getDelivery())) {
                $task->assignTo($this->getUser());
            } else {
                $task->getDelivery()->assignTo($this->getUser());
            }
        }

        $this->taskManager->scan($task);
        $this->doctrine->getManager()->flush();

        return $this->json([
            "ressource" => $iriConverter->getIriFromItem($task),
            "client_action" => $client_action,
            "entity" => $normalizer->normalize($task, null, [
                'groups' => ['task', 'delivery', 'package', 'address', 'barcode']
            ])
        ]);
    }

    /**
     * @Route("/tasks/label", name="task_label_pdf")
     */
    public function viewLabelAction(
        PhoneNumberUtil $phoneUtil,
        BarcodeGeneratorSVG $generator,
        Request $request
    ): Response {

        if (!$request->get('code', null)) {
            return $this->json(['error' => 'No code provided.'], 400);
        }

        $barcode = $this->barcodeUtils::parse($request->get('code'));

        $phoneUtil = $phoneUtil::getInstance();
        /** @var Task $ressource */
        $ressource = $this->getDoctrine()->getRepository(Task::class)->find($barcode->getEntityId());

        $package = null;
        if ($barcode->isContainsPackages()) {
            $package = $ressource->getPackages()
                ->filter(fn($p) => $p->getId() === $barcode->getPackageTaskId())
                ->first()
                ?->getPackage()
                ?->getName();
        }


        $phone = null;
        if (!is_null($ressource->getAddress()->getTelephone())) {
            $phone = $phoneUtil->format($ressource->getAddress()->getTelephone(), PhoneNumberFormat::INTERNATIONAL);
        }

        $from = $ressource->getDelivery()?->getPickup()?->getAddress();

        $barcodeSVG = $generator->getBarcode(
            barcode: $barcode->getRawBarcode(),
            type: $generator::TYPE_CODE_128,
            widthFactor: 1.4,
            height: 55
        );

        $html = $this->twig->render('task/label.pdf.twig', [
            'from' => $from,
            'task' => $ressource,
            'phone' => $phone,
            'barcode' => $barcodeSVG,
            'barcode_raw' => $barcode->getRawBarcode(),
            'package' => $package,
            'currentPackage' => $barcode->getPackageTaskIndex(),
            'totalPackages' => $ressource->totalPackages()
        ]);

        $response = $this->browserlessClient->request('POST', '/pdf', [
            'json' => [
                'html' => $html,
                'options' => [
                    'displayHeaderFooter' => false,
                    'printBackground' => false,
                    'format' => 'A5'
                ]
            ]
        ]);

        $pdf = (string) $response->getContent();
        return new Response($pdf, 200, [
            'Cache-Control' => 'private',
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="label_%s.pdf"', $barcode->getRawBarcode())
        ]);
    }
}
