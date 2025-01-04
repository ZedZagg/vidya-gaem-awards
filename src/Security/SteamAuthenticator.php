<?php

namespace App\Security;

use App\Entity\Login;
use App\Entity\User;
use App\Service\ConfigService;
use App\Service\SteamService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\HttpUtils;
use Twig\Environment;
use xPaw\Steam\SteamOpenID;

class SteamAuthenticator extends AbstractAuthenticator implements InteractiveAuthenticatorInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly HttpUtils $httpUtils,
        private readonly UserProviderInterface $userProvider,
        private readonly EntityManagerInterface $em,
        private readonly SteamService $steam,
        private readonly ConfigService $config,
        private readonly Environment $twig,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $this->httpUtils->checkRequestPath($request, 'loginReturn');
    }

    public function authenticate(Request $request): Passport
    {
        $steam = new SteamOpenID($this->router->generate('loginReturn', [], UrlGeneratorInterface::ABSOLUTE_URL));

        if (!$steam->ShouldValidate()) {
            throw new AuthenticationException('Invalid login request');
        }

        try {
            $steamId64 = $steam->Validate();
        } catch (Exception $e) {
            throw new AuthenticationException($e->getMessage(), 0, $e);
        }

        try {
            $this->userProvider->loadUserByIdentifier($steamId64);
        } catch (UserNotFoundException $e) {
            if ($this->config->isReadOnly()) {
                throw new AuthenticationException('Unable to create an account. The site is in read-only mode and you have not logged in with this Steam account before.', 0, $e);
            }

            $user = new User();
            $user->setSteamId($steamId64);
            $user->setName($steamId64);

            $this->em->persist($user);
            $this->em->flush();
        }

        return new SelfValidatingPassport(new UserBadge($steamId64), [
            new RememberMeBadge()
        ]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();

        $login = new Login();
        $user
            ->addLogin($login)
            ->setLastLogin(new DateTime());

        if (!$user->getFirstLogin()) {
            $user->setFirstLogin(new DateTime());
        }

        $steam = $this->steam->getProfile($user->getSteamId());

        $user->setAvatar($steam['avatar']);
        $user->setName($steam['nickname']);

        $this->em->persist($login);
        $this->em->persist($user);

        if (!$this->config->isReadOnly()) {
            $this->em->flush();
        }

        $redirectPath = $request->getSession()->get('_security.main.target_path') ?: 'home';
        return $this->httpUtils->createRedirectResponse($request, $redirectPath);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $html = $this->twig->render('loginFailure.html.twig', [
            'exception' => $exception,
        ]);

        return new Response($html, Response::HTTP_UNAUTHORIZED);
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
