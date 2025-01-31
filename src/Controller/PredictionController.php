<?php
namespace App\Controller;

use App\Entity\BaseUser;
use App\Entity\FantasyPrediction;
use App\Entity\FantasyUser;
use App\Entity\TableHistory;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\ConfigService;
use App\Service\FileService;
use App\Service\PredictionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\Action;
use App\Entity\Award;
use App\Entity\Nominee;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PredictionController extends AbstractController
{
    public function index(?FantasyUser $fantasyUser, EntityManagerInterface $em, PredictionService $predictionService, SessionInterface $session, ConfigService $configService, AuthorizationCheckerInterface $authChecker): Response
    {
        $nonce = $session->get('nonce');
        if (!$nonce) {
            $nonce = md5(random_bytes(20));
            $session->set('nonce', $nonce);
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($fantasyUser) {
            if (!$configService->getConfig()->isPagePublic('results') && !$authChecker->isGranted('ROLE_VOTING_RESULTS')) {
                throw $this->createAccessDeniedException('Fantasy league results aren\'t yet publicly available.');
            }
            $viewingOwn = false;
        } else {
            if (!$user->getFantasyUser()) {
                if ($configService->getConfig()->isPagePublic('results')) {
                    return $this->redirectToRoute('predictionLeaderboard');
                }
                return $this->render('predictionSignUp.twig', [
                    'page' => 'picks',
                    'nonce' => $nonce
                ]);
            }

            $fantasyUser = $user->getFantasyUser();
            $viewingOwn = true;
        }

        /** @var Award[] $awards */
        $awards = $em->createQueryBuilder()
            ->select('a')
            ->from(Award::class, 'a', 'a.id')
            ->where('a.enabled = true')
            ->orderBy('a.order', 'ASC')
            ->getQuery()
            ->getResult();

        if ($viewingOwn) {
            $showResults = $predictionService->areResultsAvailable();
            $locked = $predictionService->arePredictionsLocked();
        } else {
            $showResults = true;
            $locked = true;
        }

        return $this->render('predictionPicks.twig', [
            'page' => 'picks',
            'awards' => $awards,
            'showResults' => $showResults,
            'locked' => $locked,
            'victoryMessageLimit' => FantasyUser::VICTORY_MESSAGE_LIMIT,
            'fantasyUser' => $fantasyUser,
            'viewingOwn' => $viewingOwn,
        ]);
    }

    public function rules(): Response
    {
        return $this->render('predictionRules.twig', [
            'page' => 'rules'
        ]);
    }

    public function join(EntityManagerInterface $em, Request $request, SessionInterface $session, AuditService $auditService, ConfigService $configService): RedirectResponse
    {
        if ($configService->getConfig()->isPagePublic('results')) {
            return $this->redirectToRoute('predictionLeaderboard');
        }

        /** @var BaseUser $user */
        $user = $this->getUser();
        if (!$user->isLoggedIn() || !$request->get('nonce') || $request->get('nonce') !== $session->get('nonce')) {
            return $this->redirectToRoute('predictions');
        }

        /** @var User $user */
        if (!$user->getFantasyUser()) {
            $fantasyUser = new FantasyUser();
            $fantasyUser->setUser($user);
            $em->persist($fantasyUser);

            $auditService->add(
                new Action('fantasy-signed-up')
            );

            $em->flush();
        }

        return $this->redirectToRoute('predictions');
    }

    public function updatePick(Award $award, EntityManagerInterface $em, PredictionService $predictionService, AuditService $auditService, Request $request): JsonResponse
    {
        if (!$award->isEnabled()) {
            throw new NotFoundHttpException();
        }

        if ($predictionService->arePredictionsLocked()) {
            return $this->json(['error' => 'The 2024 Fantasy League has closed. You can no longer make changes to your picks.'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$user->getFantasyUser()) {
            return $this->json(['error' => 'You haven\'t yet signed up for the 2024 Fantasy League.', 400]);
        }

        $prediction = $user->getFantasyUser()->getPredictionForAward($award);

        $post = $request->request;

        if ($post->get('nominee') === '') {
            if ($prediction) {
                $em->remove($prediction);
            }
            $nominee = null;
        } else {
            $nominee = $em->getRepository(Nominee::class)->find($post->get('nominee'));
            if (!$nominee || $nominee->getAward() !== $award) {
                return $this->json(['error' => 'Invalid pick selected.'], 400);
            }

            if (!$prediction) {
                $prediction = new FantasyPrediction();
                $prediction->setFantasyUser($user->getFantasyUser());
                $prediction->setAward($award);
            }
            $prediction->setNominee($nominee);
            $em->persist($prediction);
        }

        $auditService->add(
            new Action('fantasy-picked', $award->getId(), $nominee ? $nominee->getId() : null)
        );

        $em->flush();
        return $this->json(['success' => true]);
    }

    public function updateDetails(Request $request, EntityManagerInterface $em, PredictionService $predictionService, AuditService $auditService, FileService $fileService): RedirectResponse
    {
        if ($predictionService->arePredictionsLocked()) {
            $this->addFlash('formError', 'The 2024 Fantasy League has closed. You can no longer make changes to your details.');
            return $this->redirectToRoute('predictions');
        }

        $post = $request->request;

        if (!$post->get('name')) {
            return $this->redirectToRoute('predictions');
        }

        /** @var User $user */
        $user = $this->getUser();
        $fantasyUser = $user->getFantasyUser();

        if (!$fantasyUser) {
            $this->addFlash('formError', 'You haven\'t yet signed up for the 2024 Fantasy League.');
            return $this->redirectToRoute('predictions');
        }

        $name = substr($post->get('name'), 0, FantasyUser::NAME_LIMIT) ?: 'Anonymous';
        $victoryMessage = substr($post->get('victory-message'), 0, FantasyUser::VICTORY_MESSAGE_LIMIT) ?: null;

        $fantasyUser->setName($name);
        $fantasyUser->setVictoryMessage($victoryMessage);

        /** @var UploadedFile $file */
        $file = $request->files->get('avatar');
        if ($file) {
            $fileResult = $this->processAvatar($file, $request, $predictionService, $fileService);
        } else {
            $fileResult = true;
        }

        $auditService->add(
            new Action('fantasy-updated-details'),
            new TableHistory(FantasyUser::class, $fantasyUser->getId(), $post->all())
        );

        $em->persist($fantasyUser);

        if (!$fileResult) {
            $this->addFlash('formSuccess', 'Your Fantasy League details have been successfully updated.');
        }

        return $this->redirectToRoute('predictions');
    }

    private function processAvatar(UploadedFile $file, Request $request, PredictionService $predictionService, FileService $fileService): RedirectResponse|bool
    {
        if ($predictionService->arePredictionsLocked()) {
            $this->addFlash('formError', 'The 2024 Fantasy League has closed. You can no longer make changes to your details.');
            return $this->redirectToRoute('predictions');
        }

        if ($file->getSize() > FantasyUser::MAX_AVATAR_SIZE) {
            $this->addFlash('formError', 'Uploaded avatar is too large (Limit: 1 MB).');
            return false;
        }

        /** @var FantasyUser $fantasyUser */
        $fantasyUser = $this->getUser()->getFantasyUser();

        try {
            $file = $fileService->handleUploadedFile(
                $request->files->get('avatar'),
                'FantasyUser.avatar',
                'predictionAvatars',
                null
            );

            $filepath = __DIR__ . '/../../public/uploads/' . $file->getRelativePath();
            $imageType = exif_imagetype($filepath);

            if (!in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) {
                $this->addFlash('formError', 'Invalid file type: must be one of jpeg, png or gif');
                return false;
            }

            $image = imagecreatefromstring(file_get_contents($filepath));
            $w = imagesx($image);
            $h = imagesy($image);
            if ($w !== $h) {
                $dimension = min($w, $h);
                $image = imagecrop($image, [
                    'x' => ($w - $dimension) / 2,
                    'y' => ($h - $dimension) / 2,
                    'width' => $dimension,
                    'height' => $dimension
                ]);

                if ($imageType === IMAGETYPE_JPEG) {
                    imagejpeg($image, $filepath);
                } elseif ($imageType === IMAGETYPE_PNG) {
                    imagepng($image, $filepath);
                } elseif ($imageType === IMAGETYPE_GIF) {
                    imagegif($image, $filepath);
                }
            }
        } catch (Exception $e) {
            $this->addFlash('formError', $e->getMessage());
            return false;
        }

        if ($fantasyUser->getAvatar()) {
            $fileService->deleteFile($fantasyUser->getAvatar());
        }

        $fantasyUser->setAvatar($file);

        return true;
    }

    public function leaderboard(EntityManagerInterface $em): Response
    {
        $fantasyUsers = $em->createQueryBuilder()
            ->select('fu')
            ->from(FantasyUser::class, 'fu')
            ->where('fu.score > 0')
            ->andWhere('fu.rank IS NOT NULL')
            ->orderBy('fu.rank', 'ASC')
            ->getQuery()
            ->getResult();

        $champions = array_values(array_filter($fantasyUsers, function (FantasyUser $fantasyUser) {
            return $fantasyUser->getRank() === 1;
        }));

        $almost = array_values(array_filter($fantasyUsers, function (FantasyUser $fantasyUser) {
            return $fantasyUser->getRank() > 1 && $fantasyUser->getRank() <= 5;
        }));

        $plebs = array_values(array_filter($fantasyUsers, function (FantasyUser $fantasyUser) {
            return $fantasyUser->getRank() > 5;
        }));

        $awardCount = $em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Award::class, 'a')
            ->where('a.enabled = true')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('predictionLeaderboard.twig', [
            'page' => 'leaderboard',
            'awardCount' => $awardCount,
            'champions' => $champions,
            'almost' => $almost,
            'plebs' => $plebs,
        ]);
    }
}
