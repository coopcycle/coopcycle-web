<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

Class CreateSettingCommand extends ContainerAwareCommand
{
    private $entityName;
    private $entityManager;

    protected function configure()
    {
        $this
            ->setName('craue:setting:create')
            ->setDescription('Creates a new craue setting.')
            ->addOption(
                'section',
                null,
                InputOption::VALUE_REQUIRED,
                'Setting section'
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
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->entityName = $this->getContainer()->getParameter('craue_config.entity_name');
        $this->entityManager = $this->getContainer()->get('doctrine')->getManagerForClass($this->entityName);
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $section = $input->getOption('section');
        $name = $input->getOption('name');
        $value = $input->getOption('value');

        $className = $this->entityName;

        $setting = new $className();
        $setting->setSection($section);
        $setting->setName($name);
        $setting->setValue($value);

        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $output->writeln('<info>Setting created.</info>');
    }
}
