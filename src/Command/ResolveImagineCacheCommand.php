<?php

namespace AppBundle\Command;

use AppBundle\Entity\Sylius\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use League\Flysystem\MountManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

Class ResolveImagineCacheCommand extends Command
{
    public function __construct(
        StorageInterface $storage,
        MountManager $mountManager,
        PropertyMappingFactory $propertyMappingFactory,
        EntityManagerInterface $entityManager)
    {
        parent::__construct();

        $this->storage = $storage;
        $this->mountManager = $mountManager;
        $this->propertyMappingFactory = $propertyMappingFactory;
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:imagine:cache:resolve')
            ->setDescription('Warms up the cache for all the product images.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resolveCacheCommand = $this->getApplication()->find('liip:imagine:cache:resolve');

        $qb =
            $this->entityManager->getRepository(ProductImage::class)
                ->createQueryBuilder('i')
                ->setFirstResult(0)
                ->setMaxResults(10)
                ;

        $paginator = new Paginator($qb->getQuery());

        $pageCount =
            ceil(count($paginator) / $paginator->getQuery()->getMaxResults());

        for ($p = 1; $p <= $pageCount; $p++) {

            $paginator->getQuery()->setFirstResult(($p - 1) * $paginator->getQuery()->getMaxResults());

            foreach ($paginator as $image) {

                $mapping = $this->propertyMappingFactory->fromField($image, 'imageFile');
                $uri = $this->storage->resolveUri($image, 'imageFile');

                $filterName = sprintf('product_thumbnail_%s', str_replace(':', 'x', $image->getRatio()));

                $arguments = [
                    'paths' => [ $uri ],
                    '--filter' => [ $filterName ],
                    '--no-colors' => true,
                ];

                $resolveCacheInput = new ArrayInput($arguments);
                $returnCode = $resolveCacheCommand->run($resolveCacheInput, $output);
            }
        }

        return 0;
    }
}
