<?php

namespace App\Security;

use App\Entity\AnonymousUser;
use RandomLib\Factory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AnonymousAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private string $secret,
        private readonly Security $security
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Don't run the authenticator if there's already a user
        $user = $this->security->getUser();
        if ($user) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        return new SelfValidatingPassport(new UserBadge('Anonymous', function ($identifier) {
            return new AnonymousUser();
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
