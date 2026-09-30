<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\NewPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use App\Service\UserMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Translation\TranslatableMessage;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * „Passwort vergessen“ in drei Schritten:
 * 1. E-Mail-Adresse eingeben → Mail mit Link (1 Stunde gültig)
 * 2. Bestätigungsseite – verrät nie, ob es zu der Adresse ein Konto gibt
 * 3. Link öffnen → neues Passwort setzen → direkt angemeldet
 */
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $em,
        private readonly UserMailer $mailer,
    ) {
    }

    #[Route('/passwort-vergessen', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(Request $request, UserRepository $users): Response
    {
        if ($this->getUser()) {
            // Angemeldet? Dann einfach im Profil ändern.
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->sendResetEmail((string) $form->get('email')->getData(), $users);
        }

        return $this->render('reset_password/request.html.twig', ['form' => $form]);
    }

    #[Route('/passwort-vergessen/gesendet', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        // Nur nach einer Anfrage erreichbar – egal ob es die Adresse gibt (verrät nichts)
        if (!$this->canCheckEmail()) {
            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('reset_password/check_email.html.twig', [
            'lifetimeMinutes' => intdiv($this->resetPasswordHelper->getTokenLifetime(), 60),
        ]);
    }

    #[Route('/passwort-neu/{token}', name: 'app_reset_password', defaults: ['token' => null], methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        ?string $token = null,
    ): Response {
        if (null !== $token) {
            // Token aus der URL in die Session verschieben, damit er nicht im Verlauf/Referer landet
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            return $this->redirectToRoute('app_forgot_password');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->addFlash('error', new TranslatableMessage('reset.error.invalid_link'));

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(NewPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Link ist nur einmal verwendbar
            $this->resetPasswordHelper->removeResetRequest($token);

            $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $this->em->flush();
            $this->cleanSessionAfterReset();

            $this->addFlash('success', new TranslatableMessage('reset.success'));
            $security->login($user, 'form_login', 'main', [(new RememberMeBadge())->enable()]);

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('reset_password/reset.html.twig', ['form' => $form, 'user' => $user]);
    }

    private function sendResetEmail(string $email, UserRepository $users): RedirectResponse
    {
        $user = $users->findOneBy(['email' => mb_strtolower(trim($email))]);

        // Egal ob es das Konto gibt oder zu viele Anfragen kamen: immer dieselbe Bestätigungsseite
        $this->setCanCheckEmailInSession();
        if (null === $user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return $this->redirectToRoute('app_check_email');
        }

        $this->mailer->send($user, 'email.reset.subject', [], 'email/reset_password.html.twig', [
            'resetUrl' => $this->generateUrl('app_reset_password', ['token' => $resetToken->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            'lifetimeMinutes' => intdiv($this->resetPasswordHelper->getTokenLifetime(), 60),
        ]);
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
