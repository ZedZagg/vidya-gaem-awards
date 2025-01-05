<?php

namespace App\Security;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AnonymousAuthFactory extends AbstractFactory implements AuthenticatorFactoryInterface
{

    public function getPriority(): int
    {
        return -60;
    }

    public function getKey(): string
    {
        return 'anonymous_handler';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string|array
    {
        $authenticatorId = 'security.authenticator.anonymous_handler.' . $firewallName;

        $container
            ->register($authenticatorId, AnonymousAuthenticator::class)
            ->addArgument('%kernel.secret%')
            ->addArgument(new Reference('security.helper'));

        return $authenticatorId;
    }
}
