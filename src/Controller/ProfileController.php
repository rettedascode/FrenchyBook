<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfileType;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Service\InviteCodeManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Translation\TranslatableMessage;

class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        BookRepository $books,
        LoanRepository $loans,
        InviteCodeManager $inviteCodes,
    ): Response {
        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted()) {
            if ($profileForm->isValid()) {
                $em->flush();
                $this->addFlash('success', new TranslatableMessage('flash.profile.saved'));

                return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
            }
            // Ungültige Änderungen nicht im eingeloggten User stehen lassen
            $em->refresh($user);
        }

        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $passwordForm->get('newPassword')->getData()));
            $em->flush();
            $this->addFlash('success', new TranslatableMessage('flash.profile.password_changed'));

            return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('profile/index.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'bookCount' => $books->countByOwner($user),
            'lentCount' => $loans->countActiveLentBy($user),
            'borrowedCount' => $loans->countActiveBorrowedBy($user),
            'inviteCode' => $inviteCodes->current(),
        ]);
    }
}
