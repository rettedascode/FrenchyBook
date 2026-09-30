<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Repository\LoanRequestRepository;
use App\Repository\PushSubscriptionRepository;
use App\Repository\WaitlistEntryRepository;
use App\Service\PushNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * „Meine Übersicht“ – Startseite nach dem Login.
 */
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        LoanRequestRepository $requests,
        LoanRepository $loans,
        BookRepository $books,
        PushSubscriptionRepository $pushSubscriptions,
        PushNotifier $push,
        WaitlistEntryRepository $waitlist,
    ): Response {
        $incoming = $requests->findOpenForOwner($user);
        $myBookCount = $books->countByOwner($user);

        // „Deine ersten Schritte“ – was schon erledigt ist, erkennt der Server selbst.
        // Ob die App installiert ist, weiß nur das Gerät (prüft das Skript im Template).
        $onboarding = [
            'book' => $myBookCount > 0,
            'borrow' => $requests->count(['requester' => $user]) > 0 || $loans->count(['borrower' => $user]) > 0,
        ];
        if ($push->isEnabled()) {
            $onboarding['push'] = $pushSubscriptions->count(['user' => $user]) > 0;
        }

        return $this->render('dashboard/index.html.twig', [
            'incomingRequests' => $incoming,
            'lentOut' => $loans->findActiveLentBy($user),
            'borrowed' => $loans->findActiveBorrowedBy($user),
            'myRequests' => $requests->findOpenByRequester($user),
            'waitlist' => $waitlist->findByUser($user),
            'myBookCount' => $myBookCount,
            'onboarding' => $onboarding,
            // Für „Annehmen“ muss das Buch verfügbar sein
            'activeLoans' => $loans->findActiveByBooks(array_map(static fn ($r) => $r->getBook(), $incoming)),
        ]);
    }
}
