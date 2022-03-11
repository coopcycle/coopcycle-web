<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\OrderProcessing\OrderTaxesProcessor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Fidry\AliceDataFixtures\LoaderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LoadFixturesCommand extends Command
{
    public function __construct(
        LoaderInterface $fixturesLoader,
        string $projectDir,
        string $environment)
    {
        $this->fixturesLoader = $fixturesLoader;
        $this->projectDir = $projectDir;
        $this->environment = $environment;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:fixtures:load')
            ->setDescription('Load fixtures')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'The entity manager to use for this command. If not specified, use the default Doctrine fixtures entity'
                .'manager.'
            );
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
        if ('test' !== $this->environment) {
            $this->io->error('This command can only be executed in test environment');
            return 1;
        }

        $file = $input->getOption('file');

        $files = [
            $this->projectDir . '/' . $file
        ];

        $this->fixturesLoader->load($files, $_SERVER);

        return 0;
    }
}
