<?php

namespace AppBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

Class CopyTranslationCommand extends Command
{
    private $translator;
    private $jsTranslationsDir;
    private $localeRegex;

    public function __construct(
        TranslatorInterface $translator,
        string $jsTranslationsDir,
        string $localeRegex)
    {
        $this->translator = $translator;
        $this->jsTranslationsDir = $jsTranslationsDir;
        $this->localeRegex = $localeRegex;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:translations:copy')
            ->setDescription('Copies a PHP translation to JS')
            ->addOption(
                'input',
                null,
                InputOption::VALUE_REQUIRED,
                'PHP translation key',
            )
            ->addOption(
                'output',
                null,
                InputOption::VALUE_REQUIRED,
                'JS translation key'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputKey = $input->getOption('input');
        $outputKey = $input->getOption('output');

        if ($this->translator instanceof TranslatorBagInterface) {

            $locales = explode('|', $this->localeRegex);

            foreach ($locales as $locale) {
                $catalogue = $this->translator->getCatalogue($locale);

                if ($catalogue->has($inputKey)) {

                    $jsFile = $this->jsTranslationsDir . '/' . $locale . '.json';

                    if (file_exists($jsFile)) {
                        $messages = json_decode(file_get_contents($jsFile), true);
                        if (!isset($messages['common'][$outputKey])) {
                            $messages['common'][$outputKey] = $catalogue->get($inputKey);
                            file_put_contents(
                                $jsFile,
                                json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
                            );
                        }
                    } else {
                        $output->writeln(sprintf('JS translation file for locale "%s" does not exist', $locale));
                    }
                }
            }
        }

        return 0;
    }
}
