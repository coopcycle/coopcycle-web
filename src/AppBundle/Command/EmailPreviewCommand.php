<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EmailPreviewCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('coopcycle:email:preview')
            ->setDescription('Preview the email layout.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $html = $container->get('templating')
                          ->render(
                              '@App/emails/layout.html.twig',
                              ['raw_content' => 'The lazy fox jump over aso. aso.<br>The lazy fox jump over aso. aso.<br>The lazy fox jump over aso. aso.']);
        echo $html;
    }

}
