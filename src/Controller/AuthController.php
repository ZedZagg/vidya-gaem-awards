<?php
namespace App\Controller;

use App\Service\SteamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use xPaw\Steam\SteamOpenID;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly SteamService $steam,
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
}
