<?php
namespace AppBundle\Controller;

use AppBundle\Service\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Action;
use AppBundle\Entity\Award;
use AppBundle\Entity\Nominee;
use AppBundle\Entity\TableHistory;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class NomineeController extends Controller
{
    public function indexAction(?string $awardID, EntityManagerInterface $em, AuthorizationCheckerInterface $authChecker, Request $request)
    {
        $query = $em->createQueryBuilder()
            ->select('a')
            ->from(Award::class, 'a', 'a.id')
            ->where('a.enabled = true')
            ->orderBy('a.order', 'ASC');

        if (!$authChecker->isGranted('ROLE_AWARDS_SECRET')) {
            $query->andWhere('a.secret = false');
        }
        $awards = $query->getQuery()->getResult();

        $awardVariables = [];

        if ($awardID) {
            /** @var Award $award */
            $award = $em->getRepository(Award::class)->find($awardID);

            if (!$award || ($award->isSecret() && !$authChecker->isGranted('ROLE_AWARDS_SECRET'))) {
                $this->addFlash('error', 'Invalid award ID specified.');
                return $this->redirectToRoute('nomineeManager');
            }

            $alphabeticalSort = $request->get('sort') === 'alphabetical';

            $autocompleters = array_filter($award->getUserNominations(), function ($un) {
                return $un['count'] >= 3;
            });
            $autocompleters = array_map(function ($un) {
                return $un['title'];
            }, $autocompleters);
            sort($autocompleters);

            $nomineesArray = [];
            /** @var Nominee $nominee */
            foreach ($award->getNominees() as $nominee) {
                $nomineesArray[$nominee->getShortName()] = $nominee;
            }

            $awardVariables = [
                'alphabeticalSort' => $alphabeticalSort,
                'autocompleters' => $autocompleters,
                'nominees' => $nomineesArray
            ];
        }

        return $this->render('nominees.twig', array_merge([
            'title' => 'Nominee Manager',
            'awards' => $awards,
            'award' => $award ?? false,
        ], $awardVariables));
    }

    public function postAction(string $awardID, ConfigService $configService, EntityManagerInterface $em, AuthorizationCheckerInterface $authChecker, Request $request, UserInterface $user)
    {
        if ($configService->isReadOnly()) {
            return $this->json(['error' => 'The site is currently in read-only mode. No changes can be made.']);
        }

        /** @var Award $award */
        $award = $em->getRepository(Award::class)->find($awardID);

        if (!$award || ($award->isSecret() && !$authChecker->isGranted('ROLE_AWARDS_SECRET'))) {
            return $this->json(['error' => 'Invalid award specified.']);
        } elseif (!$award->isEnabled()) {
            return $this->json(['error' => 'Award isn\'t enabled.']);
        }

        $post = $request->request;
        $action = $post->get('action');

        if (!in_array($action, ['new', 'edit', 'delete'], true)) {
            return $this->json(['error' => 'Invalid action specified.']);
        }

        if ($action === 'new') {
            if ($award->getNominee($post->get('id'))) {
                return $this->json(['error' => 'A nominee with that ID already exists for this award.']);
            } elseif (!$post->get('id')) {
                return $this->json(['error' => 'You need to enter an ID.']);
            } elseif (preg_match('/[^a-z0-9-]/', $post->get('id'))) {
                return $this->json(['error' => 'ID can only contain lowercase letters, numbers and dashes.']);
            }

            $nominee = new Nominee();
            $nominee
                ->setAward($award)
                ->setShortName($post->get('id'));
        } else {
            $nominee = $award->getNominee($post->get('id'));
            if (!$nominee) {
                $this->json(['error' => 'Invalid nominee specified.']);
            }
        }

        if ($action === 'delete') {
            $em->remove($nominee);

            $action = new Action('nominee-delete');
            $action->setUser($user)
                ->setPage(__CLASS__)
                ->setData1($award->getId())
                ->setData2($nominee->getShortName());
            $em->persist($action);

            $em->flush();

            return $this->json(['success' => true]);
        }

        if (strlen(trim($post->get('name', ''))) === 0) {
            return $this->json(['error' => 'You need to enter a name.']);
        }

        if (substr($post->get('image', ''), 0, 7) === 'http://') {
            return $this->json(['error' => 'Image URL must start with https://.']);
        }

        $nominee
            ->setName($post->get('name'))
            ->setSubtitle($post->get('subtitle'))
            ->setImage($post->get('image'))
            ->setFlavorText($post->get('flavorText'));
        $em->persist($nominee);

        $action = new Action('nominee-' . $action);
        $action->setUser($user)
            ->setPage(__CLASS__)
            ->setData1($award->getId())
            ->setData2($nominee->getShortName());
        $em->persist($action);

        $history = new TableHistory();
        $history->setUser($user)
            ->setTable('Nominee')
            ->setEntry($award->getId() . '/' . $nominee->getShortName())
            ->setValues($post->all());
        $em->persist($history);
        $em->flush();

        return $this->json(['success' => true]);
    }
}
