<?php
namespace App\Controller;

use App\Entity\Action;
use App\Entity\Permission;
use App\Entity\TableHistory;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\ConfigService;
use App\Service\SteamService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PeopleController extends AbstractController
{
    public function indexAction(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(User::class)->findBy(
            ['special' => true], ['name' => 'ASC']
        );

        return $this->render('people.html.twig', [
            'title' => 'People',
            'users' => $users
        ]);
    }

    public function viewAction($steamID, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['steamId' => $steamID]);

        if (!$user || !$user->isSpecial()) {
            $this->addFlash('error', 'Invalid SteamID provided.');
            return $this->redirectToRoute('people');
        }

        $permissions = $em->getRepository(Permission::class)->findAll();

        return $this->render('viewPerson.html.twig', [
            'title' => $user->getName(),
            'user' => $user,
            'permissions' => $permissions
        ]);
    }

    public function editAction($steamID, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['steamId' => $steamID]);

        if (!$user || !$user->isSpecial()) {
            $this->addFlash('error', 'Invalid SteamID provided.');
            return $this->redirectToRoute('people');
        }

        return $this->render('editPerson.html.twig', [
            'title' => $user->getName(),
            'user' => $user
        ]);
    }

    public function postAction($steamID, ConfigService $configService, EntityManagerInterface $em, Request $request, AuthorizationCheckerInterface $authChecker, AuditService $auditService): RedirectResponse
    {
        if ($configService->isReadOnly()) {
            $this->addFlash('error', 'The site is currently in read-only mode. No changes can be made.');
            return $this->redirectToRoute('people');
        }

        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['steamId' => $steamID]);

        if (!$user || !$user->isSpecial()) {
            $this->addFlash('error', 'Invalid SteamID provided.');
            return $this->redirectToRoute('people');
        }

        $post = $request->request;

        // Remove group
        if ($post->get('RemoveGroup') && $authChecker->isGranted('ROLE_PROFILE_EDIT_GROUPS')) {
            $groupName = $post->get('RemoveGroup');

            /** @var Permission $group */
            $group = $em->getRepository(Permission::class)->find($groupName);
            $user->removePermission($group);
            $em->persist($user);

            $this->addFlash('formSuccess', 'Permission ' . $groupName . ' successfully removed.');

            $auditService->add(
                new Action('profile-group-removed', $user->getId(), $groupName)
            );
            $em->flush();
        }

        // Add group
        if ($post->get('AddGroup') && $authChecker->isGranted('ROLE_PROFILE_EDIT_GROUPS')) {
            $groupName = trim(strtolower($post->get('GroupName')));

            /** @var Permission $group */
            $group = $em->getRepository(Permission::class)->find($groupName);
            if (!$group) {
                $this->addFlash('formError', 'Invalid group name.');
            } elseif ($user->getPermissions()->contains($group)) {
                $this->addFlash('formError', 'User already has that permission.');
            } else {
                $user->addPermission($group);
                $auditService->add(
                    new Action('profile-group-added', $user->getId(), $groupName)
                );
                $em->flush();

                $this->addFlash('formSuccess', 'Permission ' . $groupName . ' successfully added.');
            }
        }

        // Edit details (primary role and email)
        if ($post->get('action') === 'edit-details' && $authChecker->isGranted('ROLE_PROFILE_EDIT_DETAILS')) {
            $user->setPrimaryRole($post->get('PrimaryRole'));
            $user->setEmail($post->get('Email'));
            $em->persist($user);

            $auditService->add(
                new Action('profile-details-updated', $user->getId()),
                new TableHistory(User::class, $user->getId(), $post->all())
            );

            $em->flush();

            $this->addFlash('formSuccess', 'Details successfully updated.');
        }

        if ($post->get('action') === 'edit-notes' && $authChecker->isGranted('ROLE_PROFILE_EDIT_NOTES')) {
            $user->setNotes($post->get('Notes'));
            $em->persist($user);

            $auditService->add(
                new Action('profile-notes-updated', $user->getId()),
                new TableHistory(User::class, $user->getId(), $post->all())
            );
            $em->flush();

            $this->addFlash('formSuccess', 'Notes successfully updated.');
        }

        return $this->redirectToRoute('viewPerson', ['steamID' => $steamID]);
    }

    public function permissionsAction(): Response
    {
        return $this->render('permissions.html.twig');
    }

    public function newAction(EntityManagerInterface $em): Response
    {
        $permissions = $em->getRepository(Permission::class)->findAll();
        return $this->render('addPerson.html.twig', [
            'permissions' => $permissions
        ]);
    }

    public function searchAction(EntityManagerInterface $em, Request $request, ConfigService $configService, AuditService $auditService, SteamService $steam): JsonResponse
    {
        $post = $request->request;

        $steamId = $steam->stringToSteamId($post->get('id'));
        if (!$steamId) {
            return $this->json(['error' => 'Invalid SteamID or URL provided.']);
        }

        $profile = $steam->getProfile($steamId);
        if (!$profile) {
            return $this->json(['error' => 'no matches']);
        }

        $repo = $em->getRepository(User::class);

        /** @var User $user */
        $user = $repo->findOneBy(['steamId' => $profile['steamId64']]);
        if (!$user) {
            $user = new User();
            $user
                ->setSteamId($profile['steamId64'])
                ->setName($profile['nickname'])
                ->setAvatar($profile['avatar']);
        }

        if ($user->isSpecial()) {
            return $this->json([
                'error' => 'already special',
                'name' => $user->getName()
            ]);
        }

        if ($post->getBoolean('add')) {
            if ($configService->isReadOnly()) {
                return $this->json(['error' => 'The site is currently in read-only mode. No changes can be made.']);
            }

            // Make the user special and give them the starting permission
            $user->setSpecial(true);

            if ($post->get('permission')) {
                /** @var Permission $permission */
                $permission = $em->getRepository(Permission::class)->find($post->get('permission'));
                if (!$permission) {
                    return $this->json(['error' => 'Invalid permission specified.']);
                }
                $user->addPermission($permission);
            }
            $em->persist($user);
            $em->flush();

            $auditService->add(
                new Action('user-added', $user->getId(), $post->get('permission') ?: null)
            );

            return $this->json(['success' => true]);
        }

        return $this->json([
            'success' => true,
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'steamID' => $user->getSteamId()
        ]);
    }
}
