<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AdminUserType;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Repository\PushSubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\CoverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/admin/mitglieder')]
class UserController extends AbstractAdminController
{
    /** Ohne leicht verwechselbare Zeichen – zum Vorlesen oder Abtippen */
    private const PASSWORD_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const TEMP_PASSWORD_SESSION_KEY = 'admin_temp_password';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'admin_users', methods: ['GET'])]
    public function index(UserRepository $users, BookRepository $books, LoanRepository $loans): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['name' => 'ASC']),
            'bookCounts' => $books->countByAllOwners(),
            'borrowedCounts' => $loans->countActiveByBorrower(),
        ]);
    }

    #[Route('/{id}', name: 'admin_user_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        User $user,
        BookRepository $books,
        LoanRepository $loans,
        PushSubscriptionRepository $pushSubscriptions,
    ): Response {
        $form = $this->createForm(AdminUserType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $this->em->flush();
                $this->flash('success', 'admin.users.flash_saved', ['name' => $user->getName()]);

                return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            }
            // Ungültige Änderungen nicht stehen lassen (z. B. wenn man sich selbst bearbeitet)
            $this->em->refresh($user);
        }

        // Frisch erzeugtes Passwort nur ein einziges Mal anzeigen
        $session = $request->getSession();
        $temp = $session->get(self::TEMP_PASSWORD_SESSION_KEY);
        $tempPassword = null;
        if (\is_array($temp) && ($temp['user'] ?? null) === $user->getId()) {
            $tempPassword = (string) $temp['password'];
            $session->remove(self::TEMP_PASSWORD_SESSION_KEY);
        }

        return $this->render('admin/user_show.html.twig', [
            'user' => $user,
            'form' => $form,
            'bookCount' => $books->countByOwner($user),
            'lentCount' => $loans->countActiveLentBy($user),
            'borrowedCount' => $loans->countActiveBorrowedBy($user),
            'devices' => $pushSubscriptions->findByUser($user),
            'tempPassword' => $tempPassword,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/admin', name: 'admin_user_toggle_admin', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function toggleAdmin(Request $request, User $user, #[CurrentUser] User $me): Response
    {
        if ($this->csrfOk($request, 'admin-toggle-'.$user->getId())) {
            if ($user === $me) {
                // Sonst könnte man sich aus Versehen selbst aussperren
                $this->flash('error', 'admin.users.not_yourself');
            } else {
                $user->setAdmin(!$user->isAdmin());
                $this->em->flush();
                $this->flash('success', $user->isAdmin() ? 'admin.users.flash_admin_on' : 'admin.users.flash_admin_off', ['name' => $user->getName()]);
            }
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    /**
     * Für Freunde, die ihr Passwort vergessen haben und mit der E-Mail nicht klarkommen:
     * neues Passwort erzeugen, einmal anzeigen, persönlich weitergeben.
     */
    #[Route('/{id}/passwort', name: 'admin_user_password', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function resetPassword(Request $request, User $user, UserPasswordHasherInterface $hasher): Response
    {
        if ($this->csrfOk($request, 'admin-password-'.$user->getId())) {
            $password = self::randomPassword();
            // Neuer Hash → „Angemeldet bleiben“ auf den anderen Geräten wird ungültig
            $user->setPassword($hasher->hashPassword($user, $password));
            $this->em->flush();
            $request->getSession()->set(self::TEMP_PASSWORD_SESSION_KEY, ['user' => $user->getId(), 'password' => $password]);
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId(), '_fragment' => 'passwort'], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/loeschen', name: 'admin_user_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(Request $request, User $user, #[CurrentUser] User $me, BookRepository $books, CoverManager $covers): Response
    {
        if (!$this->csrfOk($request, 'admin-delete-user-'.$user->getId())) {
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }
        if ($user === $me) {
            $this->flash('error', 'admin.users.not_yourself');

            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $name = $user->getName();
        $coverFiles = [];
        foreach ($books->findBy(['owner' => $user]) as $b) {
            array_push($coverFiles, $b->getCoverImage(), $b->getBackImage());
        }
        $coverFiles = array_filter($coverFiles);

        // Bücher, Ausleihen, Anfragen und Push-Abos hängen per ON DELETE CASCADE am Mitglied
        $this->em->remove($user);
        $this->em->flush();
        foreach ($coverFiles as $file) {
            $covers->remove($file);
        }
        // Autoren ohne Bücher aufräumen
        $this->em->getConnection()->executeStatement('DELETE FROM author WHERE id NOT IN (SELECT author_id FROM book_author)');

        $this->flash('success', 'admin.users.flash_deleted', ['name' => $name]);

        return $this->redirectToRoute('admin_users', [], Response::HTTP_SEE_OTHER);
    }

    private static function randomPassword(): string
    {
        $groups = [];
        for ($g = 0; $g < 3; ++$g) {
            $part = '';
            for ($i = 0; $i < 4; ++$i) {
                $part .= self::PASSWORD_ALPHABET[random_int(0, \strlen(self::PASSWORD_ALPHABET) - 1)];
            }
            $groups[] = $part;
        }

        return implode('-', $groups);
    }
}
