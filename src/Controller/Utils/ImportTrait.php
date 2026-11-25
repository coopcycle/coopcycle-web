<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Form\ImportMappingType;
use AppBundle\Import\ImportRegistry;
use AppBundle\Service\ImportStatusManager;
use AppBundle\Spreadsheet\Column\ColumnDetector;
use AppBundle\Spreadsheet\Column\ColumnMapping;
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
 * Importing a spreadsheet, whatever its rows hold, in two steps.
 *
 * The file is uploaded and kept, and the import waits: a file is of no use until we know
 * which field each of its columns holds. A dispatcher says so on the mapping screen, and
 * only then is the file handed to a worker.
 */
trait ImportTrait
{
    /**
     * Dropping an uploaded file is a form of its own, next to the mapping one
     */
    const CANCEL_IMPORT_TOKEN_ID = 'import_cancel';

    /**
     * Keeps an uploaded file and sends its uploader off to map its columns.
     *
     * Nothing is dispatched here: the import sits in "mapping" until the mapping screen
     * is confirmed, and a worker only ever picks up a pending import.
     */
    protected function handleImportUpload(
        string $type,
        ?Store $store,
        array $options,
        FormInterface $form,
        EntityManagerInterface $entityManager,
        Hashids $hashids,
        Filesystem $filesystem,
        SlugifyInterface $slugify,
        string $routeTo,
        LoggerInterface $logger,
        string $mappingRoute = 'admin_import_mapping',
    )
    {

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $form->get('file')->getData();

        $queue = new ImportQueue($type);
        $queue->setStore($store);
        // Which the tasks of a task import are saved with, and which scopes them when
        // they are cancelled
        $queue->setOrganization($store?->getOrganization());
        $queue->setOptions(empty($options) ? null : $options);
        $queue->setStatus(ImportQueue::STATUS_MAPPING);

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

            $logger->error('Error while writing import file', [
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
    protected function handleImportMapping(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ImportRegistry $registry,
        ColumnDetector $detector,
        ImportStatusManager $statusManager,
        MessageBusInterface $messageBus,
        LoggerInterface $logger,
        string $routeTo,
    )
    {
        /** @var ?ImportQueue $queue */
        $queue = $entityManager->getRepository(ImportQueue::class)->find($id);

        if (is_null($queue)) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('edit', $queue);

        $importHandler = $registry->handlerFor($queue->getType());
        $filesystem = $importHandler->getFilesystem();
        $parser = $importHandler->getParser();

        // Already mapped and on its way, or dropped: there is nothing left to map
        if (!$queue->isWaitingForMapping()) {
            return $this->redirectToRoute($routeTo, ['section' => 'imports']);
        }

        // Its own form, with its own token: a dispatcher must be able to drop a file
        // whose columns they never finished matching, which the mapping form itself
        // would refuse to submit
        if ($request->request->has('cancel_import')) {

            #FIXME; normally cypress e2e tests run with CSRF protection enabled, but once in a while CSRF tokens are not saved in the session (removed?) for this form
            if ('test' !== $this->getParameter('kernel.environment')
                && !$this->isCsrfTokenValid(self::CANCEL_IMPORT_TOKEN_ID, $request->request->get('_token'))) {
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
            $logger->error('Could not read an import file to map its columns', [
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

        $form = $this->createForm(ImportMappingType::class, null, [
            'columns' => $columns,
            'file_columns' => $preview->getHeader(),
            'import_type' => $queue->getType(),
            #FIXME; normally cypress e2e tests run with CSRF protection enabled, but once in a while CSRF tokens are not saved in the session (removed?) for this form
            'csrf_protection' => 'test' !== $this->getParameter('kernel.environment'),
        ]);

        // What the file looks like it holds, for the dispatcher to correct rather than fill in
        $form->setData($detector->detect($preview, $columns)->toArray());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $mapping = ColumnMapping::fromArray($form->getData());

            // A compare-and-set: submitted twice, the file is still handed over once
            if ($statusManager->queued($queue, $mapping->toArray())) {

                $messageBus->dispatch(
                    $importHandler->createMessage($queue),
                    [ new DelayStamp(5000) ]
                );

                $this->addFlash(
                    'notice',
                    $this->translator->trans('deliveries.importing', ['%filename%' => $queue->getFilename()])
                );
            }

            return $this->redirectToRoute($routeTo, ['section' => 'imports']);
        }

        return $this->render('import/mapping.html.twig', [
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
    private function toTempFile(Filesystem $filesystem, ImportQueue $queue): string
    {
        $tempnam = tempnam(sys_get_temp_dir(), 'coopcycle_import_mapping');

        if (false === file_put_contents($tempnam, $filesystem->read($queue->getFilename()))) {
            throw new \Exception(sprintf('Could not write temp file %s', $tempnam));
        }

        return $tempnam;
    }
}
