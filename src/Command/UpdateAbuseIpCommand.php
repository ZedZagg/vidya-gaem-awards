<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\UserNomination;
use App\Service\AbuseIpdbService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:update-abuse-ip')]
class UpdateAbuseIpCommand extends Command
{
    public function __construct(
        private AbuseIpdbService $abuseIpdb,
        private EntityManagerInterface $em,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Checking nomination IPs');

        $ips = $this->em->getRepository(UserNomination::class)
            ->createQueryBuilder('n')
            ->select('n.user')
            ->distinct()
            ->getQuery()
            ->getResult();

        $ips = array_map(fn($ip) => $ip['user'], $ips);
        $ips = array_filter($ips, fn($ip) => filter_var($ip, FILTER_VALIDATE_IP));

        $output->writeln('Checking ' . count($ips) . ' IPs');

        foreach ($ips as $ip) {
            $output->writeln('Checking ' . $ip);
            $this->abuseIpdb->updateIpInformation($ip);
        }

        $output->writeln('Finished');

        return Command::SUCCESS;
    }
}
