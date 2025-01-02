<?php
namespace App\Controller;

use App\Entity\TableHistory;
use App\Service\AuditService;
use App\Service\ConfigService;
use App\Service\IgdbService;
use App\Service\WikipediaService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Action;
use App\Entity\GameRelease;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class VideoGamesController extends AbstractController
{
    public function indexAction(EntityManagerInterface $em): Response
    {
        $query = $em->createQueryBuilder()
            ->from(GameRelease::class, 'gr')
            ->select('gr')
            ->where('gr.deletedAt IS NULL')
            ->orderBy('gr.name', 'ASC');

        $games = $query->getQuery()->getResult();

        return $this->render('videoGames.html.twig', [
            'title' => 'Vidya in 2024',
            'games' => $games
        ]);
    }

    public function add(EntityManagerInterface $em, ConfigService $configService, Request $request, AuditService $auditService): JsonResponse
    {
        if ($configService->isReadOnly()) {
            return $this->json(['error' => 'The site is currently in read-only mode. No changes can be made.']);
        }

        $post = $request->request;

        $game = trim($post->get('name'));

        if (trim($game) === '') {
            return $this->json(['error' => 'Please enter the name of the game.']);
        }

        $game = new GameRelease($game);
        $game->setSource('manual');

        $platforms = ['pc', 'ps3', 'ps4', 'ps5', 'vita', 'psn', 'x360', 'xb1', 'xbla', 'xsx', 'wii', 'wiiu', 'wiiware', 'switch', 'n3ds', 'vr', 'mobile'];
        foreach ($platforms as $platform) {
            if ($post->get($platform)) {
                $game->{'set'.$platform}(true);
            }
        }

        if (count($game->getPlatforms()) === 0) {
            return $this->json(['error' => 'You need to select at least one platform.']);
        }

        $em->persist($game);
        $em->flush();

        $auditService->add(
            new Action('add-video-game', $game->getId()),
            new TableHistory(GameRelease::class, $game->getId(), $post->all())
        );
        $em->flush();

        return $this->json(['success' => $game->getName()]);
    }

    public function remove(EntityManagerInterface $em, ConfigService $configService, Request $request, AuditService $auditService): JsonResponse
    {
        if ($configService->isReadOnly()) {
            return $this->json(['error' => 'The site is currently in read-only mode. No changes can be made.']);
        }

        $post = $request->request;

        $game = $em->getRepository(GameRelease::class)->find($post->get('id'));
        if (!$game || $game->isDeleted()) {
            return $this->json(['error' => 'Couldn\'t find the selected game. Perhaps it has already been removed?']);
        }

        $game->setDeletedAt(new DateTimeImmutable());
        $em->persist($game);
        $em->flush();

        $auditService->add(
            new Action('remove-video-game', $game->getName())
        );
        $em->flush();

        return $this->json(['success' => true]);
    }

    public function reloadWikipedia(EntityManagerInterface $em, WikipediaService $wikpedia, ConfigService $configService, AuditService $auditService, Session $session): JsonResponse
    {
        if ($configService->isReadOnly()) {
            return $this->json(['error' => 'The site is currently in read-only mode. No changes can be made.']);
        }

        try {
            $games = $wikpedia->getGames(2024);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()]);
        }

        $wikpedia->addGamesToGameReleaseTable($games, true);
        $auditService->add(
            new Action('reload-video-games', 'wikipedia')
        );
        $em->flush();

        $session->getFlashBag()->add('success', 'The list of 2024 video game releases has been successfully imported from Wikipedia.');

        return $this->json(['success' => true]);
    }

    public function reloadIgdb(EntityManagerInterface $em, IgdbService $igdb, ConfigService $configService, AuditService $auditService, Session $session): JsonResponse
    {
        if ($configService->isReadOnly()) {
            return $this->json(['error' => 'The site is currently in read-only mode. No changes can be made.']);
        }

        try {
            $allGames = [];
            $offset = 0;

            do {
                $games = $igdb->getGames(2024, $offset);

                $allGames = [...$allGames, ...$games];
                $offset = count($allGames);
            } while (!empty($games));
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()]);
        }

        $igdb->addGamesToGameReleaseTable($allGames, true);
        $auditService->add(
            new Action('reload-video-games', 'igdb')
        );
        $em->flush();

        $session->getFlashBag()->add('success', 'The list of 2024 video game releases has been successfully imported from IGDB.');

        return $this->json(['success' => true]);
    }
}
