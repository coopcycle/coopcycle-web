<?php

namespace AppBundle\Command;

use Craue\ConfigBundle\Util\Config;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

Class CreateSettingCommand extends Command
{
    private $entityName;
    private $entityManager;
    private $craueConfig;

    public function __construct(
        string $entityName,
        ManagerRegistry $doctrine,
        Config $config)
    {
        $this->entityName = $entityName;
        $this->doctrine = $doctrine;
        $this->craueConfig = $config;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('craue:setting:create')
            ->setDescription('Creates a new craue setting.')
            ->addOption(
                'section',
                null,
                InputOption::VALUE_REQUIRED,
                'Setting section',
                'general',
            )
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Setting name'
            )
            ->addOption(
                'value',
                null,
                InputOption::VALUE_REQUIRED,
                'Setting value'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force overwrite when setting already exists'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->entityManager = $this->doctrine->getManagerForClass($this->entityName);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        foreach (array('section', 'name', 'value') as $optionName) {
            $optionValue = $input->getOption($optionName);
            if (null === $optionValue) {
                $question = new Question(sprintf("Please provide setting %s\n", $optionName));
                $optionValue = $this->getHelper('question')->ask($input, $output, $question);
                if (!$optionValue) {
                    throw new \RuntimeException(sprintf('No setting %s provided', $optionName));
                }
                $input->setOption($optionName, $optionValue);
            }
        }
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $section = $input->getOption('section');
        $name = $input->getOption('name');
        $value = $input->getOption('value');
        $force = $input->getOption('force');

        try {

            $currentValue = $this->craueConfig->get($name);

            if ($force) {
                $output->writeln(sprintf('<comment>Setting "%s" already exists, updating.</comment>', $name));
                $this->craueConfig->set($name, $value);
            } else {
                $output->writeln(sprintf('<comment>Setting "%s" already exists, ignoring.</comment>', $name));
            }

            return 0;

        } catch (\RuntimeException $e) {}

        $className = $this->entityName;

        $setting = new $className();
        $setting->setSection($section);
        $setting->setName($name);
        $setting->setValue($value);

        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $output->writeln('<info>Setting created.</info>');

        return 0;
    }
}
