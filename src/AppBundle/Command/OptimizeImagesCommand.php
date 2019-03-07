<?php

namespace AppBundle\Command;

use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class OptimizeImagesCommand extends ContainerAwareCommand
{
    private $restaurantImagesDir;
    private $restaurantImagesLoader;
    private $filterManager;
    private $finder;

    public function __construct(
        $restaurantImagesDir,
        LoaderInterface $restaurantImagesLoader,
        FilterManager $filterManager,
        Filesystem $filesystem)
    {
        $this->restaurantImagesDir = $restaurantImagesDir;
        $this->restaurantImagesLoader = $restaurantImagesLoader;
        $this->filterManager = $filterManager;
        $this->filesystem = $filesystem;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:images:optimize')
            ->setDescription('Optimizes images.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->finder = new Finder();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->finder->files()->in($this->restaurantImagesDir);

        foreach ($this->finder as $file) {

            try {

                $image = $this->restaurantImagesLoader->find($file->getRelativePathname());

                $filteredBinary = $this->filterManager->applyFilter($image, 'restaurant_thumbnail');

                // We do not use the cache system, we overwrite the original file
                $this->filesystem->dumpFile($file->getRealPath(), $filteredBinary->getContent());

                $output->writeln(sprintf('<info>Successfully optimized %s</info>', $file->getRelativePathname()));

            } catch (NotLoadableException $e) {
                // TODO Log error
            }
        }
    }
}
