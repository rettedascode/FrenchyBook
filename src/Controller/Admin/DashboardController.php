<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Repository\LoanRequestRepository;
use App\Repository\UserRepository;
use App\Service\BackupStatus;
use App\Service\ErrorLog;
use App\Service\InviteCodeManager;
use App\Service\PushNotifier;
use App\Service\UserMailer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/admin')]
class DashboardController extends AbstractAdminController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        UserRepository $users,
        BookRepository $books,
        LoanRepository $loans,
        LoanRequestRepository $requests,
        InviteCodeManager $inviteCodes,
        ErrorLog $errorLog,
        PushNotifier $push,
        BackupStatus $backup,
        #[Autowire('%env(MAILER_DSN)%')] string $mailerDsn,
    ): Response {
        $overdue = $loans->findOverdue(new \DateTimeImmutable('today'));

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users' => $users->count([]),
                'books' => $books->count([]),
                'loans' => $loans->countActive(),
                'overdue' => \count($overdue),
                'requests' => $requests->countOpen(),
            ],
            'checks' => [
                'mail' => '' !== $mailerDsn && !str_starts_with($mailerDsn, 'null://'),
                'push' => $push->isEnabled(),
                'errors' => $errorLog->countSince(new \DateTimeImmutable('-7 days')),
                'backup' => $backup->get(),
            ],
            'inviteCode' => $inviteCodes->current(),
            'newestUsers' => $users->findBy([], ['createdAt' => 'DESC'], 5),
            'newestBooks' => $books->findBy([], ['createdAt' => 'DESC'], 5),
        ]);
    }

    #[Route('/einladungscode', name: 'admin_invite_code', methods: ['POST'])]
    public function inviteCode(Request $request, InviteCodeManager $inviteCodes): Response
    {
        if ($this->csrfOk($request, 'admin-invite-code')) {
            $action = $request->getPayload()->getString('action');
            try {
                match ($action) {
                    'new' => $inviteCodes->regenerate(),
                    'off' => $inviteCodes->disable(),
                    default => $inviteCodes->set($request->getPayload()->getString('code')),
                };
                '' === $inviteCodes->current()
                    ? $this->flash('warning', 'admin.invite.flash_off')
                    : $this->flash('success', 'admin.invite.flash_saved', ['code' => $inviteCodes->current()]);
            } catch (\InvalidArgumentException) {
                $this->flash('error', 'admin.invite.invalid');
            }
        }

        return $this->redirectToRoute('admin_dashboard', ['_fragment' => 'einladung'], Response::HTTP_SEE_OTHER);
    }

    #[Route('/test-mail', name: 'admin_test_mail', methods: ['POST'])]
    public function testMail(Request $request, #[CurrentUser] User $admin, UserMailer $mailer): Response
    {
        if ($this->csrfOk($request, 'admin-test-mail')) {
            $mailer->send($admin, 'admin.test_mail.subject', [], 'email/admin_test.html.twig', ['sentAt' => new \DateTimeImmutable()])
                ? $this->flash('success', 'admin.test_mail.sent', ['email' => $admin->getEmail()])
                : $this->flash('error', 'admin.test_mail.failed');
        }

        return $this->redirectToRoute('admin_dashboard', [], Response::HTTP_SEE_OTHER);
    }
}
