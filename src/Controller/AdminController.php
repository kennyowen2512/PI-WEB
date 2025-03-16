<?php
// src/Controller/AdminController.php // k k k 

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\User;
use App\Form\AnnonceType;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Vérifie que l'utilisateur est bien un admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('back_office/dashboard.html.twig');
    }

    // Gestion des utilisateurs
    #[Route('/users', name: 'user_index', methods: ['GET'])]
    public function userIndex(EntityManagerInterface $entityManager): Response
    {
        $users = $entityManager->getRepository(User::class)->findAll();
        return $this->render('back_office/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'user_edit', methods: ['GET', 'POST'])]
    public function userEdit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('back_office/user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function userDelete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'L\'utilisateur a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    // Gestion des annonces
    #[Route('/annonces', name: 'annonce_index', methods: ['GET'])]
    public function annonceIndex(EntityManagerInterface $entityManager): Response
    {
        $annonces = $entityManager->getRepository(Annonce::class)->findAll();
        return $this->render('back_office/annonce/index.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/annonces/{id}/edit', name: 'annonce_edit', methods: ['GET', 'POST'])]
    public function annonceEdit(Request $request, Annonce $annonce, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de la photo (comme dans ton code existant)
            $entityManager->flush();
            return $this->redirectToRoute('admin_annonce_index');
        }

        return $this->render('back_office/annonce/edit.html.twig', [
            'form' => $form->createView(),
            'annonce' => $annonce,
        ]);
    }

    #[Route('/annonces/{id}/delete', name: 'annonce_delete', methods: ['POST'])]
    public function annonceDelete(Request $request, Annonce $annonce, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $annonce->getId(), $request->request->get('_token'))) {
            // Supprimer la photo associée (comme dans ton code existant)
            $entityManager->remove($annonce);
            $entityManager->flush();
            $this->addFlash('success', 'L\'annonce a été supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_annonce_index');
    }
}