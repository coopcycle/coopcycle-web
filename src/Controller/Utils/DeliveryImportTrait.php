<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery\ImportQueue as DeliveryImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Form\DeliveryImportMappingType;
use AppBundle\Message\ImportDeliveries;
use AppBundle\Service\DeliveryImportStatusManager;
use AppBundle\Spreadsheet\Column\ColumnDetector;
use AppBundle\Spreadsheet\Column\ColumnMapping;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Importing deliveries from a spreadsheet, in two steps.
 *
 * The file is uploaded and kept, and the import waits: a file is of no use until we know
 * which field each of its columns holds. A dispatcher says so on the mapping screen, and
 * only then is the file handed to a worker.
 */
trait DeliveryImportTrait
{
    /**
     * Dropping an uploaded file is a form of its own, next to the mapping one
     */
    const CANCEL_IMPORT_TOKEN_ID = 'delivery_import_cancel';

    /**
     * Keeps an uploaded file and sends its uploader off to map its columns.
     *
     * Nothing is dispatched here: the import sits in "mapping" until the mapping screen
     * is confirmed, and a worker only ever picks up a pending import.
     */
    protected function handleDeliveryImportForStore(
        Store $store,
        FormInterface $form,
        EntityManagerInterface $entityManager,
        Hashids $hashids,
        Filesystem $filesystem,
        MessageBusInterface $messageBus,
        SlugifyInterface $slugify,
        string $routeTo,
        LoggerInterface $logger,
        string $mappingRoute = 'admin_delivery_import_mapping',
    )
    {

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $form->get('file')->getData();

        $queue = new DeliveryImportQueue();
        $queue->setStore($store);
        $queue->setStatus(DeliveryImportQueue::STATUS_MAPPING);

        $entityManager->persist($queue);
        $entityManager->flush();

        // slugify to get a safe filename. it may return an empty string but we had a hash afterwards so it should be alright.
        $filename = $slugify->slugify($uploadedFile->getClientOriginalName());
        $filename = substr($filename, 0, -4); // remove .csv extension in filename
        $filename = substr($filename, 0, 100); // limit the filename length

        $filename = sprintf(
            '%s_%s.%s',
            $filename,
            $hashids->encode($queue->getId()),
            $uploadedFile->guessExtension()
        );

        try {

            $filesystem->write($filename, $uploadedFile->getContent());

            $queue->setFilename($filename);
            $entityManager->flush();

            return $this->redirectToRoute($mappingRoute, ['id' => $queue->getId()]);

        } catch (FilesystemException | UnableToWriteFile $e) {

            $logger->error('Error while writing delivery import file', [
                'exception' => $e,
                'filename' => $filename,
            ]);

            $entityManager->remove($queue);
            $entityManager->flush();

            $this->addFlash(
                'error',
                $this->translator->trans('deliveries.import_upload_error')
            );
        }

        return $this->redirectToRoute($routeTo, ['section' => 'imports']);
    }

    /**
     * Shows the columns of an uploaded file next to the fields they could hold, with a
     * mapping suggested from their names and their values, and hands the file to a worker
     * once a dispatcher has confirmed it.
     */
    protected function handleDeliveryImportMapping(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        Filesystem $filesystem,
        DeliverySpreadsheetParser $parser,
        ColumnDetector $detector,
        DeliveryImportStatusManager $statusManager,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
        string $routeTo,
    )
    {
        /** @var ?DeliveryImportQueue $queue */
        $queue = $entityManager->getRepository(DeliveryImportQueue::class)->find($id);

        if (is_null($queue)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('edit', $queue);

        // Already mapped and on its way, or dropped: there is nothing left to map
        if (!$queue->isWaitingForMapping()) {
            return $this->redirectToRoute($routeTo, ['section' => 'imports']);
        }

        // Its own form, with its own token: a dispatcher must be able to drop a file
        // whose columns they never finished matching, which the mapping form itself
        // would refuse to submit
        if ($request->request->has('cancel_import')) {

            if (!$this->isCsrfTokenValid(self::CANCEL_IMPORT_TOKEN_ID, $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $statusManager->cancel($queue);

            $this->addFlash(
                'notice',
                $this->translator->trans('deliveries.imports.mapping_cancelled', [
                    '%filename%' => $queue->getFilename(),
                ])
            );

            return $this->redirectToRoute($routeTo, ['section' => 'imports']);
        }

        $tempnam = null;

        try {
            $tempnam = $this->toTempFile($filesystem, $queue);

            $preview = $parser->preview($tempnam);
        } catch (\Exception $e) {
            $logger->error('Could not read a delivery import file to map its columns', [
                'exception' => $e,
                'filename' => $queue->getFilename(),
            ]);

            $statusManager->cancel($queue);

            $this->addFlash('error', $this->translator->trans('deliveries.imports.unreadable'));

            return $this->redirectToRoute($routeTo, ['section' => 'imports']);
        } finally {
            if (!is_null($tempnam) && file_exists($tempnam)) {
                unlink($tempnam);
            }
        }

        $columns = $parser->getColumns();

        $form = $this->createForm(DeliveryImportMappingType::class, null, [
            'columns' => $columns,
            'file_columns' => $preview->getHeader(),
        ]);

        // What the file looks like it holds, for the dispatcher to correct rather than fill in
        $form->setData($detector->detect($preview, $columns)->toArray());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $mapping = ColumnMapping::fromArray($form->getData());

            // A compare-and-set: submitted twice, the file is still handed over once
            if ($statusManager->queued($queue, $mapping->toArray())) {

                $messageBus->dispatch(
                    new ImportDeliveries($queue->getFilename()),
                    [ new DelayStamp(5000) ]
                );

                $this->addFlash(
                    'notice',
                    $this->translator->trans('deliveries.importing', ['%filename%' => $queue->getFilename()])
                );
            }

            return $this->redirectToRoute($routeTo, ['section' => 'imports']);
        }

        return $this->render('delivery/import_mapping.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'import_queue' => $queue,
            'form' => $form->createView(),
            'preview' => $preview,
            'cancel_url' => $this->generateUrl($routeTo, ['section' => 'imports']),
            'cancel_token_id' => self::CANCEL_IMPORT_TOKEN_ID,
        ]);
    }

    /**
     * The readers want a file on disk, the imports live on a filesystem that may not be one
     */
    private function toTempFile(Filesystem $filesystem, DeliveryImportQueue $queue): string
    {
        $tempnam = tempnam(sys_get_temp_dir(), 'coopcycle_delivery_import_mapping');

        if (false === file_put_contents($tempnam, $filesystem->read($queue->getFilename()))) {
            throw new \Exception(sprintf('Could not write temp file %s', $tempnam));
        }

        return $tempnam;
    }
}
