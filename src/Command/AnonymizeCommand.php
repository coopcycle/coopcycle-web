<?php

namespace AppBundle\Command;

use AppBundle\Entity\Sylius\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AnonymizeCommand extends Command
{
    private $entityManager;
    private $userManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserManagerInterface $userManager)
    {
        $this->entityManager = $entityManager;
        $this->userManager = $userManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:anonymize')
            ->setDescription('Anonymizes a customer')
            ->addArgument(
                'email',
                InputArgument::REQUIRED
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');

        $customer = $this->entityManager->getRepository(Customer::class)->findOneBy([
            'emailCanonical' => $email
        ]);

        if (null === $customer) {
            $this->io->text('Customer not found');

            return 1;
        }

        $anonymousEmail = sprintf('anon%s@coopcycle.org', bin2hex(random_bytes(8)));

        $customer->setEmail($anonymousEmail);
        $customer->setEmailCanonical($anonymousEmail);

        if ($customer->hasUser()) {
            $user = $customer->getUser();
            $user->setEmail($anonymousEmail);
            $user->setEmailCanonical($anonymousEmail);
            $user->setEnabled(false);

            $this->userManager->updateUser($user, false);
        }

        $this->entityManager->flush();

        $this->io->text('Customer anonymized successfully!');

        return 0;
    }
}
