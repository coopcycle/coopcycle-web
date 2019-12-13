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
        Filesystem $remoteTaskImagesFilesystem,
        Filesystem $localReceiptsFilesystem,
        Filesystem $remoteReceiptsFilesystem,
        Filesystem $localAssetsFilesystem,
        Filesystem $remoteAssetsFilesystem)
    {
        $this->localProductImagesFilesystem = $localProductImagesFilesystem;
        $this->remoteProductImagesFilesystem = $remoteProductImagesFilesystem;

        $this->localRestaurantImagesFilesystem = $localRestaurantImagesFilesystem;
        $this->remoteRestaurantImagesFilesystem = $remoteRestaurantImagesFilesystem;

        $this->localTaskImagesFilesystem = $localTaskImagesFilesystem;
        $this->remoteTaskImagesFilesystem = $remoteTaskImagesFilesystem;

        $this->localReceiptsFilesystem = $localReceiptsFilesystem;
        $this->remoteReceiptsFilesystem = $remoteReceiptsFilesystem;

        $this->localAssetsFilesystem = $localAssetsFilesystem;
        $this->remoteAssetsFilesystem = $remoteAssetsFilesystem;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:s3:upload_files')
            ->setDescription('Upload files stored locally to S3.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->text('Uploading product images');
        $this->synchronize(
            $this->localProductImagesFilesystem,
            $this->remoteProductImagesFilesystem
        );

        $this->io->text('Uploading restaurant images');
        $this->synchronize(
            $this->localRestaurantImagesFilesystem,
            $this->remoteRestaurantImagesFilesystem
        );

        $this->io->text('Uploading task images');
        $this->synchronize(
            $this->localTaskImagesFilesystem,
            $this->remoteTaskImagesFilesystem
        );

        $this->io->text('Uploading receipts files');
        $this->synchronize(
            $this->localReceiptsFilesystem,
            $this->remoteReceiptsFilesystem
        );

        $this->io->text('Uploading assets files');
        $this->synchronize(
            $this->localAssetsFilesystem,
            $this->remoteAssetsFilesystem
        );

        return 0;
    }

    private function synchronize($localFilesystem, $remoteFilesystem)
    {
        $files = $localFilesystem->listContents('', true);
        $this->io->text(sprintf('Found %d files to synchronize', count($files)));

        foreach ($files as $file) {
            if ($file['type'] === 'file' && '.' !== substr($file['basename'], 0, 1)) {
                $this->io->text(sprintf('Uploading file %s', $file['path']));
                $stream = $localFilesystem->readStream($file['path']);
                $remoteFilesystem->putStream($file['path'], $stream);
            }
        }
    }
}
