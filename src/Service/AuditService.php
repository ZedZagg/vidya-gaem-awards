<?php
namespace App\Service;

use App\Entity\Action;
use App\Entity\Award;
use App\Entity\BaseUser;
use App\Entity\TableHistory;
use App\Entity\User;
use App\Entity\UserNominationGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AuditService
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function add(Action $action, ?TableHistory $history = null): void
    {
        /** @var BaseUser $user */
        $user = $this->security->getUser();

        if ($history) {
            if ($user instanceof User) {
                $history->setUser($user);
            }
            $this->em->persist($history);
            $this->em->flush();
            $action->setTableHistory($history);
        }

        $action->setUser($user);
        $this->em->persist($action);
        $this->em->flush();
    }

    /**
     * @param Action $action
     * @return null|object
     */
    public function getEntity(Action $action): ?object
    {
        if ($history = $action->getTableHistory()) {
            $class = $history->getTable();
            // The namespace AppBundle was renamed to App in the 2018 release
            $class = str_replace('AppBundle', 'App', $class);
            $id = $history->getEntry();

            if (!class_exists($class)) {
                return null;
            }
            return $this->em->getRepository($class)->find($id);
        } elseif (str_starts_with($action->getAction(), 'profile') || $action->getAction() === 'user-added') {
            return $this->em->getRepository(User::class)->find($action->getData1());
        } else {
            return null;
        }
    }

    public function getMultiEntity(Action $action): array
    {
        $default = $this->getEntity($action);

        $return = [
            'default' => $default,
        ];

        $entityClasses = match ($action->getAction()) {
            'nomination-group-ignored',
            'nomination-group-unignored' => [Award::class, UserNominationGroup::class],
            'nomination-group-merged',
            'nomination-group-demerged' => [UserNominationGroup::class, UserNominationGroup::class],
            default => [],
        };

        if (isset($entityClasses[0]) && $action->getData1()) {
            $return['data1'] = $this->em->getRepository($entityClasses[0])->find($action->getData1());
        }
        if (isset($entityClasses[1]) && $action->getData2()) {
            $return['data2'] = $this->em->getRepository($entityClasses[1])->find($action->getData2());
        }

        return $return;
    }
}
