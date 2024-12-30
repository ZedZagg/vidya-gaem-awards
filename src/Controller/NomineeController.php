<?php
namespace App\Controller;

use App\Entity\UserNomination;
use App\Entity\UserNominationGroup;
use App\Service\AuditService;
use App\Service\ConfigService;
use App\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Action;
use App\Entity\Award;
use App\Entity\Nominee;
use App\Entity\TableHistory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class NomineeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly ConfigService $configService,
        private readonly AuditService $auditService,
    ) {
    }

    public function indexAction(?string $awardID, Request $request): Response
    {
        $query = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Award::class, 'a', 'a.id')
            ->where('a.enabled = true')
            ->orderBy('a.order', 'ASC');

        if (!$this->authChecker->isGranted('ROLE_AWARDS_SECRET')) {
            $query->andWhere('a.secret = false');
        }
        $awards = $query->getQuery()->getResult();

        $awardVariables = [];

        if ($awardID) {
            /** @var Award $award */
            $award = $this->em->getRepository(Award::class)->find($awardID);

            if (!$award || ($award->isSecret() && !$this->authChecker->isGranted('ROLE_AWARDS_SECRET'))) {
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

            $nomineeNames = array_map(function (Nominee $nominee) {
                return $nominee->getName();
            }, $nomineesArray);


            // Get all userNominationGroups for the Award, sorted by number of nominations that the group has
            // (use the relationship between UserNominationGroup and UserNomination to count nominations)
            $userNominationGroups = $this->em->createQueryBuilder()
                ->select('ung')
                ->from(UserNominationGroup::class, 'ung')
                ->where('ung.award = :award')
                ->setParameter('award', $award)
                ->orderBy('SIZE(ung.nominations)', 'DESC')
                ->addOrderBy('ung.name', 'ASC')
                ->getQuery()
                ->getResult();

            $awardVariables = [
                'alphabeticalSort' => $alphabeticalSort,
                'autocompleters' => $autocompleters,
                'nominees' => $nomineesArray,
                'nomineeNames' => $nomineeNames,
                'userNominationGroups' => $userNominationGroups,
            ];
        }

        return $this->render('nominees.html.twig', array_merge([
            'title' => 'Nominee Manager',
            'awards' => $awards,
            'award' => $award ?? false,
        ], $awardVariables));
    }

    public function postAction(string $awardID, Request $request, FileService $fileService): Response
    {
        /** @var Award $award */
        $award = $this->em->getRepository(Award::class)->find($awardID);

        if ($response = $this->permissionCheck($award)) {
            return $response;
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

            if ($post->has('group')) {
                $group = $this->em->getRepository(UserNominationGroup::class)->find($post->get('group'));
                if (!$group || $group->getAward() !== $award) {
                    return $this->json(['error' => 'Invalid nomination group. Refresh the page and try again.']);
                } elseif ($group->getNominee()) {
                    return $this->json(['error' => 'This nomination group is already linked to a nominee. Refresh the page and try again.']);
                }

                $group->setNominee($nominee);
                $this->em->persist($group);
            }
        } else {
            $nominee = $award->getNominee($post->get('id'));
            if (!$nominee) {
                $this->json(['error' => 'Invalid nominee specified.']);
            }
        }

        if ($action === 'delete') {
            $this->em->remove($nominee);
            $this->auditService->add(
                new Action('nominee-delete', $award->getId(), $nominee->getShortName())
            );
            $this->em->flush();

            return $this->json(['success' => true]);
        }

        if (strlen(trim($post->get('name', ''))) === 0) {
            return $this->json(['error' => 'You need to enter a name.']);
        }

        if ($request->files->get('image')) {
            try {
                $file = $fileService->handleUploadedFile(
                    $request->files->get('image'),
                    'Nominee.image',
                    'nominees',
                    $award->getId() . '--' . $nominee->getShortName()
                );
            } catch (Exception $e) {
                return $this->json(['error' => $e->getMessage()]);
            }

            if ($nominee->getImage()) {
                $fileService->deleteFile($nominee->getImage());
            }

            $nominee->setImage($file);
        }

        $nominee
            ->setName($post->get('name'))
            ->setSubtitle($post->get('subtitle'))
            ->setFlavorText($post->get('flavorText'));
        $this->em->persist($nominee);
        $this->em->flush();

        $this->auditService->add(
            new Action('nominee-' . $action, $award->getId(), $nominee->getShortName()),
            new TableHistory(Nominee::class, $nominee->getId(), $post->all())
        );

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    public function exportNomineesAction(): Response
    {
        /** @var Award[] $awards */
        $awards = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Award::class, 'a')
            ->where('a.enabled = true')
            ->orderBy('a.order', 'ASC')
            ->getQuery()
            ->getResult();

        $csv = Writer::createFromString();
        $csv->insertOne([
            'Award Name',
            'Award Subtitle',
            'Nominee Name',
            'Nominee Subtitle',
            'Flavor Text'

        ]);

        foreach ($awards as $award) {
            $nominees = $award->getNominees();
            foreach ($nominees as $nominee) {
                $csv->insertOne([
                    $award->getName(),
                    $award->getSubtitle(),
                    $nominee->getName(),
                    $nominee->getSubtitle(),
                    $nominee->getFlavorText()
                ]);
            }
        }

        $response = new Response($csv->toString());
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'vga-2024-award-nominees.csv'
        );

        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    public function exportUserNominationsAction(): Response
    {
        /** @var Award[] $awards */
        $awards = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Award::class, 'a')
            ->where('a.enabled = true')
            ->orderBy('a.order', 'ASC')
            ->getQuery()
            ->getResult();

        $csv = Writer::createFromString();
        $csv->insertOne([
            'Award Name',
            'Award Subtitle',
            'Nomination',
            'Count'
        ]);

        foreach ($awards as $award) {
            $nominations = $award->getUserNominations();
            foreach ($nominations as $nomination) {
                $csv->insertOne([
                    $award->getName(),
                    $award->getSubtitle(),
                    $nomination['title'],
                    $nomination['count'],
                ]);
            }
        }

        $response = new Response($csv->toString());
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'vga-2024-user-nominations.csv'
        );

        $response->headers->set('Content-Disposition', $disposition);
        return $response;
    }

    public function nominationGroupIgnoreAction(
        string $awardID,
        Request $request,
    ): Response {
        /** @var Award $award */
        $award = $this->em->getRepository(Award::class)->find($awardID);

        if ($response = $this->permissionCheck($award)) {
            return $response;
        }

        $groupId = $request->request->get('group');
        $group = $this->em->getRepository(UserNominationGroup::class)->find($groupId);

        if (!$group || $group->getAward() !== $award) {
            return $this->json(['error' => 'Invalid nomination group specified.']);
        }

        if ($group->getMergedInto()) {
            return $this->json(['error' => 'Cannot change the ignored status of a nomination group that has previously been merged.']);
        }

        if ($group->getNominee()) {
            return $this->json(['error' => 'Cannot change the ignored status of a nomination group that is linked to a nominee.']);
        }

        $ignore = $request->request->get('ignore') === 'true';
        $group->setIgnored($ignore);

        $this->auditService->add(
            new Action($ignore ? 'nomination-group-ignored' : 'nomination-group-unignored', $award->getId(), $group->getId()),
        );

        $this->em->persist($group);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    public function nominationGroupMergeAction(
        string $awardID,
        Request $request,
    ): Response
    {
        /** @var Award $award */
        $award = $this->em->getRepository(Award::class)->find($awardID);

        if ($response = $this->permissionCheck($award)) {
            return $response;
        }

        $fromId = $request->request->get('from');
        $fromGroup = $this->em->getRepository(UserNominationGroup::class)->find($fromId);

        $toId = $request->request->get('to');
        $toGroup = $this->em->getRepository(UserNominationGroup::class)->find($toId);

        if (!$fromGroup || $fromGroup->getAward() !== $award || !$toGroup || $toGroup->getAward() !== $award) {
            return $this->json(['error' => 'Invalid nomination group specified.']);
        }

        if ($fromGroup->getMergedInto()) {
            return $this->json(['error' => 'This nomination group has already been merged.']);
        }

        if ($toGroup->getMergedInto()) {
            return $this->json(['error' => 'You cannot select a nomination group that has already been merged.']);
        }

        if ($fromGroup->getNominee()) {
            return $this->json(['error' => 'Cannot merge from a nomination group that is linked to a nominee. (You can still merge into it.)']);
        }

        $fromGroup->setIgnored(false);
        $fromGroup->setMergedInto($toGroup);
        $this->em->persist($fromGroup);

        foreach ($fromGroup->getNominations() as $nomination) {
            $nomination->setNominationGroup($toGroup);
            $nomination->setOriginalGroup($fromGroup);
            $this->em->persist($nomination);
        }

        $this->auditService->add(
            new Action('nomination-group-merged', $fromGroup->getId(), $toGroup->getId()),
        );

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    private function permissionCheck(?Award $award): ?Response
    {
        if ($this->configService->isReadOnly()) {
            return $this->json(['error' => 'The site is currently in read-only mode. No changes can be made.']);
        }

        if (!$award || ($award->isSecret() && !$this->authChecker->isGranted('ROLE_AWARDS_SECRET'))) {
            return $this->json(['error' => 'Invalid award specified.']);
        }

        if (!$award->isEnabled()) {
            return $this->json(['error' => 'Award isn\'t enabled.']);
        }

        return null;
    }

    public function nominationGroupDemergeAction(
        string $awardID,
        Request $request,
    ): Response
    {
        /** @var Award $award */
        $award = $this->em->getRepository(Award::class)->find($awardID);

        if ($response = $this->permissionCheck($award)) {
            return $response;
        }

        $groupId = $request->request->get('group');
        $group = $this->em->getRepository(UserNominationGroup::class)->find($groupId);

        if (!$group || $group->getAward() !== $award) {
            return $this->json(['error' => 'Invalid nomination group specified.']);
        }

        if (!$group->getMergedInto()) {
            return $this->json(['error' => 'This nomination group has not been merged.']);
        }

        $mergedInto = $group->getMergedInto();

        $group->setMergedInto(null);

        $nominations = $this->em->getRepository(UserNomination::class)->findBy(['originalGroup' => $group]);

        foreach ($nominations as $nomination) {
            $nomination->setNominationGroup($group);
            $nomination->setOriginalGroup(null);
            $this->em->persist($nomination);
        }

        $this->auditService->add(
            new Action('nomination-group-demerged', $group->getId(), $mergedInto->getId())
        );

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    public function nominationGroupUnlinkAction(string $awardID, Request $request)
    {
        /** @var Award $award */
        $award = $this->em->getRepository(Award::class)->find($awardID);

        if ($response = $this->permissionCheck($award)) {
            return $response;
        }

        $groupId = $request->request->get('group');
        $group = $this->em->getRepository(UserNominationGroup::class)->find($groupId);

        if (!$group || $group->getAward() !== $award) {
            return $this->json(['error' => 'Invalid nomination group specified.']);
        }

        $group->setNominee(null);
        $this->em->persist($group);

        $this->auditService->add(
            new Action('nomination-group-updated', $group->getId()),
            new TableHistory(UserNominationGroup::class, $group->getId(), ['action' => 'unlink'])
        );

        $this->em->flush();

        return $this->json(['success' => true]);
    }
}
