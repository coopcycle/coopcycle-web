<?php

namespace AppBundle\Command;

use Laravolt\Avatar\Avatar;
use Shahonseven\ColorHash;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RegenerateAvatarsCommand extends Command
{
    private $avatarDir;

    public function __construct(string $avatarDir)
    {
        $this->avatarDir = $avatarDir;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:avatars:regenerate')
            ->setDescription('Regnerates avatars');
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
        $dir = rtrim($this->avatarDir, '/');
        $files = glob("{$dir}/*.png");

        $colorHash = new ColorHash();

        $avatar = new Avatar([
            'uppercase' => true,
        ]);

        foreach ($files as $filename) {

            $username = basename($filename, '.png');
            $hex = $colorHash->hex($username);

            $this->io->text(sprintf('Regenerating avatar for "%s" with color %s', $username, $hex));

            $avatar
                ->create($username)
                ->setBackground($hex)
                ->save($filename);
        }

        return 0;
    }
}
