<?php
namespace App\Command;

use App\Entity\Autocompleter;
use App\Entity\Config;
use App\Entity\Permission;
use App\Entity\Template;
use App\Entity\User;
use App\Service\SteamService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class InitialiseDatabaseCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly EntityManagerInterface $em,
        private readonly SteamService $steam,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:init-db')
            ->setDescription('Initialises the database with the required data.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->getApplication()->find('doctrine:schema:update');
        $arguments = ['--force' => true];
        $greetInput = new ArrayInput($arguments);
        $command->run($greetInput, $output);

        $repo = $this->em->getRepository(Config::class);
        $config = $repo->findOneBy([]);

        if ($config) {
            throw new Exception('The database already appears to be initalized.');
        }

        // Add the default config
        $config = new Config();
        $this->em->persist($config);

        // Add the special-case autocompleter
        $existing = $this->em->getRepository(Autocompleter::class)->find(Autocompleter::VIDEO_GAMES);

        if (!$existing) {
            $autocompleter = new Autocompleter();
            $autocompleter->setId(Autocompleter::VIDEO_GAMES);
            $autocompleter->setName('Video games in ' . date('Y'));
            $this->em->persist($autocompleter);
        }

        // Add the standard permissions
        foreach (Permission::STANDARD_PERMISSIONS as $id => $description) {
            $permission = new Permission();
            $permission->setId($id);
            $permission->setDescription($description);
            $this->em->persist($permission);
        }

        $this->em->flush();

        // Add the default permission inheritance
        $repo = $this->em->getRepository(Permission::class);
        foreach (Permission::STANDARD_PERMISSION_INHERITANCE as $parent => $children) {
            /** @var Permission $parent */
            $parent = $repo->find($parent);

            foreach ($children as $child) {
                /** @var Permission $child */
                $child = $repo->find($child);
                $parent->addChild($child);
            }

            $this->em->persist($parent);
        }

        $this->em->flush();

        // Add available templates
        $template = (new Template())
            ->setFilename('home_static_panel.html.twig')
            ->setName('Home - static panel')
            ->setSource(file_get_contents($this->projectDir . '/templates/dynamic/home_static_panel.html.twig'));

        $this->em->persist($template);
        $this->em->flush();

        // Add the first user account
        $helper = $this->getHelper('question');
        $question = new Question('Enter a Steam ID or custom profile URL to give that user level 5 access: ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                return false;
            }

            $steamId = $this->steam->stringToSteamId($answer);
            if (!$steamId) {
                return false;
            }

            return $this->steam->getProfile($steamId);
        });

        $profile = $helper->ask($input, $output, $question);

        if ($profile) {
            $user = new User();
            $user
                ->setSteamId($profile['steamId64'])
                ->setName($profile['nickname'])
                ->setAvatar($profile['avatar'])
                ->setSpecial(true);

            /** @var Permission $permission */
            $permission = $this->em->getRepository(Permission::class)->find('LEVEL_5');
            $user->addPermission($permission);
            $this->em->persist($user);
            $this->em->flush();

            $output->writeln($profile['nickname'] . ' has been given level 5 access.');
        }

        $output->writeln('Setup complete.');

        return 0;
    }
}
