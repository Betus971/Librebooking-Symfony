<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserRoleType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminUserController extends AbstractController
{
    #[Route('/admin/user', name: 'app_admin_user')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function index(
        Request $request,
        UserRepository $userRepository,
        #[Autowire(param: 'app.pagination.per_page')] int $perPage
    ): Response {
        $q     = trim((string) $request->query->get('q', ''));
        $page  = max(1, $request->query->getInt('page', 1));

        $result = $userRepository->searchPaginated($q, $page, $perPage);

        // Construction d'une SlidingPagination KnpPaginator avec un total déjà
        // calculé par le repo → permet d'utiliser le template DSFR unifié
        // sans changer la signature de searchPaginated().
        $pagination = new SlidingPagination([]);
        $pagination->setItems($result['users']);
        $pagination->setCurrentPageNumber($page);
        $pagination->setItemNumberPerPage($perPage);
        $pagination->setTotalItemCount((int) $result['total']);
        $pagination->setUsedRoute('app_admin_user');
        // Construction manuelle → initialiser ces tableaux pour éviter le
        // TypeError dans SlidingPagination::getPaginationData().
        $pagination->setPaginatorOptions([]);
        $pagination->setCustomParameters([]);
        // Template par défaut (knp_paginator.yaml) non appliqué en construction
        // manuelle → on le pose explicitement.
        $pagination->setTemplate('_partials/_pagination.html.twig');
        if ($q !== '') {
            $pagination->setParam('q', $q);
        }

        return $this->render('admin_user/index.html.twig', [
            'users'      => $result['users'],
            'total'      => $result['total'],
            'q'          => $q,
            'limit'      => $perPage,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/{id}/roles', name: 'admin_users_edit_roles')]
    #[IsGranted('ROLE_SUPER_ADMIN')] // P0.1 : seul un super-admin attribue des rôles (évite l'auto-escalade).
    public function editRoles(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserRoleType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); // Sauvegarde les rôles cochés

            $this->addFlash('success', 'Les droits de ' . $user->getEmail() . ' ont été mis à jour.');
            return $this->redirectToRoute('app_admin_user');
        }

        return $this->render('admin_user/edit_roles.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }


    #[Route('/{id}/groups', name: 'admin_users_edit_groups')]
    #[IsGranted('ROLE_SUPER_ADMIN')] // P0.1 : l'affectation de groupes est une opération privilégiée.
    public function editGroups(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(\App\Form\UserGroupType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Les groupes de ' . $user->getEmail() . ' ont été mis à jour.');
            return $this->redirectToRoute('app_admin_user');
        }

        return $this->render('admin_user/edit_groups.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
    /* // -----------------------------------------------------------------------
    // FUTURE FONCTIONNALITÉ : IMPORT UTILISATEUR DEPUIS LE SSO / LDAP
    // À décommenter une fois connecté au réseau d'entreprise (Preprod)
    // -----------------------------------------------------------------------

    use Symfony\Component\Ldap\Ldap;

    #[Route('/admin/user/import', name: 'admin_user_import_sso')]
    public function importFromSso(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        // 1. Récupération de la recherche (ex: "Dupont")
        $query = $request->query->get('q');

        if ($query) {
            // 2. Connexion au LDAP (Exemple théorique)
            // $ldap = Ldap::create('ext_ldap', ['connection_string' => 'ldap://mon-serveur-sso:389']);
            // $ldap->bind('cn=admin,dc=example,dc=com', 'password');
            // $queryLdap = $ldap->query('dc=example,dc=com', '(&(objectClass=person)(cn=*'.$query.'*))');
            // $results = $queryLdap->execute();

            // 3. On transformerait les résultats en tableau simple pour l'afficher
            // $usersFound = ...;
        }

        // 4. Si on clique sur "Importer", on crée le User en local
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            // Vérif si existe déjà
            if (!$userRepository->findOneBy(['email' => $email])) {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles([]); // Rôles par défaut
                // $user->setPassword('...'); // Pas de mot de passe, c'est le SSO qui gère !

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Utilisateur importé du SSO ! Vous pouvez maintenant lui donner des droits.');
                return $this->redirectToRoute('admin_users_list');
            }
        }

        return $this->render('admin_user/import_sso.html.twig', [
            // 'results' => $results ?? []
        ]);
    }
    */
}
