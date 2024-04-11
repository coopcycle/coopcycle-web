<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\Task\ImportQueue;
use AppBundle\Message\ImportTasks;
use AppBundle\Security\TokenStoreExtractor;
use ApiPlatform\Core\EventListener\EventPriorities;
use Hashids\Hashids;
use League\Flysystem\Filesystem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;

final class TaskImportQueueSubscriber implements EventSubscriberInterface
{
    private $messageBus;
    private $filesystem;
    private $storeExtractor;
    private $secret;

    public function __construct(
        MessageBusInterface $messageBus,
        Filesystem $taskImportsFilesystem,
        TokenStoreExtractor $storeExtractor,
        string $secret)
    {
        $this->messageBus = $messageBus;
        $this->filesystem = $taskImportsFilesystem;
        $this->storeExtractor = $storeExtractor;
        $this->secret = $secret;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => [
                ['queue', EventPriorities::POST_WRITE],
            ],
        ];
    }

    public function queue(ViewEvent $event)
    {
        $request = $event->getRequest();
        $result = $event->getControllerResult();

        if (!$result instanceof ImportQueue || Request::METHOD_POST !== $request->getMethod()) {
            return;
        }

        $csv = $request->getContent();

        $hashids = new Hashids($this->secret, 8);

        $encoded = $hashids->encode($result->getGroup()->getId());
        $filename = sprintf('%s.csv', $encoded);

        if ($this->filesystem->fileExists($filename)) {
            $this->filesystem->delete($filename);
        }

        $this->filesystem->write($filename, $csv, [
            'mimetype' => 'text/plain'
        ]);

        $orgId = null;
        $store = $this->storeExtractor->extractStore();
        if (null !== $store) {
            $orgId = $store->getOrganization()->getId();
        }

        $this->messageBus->dispatch(
            new ImportTasks($encoded, $filename, new \DateTime(), $result->getId(), $orgId)
        );
    }
}
