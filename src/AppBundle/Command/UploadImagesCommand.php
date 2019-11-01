<?php

namespace AppBundle\Command;

use League\Flysystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UploadImagesCommand extends ContainerAwareCommand
{
    private $io;

    public function __construct(
        Filesystem $localProductImagesFilesystem,
        Filesystem $remoteProductImagesFilesystem,
        Filesystem $localRestaurantImagesFilesystem,
        Filesystem $remoteRestaurantImagesFilesystem,
        Filesystem $localTaskImagesFilesystem,
        Filesystem $remoteTaskImagesFilesystem)
    {
        $this->localProductImagesFilesystem = $localProductImagesFilesystem;
        $this->remoteProductImagesFilesystem = $remoteProductImagesFilesystem;

        $this->localRestaurantImagesFilesystem = $localRestaurantImagesFilesystem;
        $this->remoteRestaurantImagesFilesystem = $remoteRestaurantImagesFilesystem;

        $this->localTaskImagesFilesystem = $localTaskImagesFilesystem;
        $this->remoteTaskImagesFilesystem = $remoteTaskImagesFilesystem;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:images:upload')
            ->setDescription('Upload images.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->text('Uploading product images');

        $files = $this->localProductImagesFilesystem->listContents('', true);
        foreach ($files as $file) {
            if ($file['type'] === 'file') {
                $this->io->text(sprintf('Uploading file %s', $file['path']));
                $stream = $this->localProductImagesFilesystem->readStream($file['path']);
                $this->remoteProductImagesFilesystem->putStream($file['path'], $stream);
            }
        }

        $this->io->text('Uploading restaurant images');

        $files = $this->localRestaurantImagesFilesystem->listContents('', true);
        foreach ($files as $file) {
            if ($file['type'] === 'file') {
                $this->io->text(sprintf('Uploading file %s', $file['path']));
                $stream = $this->localRestaurantImagesFilesystem->readStream($file['path']);
                $this->remoteRestaurantImagesFilesystem->putStream($file['path'], $stream);
            }
        }

        $this->io->text('Uploading task images');

        $files = $this->localTaskImagesFilesystem->listContents('', true);
        foreach ($files as $file) {
            if ($file['type'] === 'file') {
                $this->io->text(sprintf('Uploading file %s', $file['path']));
                $stream = $this->localTaskImagesFilesystem->readStream($file['path']);
                $this->remoteTaskImagesFilesystem->putStream($file['path'], $stream);
            }
        }
    }
}
