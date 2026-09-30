<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\LoanRequest;
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
use App\Util\LocalizedDate;

/**
 * Ausleih-Anfragen: anfragen (andere), annehmen/ablehnen (Besitzer), zurückziehen (Anfragende).
 */
class LoanRequestController extends AbstractController
{
    public function __construct(private readonly LoanManager $loanManager)
    {
    }

    #[Route('/buecher/{id}/anfragen', name: 'app_request_create', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function create(Request $request, Book $book, #[CurrentUser] User $user): Response
    {
        $this->checkToken($request, 'request-book-'.$book->getId());

        try {
            $message = trim($request->getPayload()->getString('message'));
            $this->loanManager->request($book, $user, '' === $message ? null : $message);
            $this->addFlash('success', new TranslatableMessage('flash.request.sent', ['name' => $book->getOwner()->getName()]));
        } catch (LoanException $e) {
            $this->addFlash('info', $e->toMessage());
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/anfragen/{id}/annehmen', name: 'app_request_accept', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function accept(Request $request, LoanRequest $loanRequest): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::RESPOND, $loanRequest->getBook(), 'Nur der Besitzer kann Anfragen beantworten.');
        $this->checkToken($request, 'respond-request-'.$loanRequest->getId());

        try {
            $loan = $this->loanManager->accept($loanRequest);
            $this->addFlash('success', new TranslatableMessage('flash.request.accepted', [
                'name' => $loanRequest->getRequester()->getName(),
                'date' => LocalizedDate::format($loan->getDueAt(), $request->getLocale()),
            ]));
        } catch (LoanException $e) {
            $this->addFlash('info', $e->toMessage());
        }

        return $this->redirectBack($request, $loanRequest);
    }

    #[Route('/anfragen/{id}/ablehnen', name: 'app_request_decline', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function decline(Request $request, LoanRequest $loanRequest): Response
    {
        $this->denyAccessUnlessGranted(BookVoter::RESPOND, $loanRequest->getBook(), 'Nur der Besitzer kann Anfragen beantworten.');
        $this->checkToken($request, 'respond-request-'.$loanRequest->getId());

        try {
            $this->loanManager->decline($loanRequest);
            $this->addFlash('success', new TranslatableMessage('flash.request.declined', ['name' => $loanRequest->getRequester()->getName()]));
        } catch (LoanException $e) {
            $this->addFlash('info', $e->toMessage());
        }

        return $this->redirectBack($request, $loanRequest);
    }

    #[Route('/anfragen/{id}/zurueckziehen', name: 'app_request_cancel', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function cancel(Request $request, LoanRequest $loanRequest, #[CurrentUser] User $user): Response
    {
        if ($loanRequest->getRequester()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Nur die anfragende Person kann die Anfrage zurückziehen.');
        }
        $this->checkToken($request, 'cancel-request-'.$loanRequest->getId());
        $book = $loanRequest->getBook();

        try {
            $this->loanManager->cancel($loanRequest);
            $this->addFlash('success', new TranslatableMessage('flash.request.cancelled'));
        } catch (LoanException $e) {
            $this->addFlash('info', $e->toMessage());
        }

        if ('dashboard' === $request->getPayload()->getString('from')) {
            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
    }

    private function redirectBack(Request $request, LoanRequest $loanRequest): Response
    {
        if ('dashboard' === $request->getPayload()->getString('from')) {
            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_book_show', ['id' => $loanRequest->getBook()->getId()], Response::HTTP_SEE_OTHER);
    }
}
