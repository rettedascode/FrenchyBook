<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use App\Exception\LoanException;
use App\Form\LendType;
use App\Repository\UserRepository;
use App\Security\Voter\BookVoter;
use App\Service\LoanManager;
use App\Service\LoanNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use App\Util\LocalizedDate;

/**
 * Verleihen und Zurückgeben – nur für den Besitzer des Buchs.
 * Verlängern – nur für die Person, die das Buch gerade hat.
 */
class LoanController extends AbstractController
{
    public function __construct(
        private readonly LoanManager $loanManager,
        private readonly LoanNotifier $notifier,
    ) {
    }

    #[Route('/buecher/{id}/verleihen', name: 'app_loan_create', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(BookVoter::LEND, subject: 'book', message: 'Nur der Besitzer kann dieses Buch verleihen.')]
    public function create(Request $request, Book $book, #[CurrentUser] User $user, UserRepository $users): Response
    {
        $form = $this->createForm(LendType::class, null, ['friends' => $users->findAllExcept($user)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $loan = $this->loanManager->lend($book, $data['borrower'], $data['dueAt'], $data['note']);
                $this->notifier->loanCreated($loan);
                $this->addFlash('success', new TranslatableMessage('flash.loan.created', ['title' => $book->getTitle(), 'name' => $loan->getBorrower()->getName()]));
            } catch (LoanException $e) {
                $this->addFlash('error', $e->toMessage());
            }

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
        }

        // Fehler im Formular (z. B. niemand ausgewählt): Detailseite mit geöffnetem Formular zeigen
        return $this->forward(BookController::class.'::show', [
            'id' => $book->getId(),
            'lendForm' => $form,
        ]);
    }

    #[Route('/ausleihen/{id}/zurueckgegeben', name: 'app_loan_return', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function markReturned(Request $request, Loan $loan): Response
    {
        $book = $loan->getBook();
        $this->denyAccessUnlessGranted(BookVoter::RESPOND, $book, 'Nur der Besitzer kann die Rückgabe bestätigen.');
        $this->checkToken($request, 'return-loan-'.$loan->getId());

        try {
            $this->loanManager->markReturned($loan);
            $this->addFlash('success', new TranslatableMessage('flash.loan.returned', ['title' => $book->getTitle()]));
        } catch (LoanException $e) {
            $this->addFlash('info', $e->toMessage());
        }

        return $this->redirectBack($request, $book);
    }

    #[Route('/ausleihen/{id}/rueckgabedatum', name: 'app_loan_due_date', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function changeDueDate(Request $request, Loan $loan): Response
    {
        $book = $loan->getBook();
        $this->denyAccessUnlessGranted(BookVoter::RESPOND, $book);
        $this->checkToken($request, 'due-date-'.$loan->getId());

        $raw = $request->getPayload()->getString('dueAt');
        $dueAt = '' === $raw ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (false === $dueAt || (null !== $dueAt && $dueAt < new \DateTimeImmutable('today'))) {
            $this->addFlash('error', new TranslatableMessage('flash.loan.due_date_past'));

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
        }

        try {
            $this->loanManager->changeDueDate($loan, $dueAt);
            $this->addFlash('success', null === $dueAt
                ? new TranslatableMessage('flash.loan.due_date_removed')
                : new TranslatableMessage('flash.loan.due_date_changed', ['date' => LocalizedDate::format($dueAt, $request->getLocale())]));
        } catch (LoanException $e) {
            $this->addFlash('info', $e->toMessage());
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/ausleihen/{id}/uebergeben', name: 'app_loan_handover', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function handover(Request $request, Loan $loan, #[CurrentUser] User $user): Response
    {
        $this->checkToken($request, 'handover-loan-'.$loan->getId());

        try {
            $this->loanManager->confirmHandover($loan, $user);
            $this->addFlash('success', new TranslatableMessage('flash.loan.handed_over', [
                'title' => $loan->getBook()->getTitle(),
                'date' => null !== $loan->getDueAt() ? LocalizedDate::format($loan->getDueAt(), $request->getLocale()) : '',
            ]));
        } catch (LoanException $e) {
            $this->addFlash('info', $e->toMessage());
        }

        return $this->redirectBack($request, $loan->getBook());
    }

    #[Route('/ausleihen/{id}/verlaengern', name: 'app_loan_extend', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function extend(Request $request, Loan $loan, #[CurrentUser] User $user): Response
    {
        $this->checkToken($request, 'extend-loan-'.$loan->getId());

        try {
            $this->loanManager->extend($loan, $user);
            $this->addFlash('success', new TranslatableMessage('flash.loan.extended', [
                'title' => $loan->getBook()->getTitle(),
                'date' => LocalizedDate::format($loan->getDueAt(), $request->getLocale()),
            ]));
        } catch (LoanException $e) {
            $this->addFlash('info', $e->toMessage());
        }

        return $this->redirectBack($request, $loan->getBook());
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
    }

    /** Zurück zur Seite, von der die Aktion kam (Dashboard oder Buch). */
    private function redirectBack(Request $request, Book $book): Response
    {
        if ('dashboard' === $request->getPayload()->getString('from')) {
            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
    }
}
