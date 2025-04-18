<?php

namespace AppBundle\Controller;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Package;
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
        private TaskListProvider $taskListProvider
    )
    { }



    #[Route(path: '/api/barcode', name: 'barcode_api')]
    public function barcodeAction(
        IriConverterInterface $iriConverter,
        NormalizerInterface $normalizer,
        Request $request
    ): Response
    {

        //NOTE: Maybe some coops may want to restrict this to ROLE_DISPATCHER ?
        $this->denyAccessUnlessGranted('ROLE_COURIER');

        if (!$request->get('code', null)) {
           return $this->json(['error' => 'No code provided.'], 400);
        }

        $barcode = $this->barcodeUtils::parse($request->get('code'));

        /** @var ?Task $task */
        $task = $this->getDoctrine()
            ->getRepository(Task::class)
            ->findByBarcode($barcode->getRawBarcode());

        if (is_null($task)) {
            return $this->json(['error' => 'No data found.'], 404);
        }

        $clientAction = $this->determineClientAction($task);
        $this->handleTaskAssignment($task);

        $this->taskManager->scan($task);
        $this->doctrine->getManager()->flush();

        $iri = $iriConverter->getIriFromResource($task);
        return $this->json([
            "ressource" => $iri,
            "client_action" => $clientAction,

            /**
             * The action_token enables temporary elevated permissions for couriers.
             * It allows specific actions (like self-assignment) on the scanned task only.
             *
             * Generation:
             * - Uses BarcodeUtils::getToken/1 to get the label's token
             * - Hashes it with xxh3 algorithm for additional security
             * - Uses runtime secret key to prevent token guessing
             *
             * Security:
             * - Scoped to single task (token invalid for other tasks)
             * - Cannot be forged without access to runtime secret
             */
            "token_action" => hash('xxh3', BarcodeUtils::getToken($iri)),
            "entity" => $normalizer->normalize($task, null, [
                'groups' => ['task', 'delivery', 'package', 'address', 'barcode']
            ])
        ]);
    }

    /**
     * Determine the client action based on the current state of the task
     * Possible actions:
     * ask_to_assign: Will prompt the user to self-assign
     * ask_to_unassign: Will prompt the user to self-unassign
     * ask_to_complete: Will redirect the user to the complete page
     * ask_to_start_pickup: Will prompt the user to start the pickup
     * ask_to_complete_pickup: Will prompt the user to complete the pickup
     */
    private function determineClientAction(Task $task): ?array
    {

        // If the task is a delivery, we should prompt the user to start the pickup
        if (!is_null($task->getDelivery())) {
            /** @var Delivery $delivery */
            $delivery = $task->getDelivery();
            $pickup_status = $delivery->getPickup()?->getStatus();

            switch ($pickup_status) {
                case Task::STATUS_TODO:
                    return [ 'action' => 'ask_to_start_pickup', 'payload' => ['task_id' => $delivery->getPickup()->getId()] ];
                case Task::STATUS_DOING:
                    return [ 'action' => 'ask_to_complete_pickup', 'payload' => ['task_id' => $delivery->getPickup()->getId()] ];
            }

        }

        // If the task is already done or failed, we should not prompt the user
        if (in_array($task->getStatus(), [
            Task::STATUS_DONE,
            Task::STATUS_FAILED,
            Task::STATUS_CANCELLED])
        ) {
            return null;
        }

        // If the task in scanned while doing, we should redirect to the complete page
        if ($task->getStatus() === Task::STATUS_DOING) {
            return [ 'action' => 'ask_to_complete', 'payload' => ['task_id' => $task->getId()] ];
        }

        // If the task is assigned to the current user, we should prompt the user to self-unassign
        if ($task->isAssignedTo($this->getUser())) {
            return [ 'action' => 'ask_to_unassign', 'payload' => ['task_id' => $task->getId()] ];
        }

        // If the task is not assigned to the current user, we should prompt the user to self-assign
        if ($task->isAssigned()) {
            return [ 'action' => 'ask_to_assign', 'payload' => ['task_id' => $task->getId()] ];
        }

        return null;
    }

    /**
     * Assign the task/delivery to the current user
     * if the task is not assigned
     */
    private function handleTaskAssignment(Task $task): void
    {
        if ($task->isAssigned()) {
            return;
        }

        $assignable = $task->getDelivery()->getTasks() ?? [$task];
        array_walk($assignable, function (Task $task) {
            $tasklist = $this->taskListProvider->getTaskList($task, $this->getUser());
            $tasklist->appendTask($task);
            $this->doctrine->getManager()->persist($tasklist);
        });
    }


    #[Route(path: '/tasks/label', name: 'task_label_pdf')]
    public function viewLabelAction(
        PhoneNumberUtil $phoneUtil,
        BarcodeGeneratorSVG $generator,
        Request $request
    ): Response {

        if (!$request->get('code', null)) {
            return $this->json(['error' => 'No code provided.'], 400);
        }

        $barcode = $this->barcodeUtils::parse($request->get('code'));

        if ($request->get('token') !== BarcodeUtils::getToken($barcode)) {
            return $this->json(['error' => 'Invalid hash.'], 400);
        }

        $phoneUtil = $phoneUtil::getInstance();
        /** @var ?Task $ressource */
        $ressource = $this->getDoctrine()
            ->getRepository(Task::class)
            ->find($barcode->getEntityId());

        if (is_null($ressource)) {
            return $this->json(['error' => 'No data found.'], 404);
        }

        $package = null;
        if ($barcode->isContainsPackages()) {
            //NOTE: Dont rely on lazy loading
            $package = $this->getDoctrine()
                ->getRepository(Package::class)
                ->find($barcode->getPackageTaskId())?->getPackage()?->getName();
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


        $barcodes = [[
            'code' => $barcode->getRawBarcode(),
            'svg' => $barcodeSVG
        ]];

        if (isset($ressource->getMetadata()['barcode'])) {
            $barcode_alt = $ressource->getMetadata()['barcode'];

            $barcodes[] = [
                'code' => $barcode_alt,
                'svg' => $generator->getBarcode(
                    barcode: $barcode_alt,
                    type: $generator::TYPE_CODE_128,
                    widthFactor: 1.4,
                    height: 55
                )
            ];
        }

        $html = $this->twig->render('task/label.pdf.twig', [
            'from' => $from,
            'task' => $ressource,
            'phone' => $phone,
            'barcodes' => $barcodes,
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

        $pdf = $response->getContent();
        return new Response($pdf, 200, [
            'Cache-Control' => 'private',
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="label_%s.pdf"', $barcode->getRawBarcode())
        ]);
    }
}
