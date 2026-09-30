<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Exception\LoanException;
use App\Security\Voter\BookVoter;
use App\Service\LoanManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Warteliste für ausgeliehene Bücher: eintragen und wieder austragen.
 */
class WaitlistController extends AbstractController
{
    public function __construct(private readonly LoanManager $loanManager)
    {
    }

    #[Route('/buecher/{id}/warteliste', name: 'app_waitlist_join', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function join(Request $request, Book $book, #[CurrentUser] User $user): Response
    {
        if ($this->isCsrfTokenValid('waitlist-'.$book->getId(), $request->getPayload()->getString('_token'))) {
            $this->denyAccessUnlessGranted(BookVoter::VIEW, $book);
            try {
                $this->loanManager->joinWaitlist($book, $user);
                $this->addFlash('success', new TranslatableMessage('flash.waitlist.joined', [
                    'title' => $book->getTitle(),
                    'position' => $book->getWaitlistPosition($user),
                ]));
            } catch (LoanException $e) {
                $this->addFlash('info', $e->toMessage());
            }
        } else {
            $this->addFlash('error', new TranslatableMessage('flash.csrf_failed'));
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/buecher/{id}/warteliste/verlassen', name: 'app_waitlist_leave', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function leave(Request $request, Book $book, #[CurrentUser] User $user): Response
    {
        if ($this->isCsrfTokenValid('waitlist-'.$book->getId(), $request->getPayload()->getString('_token'))) {
            $this->loanManager->leaveWaitlist($book, $user);
            $this->addFlash('success', new TranslatableMessage('flash.waitlist.left', ['title' => $book->getTitle()]));
        } else {
            $this->addFlash('error', new TranslatableMessage('flash.csrf_failed'));
        }

        return 'dashboard' === $request->getPayload()->getString('from')
            ? $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
    }
}
