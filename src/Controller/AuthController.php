<?php
namespace App\Controller;

use App\Entity\Login;
use App\Entity\User;
use App\Service\ConfigService;
use App\Service\SteamService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use xPaw\Steam\SteamOpenID;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserProviderInterface $userProvider,
        private readonly SteamService $steam,
        private readonly ConfigService $config,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function loginAction(Request $request, SessionInterface $session): Response
    {
        if (!$this->steam->isApiKeySet()) {
            return $this->render('siteConfigIssue.html.twig');
        }

        $session->set('_security.main.target_path', $request->query->get('redirect'));

        $steam = new SteamOpenID($this->router->generate('loginReturn', [], UrlGeneratorInterface::ABSOLUTE_URL));

        return new RedirectResponse($steam->GetAuthUrl());
    }

    public function loginReturnAction(SessionInterface $session): Response {
        if (!$this->steam->isApiKeySet()) {
            return $this->render('siteConfigIssue.html.twig');
        }

        $steam = new SteamOpenID($this->router->generate('loginReturn', [], UrlGeneratorInterface::ABSOLUTE_URL));

        if ($steam->ShouldValidate()) {
            try {
                $communityId = $steam->Validate();
            } catch (Exception $e) {
                return $this->render('loginFailure.html.twig', [
                    'exception' => $e,
                ], new Response('', 400));
            }
        } else {
            return $this->render('loginFailure.html.twig', [
                'exception' => null,
            ], new Response('', 400));
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($communityId);
        } catch (UserNotFoundException $e) {
            $user = new User();
            $user->setSteamId($communityId);
            $user->setName($communityId);
        }

        $this->onUserLogin($user);

        return new RedirectResponse($session->get('_security.main.target_path') ?: $this->urlGenerator->generate('home'));
    }

    private function onUserLogin(User $user): void
    {
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

        // TODO: this is probably not the correct way of logging in the user, but it was easier to copy and paste
        //       from the old bundle instead of setting up a Symfony authenticator
        $token = new UsernamePasswordToken($user, 'steam', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $this->requestStack->getSession()->set('_security_steam', serialize($token));

        $event = new InteractiveLoginEvent($this->requestStack->getCurrentRequest(), $token);
        $this->eventDispatcher->dispatch($event, 'security.interactive_login');
    }
}
