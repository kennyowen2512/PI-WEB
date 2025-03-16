<?php

namespace App\Controller;

use App\Entity\Annonce;
use App\Entity\User;
use App\Form\AnnonceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/annonces', name: 'front_annonce_')]  // Préfixe pour le front office
#[Route('/admin/annonces', name: 'admin_annonce_')]  // Préfixe pour le back office
class AnnonceController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Récupérer toutes les annonces depuis la base de données
        $annonces = $entityManager->getRepository(Annonce::class)->findAll();

        // Choisir le template en fonction du rôle
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->render('back_office/annonce/index.html.twig', [
                'annonces' => $annonces,
            ]);
        } else {
            return $this->render('front_office/annonce/index.html.twig', [
                'annonces' => $annonces,
            ]);
        }
    }

    #[Route('/mes-annonces', name: 'mes_annonces', methods: ['GET'])]
public function mesAnnonces(EntityManagerInterface $entityManager): Response
{
    $user = $this->getUser(); // Récupérer l'utilisateur connecté

    if (!$user) {
        return $this->redirectToRoute('app_login'); // Redirige si l'utilisateur n'est pas connecté
    }

    // Récupérer uniquement les annonces postées par l'utilisateur connecté
    $annonces = $entityManager->getRepository(Annonce::class)->findBy([
        'proprietaire' => $user, // Filtre par l'objet User
    ]);

    return $this->render('front_office/annonce/mes_annonces.html.twig', [
        'annonces' => $annonces,
    ]);
}

#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
{
    $annonce = new Annonce();

    // Lier l'annonce à l'utilisateur connecté
    $user = $this->getUser();
    if ($user instanceof User) {
        $annonce->setProprietaire($user); // Lier l'objet User directement
        // $annonce->setnumProprietaire($user->getnumero()); // Supprimez cette ligne si la propriété n'existe pas
    }

    // Crée le formulaire
    $form = $this->createForm(AnnonceType::class, $annonce);

    // Traite la soumission du formulaire
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Gérer l'upload de la photo (si nécessaire)
        $photoFile = $form->get('photo')->getData();
        if ($photoFile) {
            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            try {
                $photoFile->move(
                    $this->getParameter('photos_directory'), // Paramètre défini dans services.yaml
                    $newFilename
                );
            } catch (FileException $e) {
                $this->addFlash('error', 'Une erreur s\'est produite lors de l\'upload de l\'image.');
            }

            $annonce->setPhoto($newFilename);
        }

        // Enregistrer l'annonce en base de données
        $entityManager->persist($annonce);
        $entityManager->flush();

        // Rediriger vers la liste des annonces
        return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'admin_annonce_index' : 'front_annonce_index');
    }

    // Afficher le formulaire
    return $this->render('front_office/annonce/new.html.twig', [
        'form' => $form->createView(),
    ]);
}

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Annonce $annonce): Response
    {
        // Choisir le template en fonction du rôle
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->render('back_office/annonce/show.html.twig', [
                'annonce' => $annonce,
            ]);
        } else {
            return $this->render('front_office/annonce/show.html.twig', [
                'annonce' => $annonce,
            ]);
        }
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Annonce $annonce, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Crée le formulaire en utilisant AnnonceType et l'annonce existante
        $form = $this->createForm(AnnonceType::class, $annonce);

        // Traite la soumission du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de la nouvelle photo (si une nouvelle photo est fournie)
            $photoFile = $form->get('photo')->getData();

            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '.' . $photoFile->guessExtension();

                // Déplacer le fichier uploadé dans le répertoire souhaité
                try {
                    $photoFile->move(
                        $this->getParameter('photos_directory'), // Paramètre défini dans services.yaml
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'exception en cas d'erreur lors de l'upload
                    $this->addFlash('error', 'Une erreur s\'est produite lors de l\'upload de l\'image.');
                }

                // Supprimer l'ancienne photo si elle existe
                if ($annonce->getPhoto()) {
                    $oldPhotoPath = $this->getParameter('photos_directory') . '/' . $annonce->getPhoto();
                    if (file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
                    }
                }

                // Enregistrer le chemin de la nouvelle photo dans l'entité
                $annonce->setPhoto($newFilename);
            }

            // Enregistrer les modifications en base de données
            $entityManager->flush();

            // Rediriger vers la liste des annonces
            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'admin_annonce_index' : 'front_annonce_index');
        }

        // Afficher le formulaire de modification dans le template
        return $this->render($this->isGranted('ROLE_ADMIN') ? 'back_office/annonce/edit.html.twig' : 'front_office/annonce/edit.html.twig', [
            'form' => $form->createView(),
            'annonce' => $annonce,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Annonce $annonce, EntityManagerInterface $entityManager): Response
    {
        // Vérifier le token CSRF pour sécuriser la suppression
        if ($this->isCsrfTokenValid('delete' . $annonce->getId(), $request->request->get('_token'))) {
            // Supprimer la photo associée si elle existe
            if ($annonce->getPhoto()) {
                $photoPath = $this->getParameter('photos_directory') . '/' . $annonce->getPhoto();
                if (file_exists($photoPath)) {
                    unlink($photoPath);
                }
            }

            // Supprimer l'annonce de la base de données
            $entityManager->remove($annonce);
            $entityManager->flush();

            // Ajouter un message flash pour informer l'utilisateur
            $this->addFlash('success', 'L\'annonce a été supprimée avec succès.');
        }

        // Rediriger vers la liste des annonces
        return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'admin_annonce_index' : 'front_annonce_index');
    }
}