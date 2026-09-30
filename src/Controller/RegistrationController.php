<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\InviteCodeManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Translation\TranslatableMessage;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly InviteCodeManager $inviteCodes,
    ) {
    }

    #[Route('/registrieren', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'invite_code' => $this->inviteCodes->current(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            // Wer sich auf Französisch registriert, bekommt App und E-Mails auf Französisch
            $user->setLocale($request->getLocale());
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', new TranslatableMessage('flash.welcome', ['name' => $user->getName()]));

            // Direkt eingeloggt – inkl. „Angemeldet bleiben“
            $security->login($user, 'form_login', 'main', [(new RememberMeBadge())->enable()]);

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }
}
