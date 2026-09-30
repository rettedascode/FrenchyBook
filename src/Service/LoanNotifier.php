<?php

namespace App\Service;

use App\Entity\Loan;
use App\Entity\LoanRequest;
use App\Entity\WaitlistEntry;
use App\Util\LocalizedDate;

/**
 * Benachrichtigungen rund ums Ausleihen – per E-Mail und (falls aktiviert) als Push aufs Handy,
 * immer in der Sprache der Person, die sie bekommt. Schlägt etwas fehl, wird das nur geloggt.
 */
class LoanNotifier
{
    public function __construct(
        private readonly UserMailer $mailer,
        private readonly PushNotifier $push,
    ) {
    }

    public function requestCreated(LoanRequest $request): void
    {
        $book = $request->getBook();
        $params = ['name' => $request->getRequester()->getName(), 'title' => $book->getTitle()];

        $this->mailer->send($book->getOwner(), 'email.request_created.subject', $params,
            'email/request_created.html.twig', ['request' => $request, 'book' => $book]);
        $this->push->notify($book->getOwner(), 'push.request_created.title', [], 'push.request_created.body', $params,
            'app_dashboard', [], 'request-'.$request->getId());
    }

    public function requestAccepted(LoanRequest $request, Loan $loan): void
    {
        $book = $request->getBook();
        $params = ['name' => $book->getOwner()->getName(), 'title' => $book->getTitle()];

        $this->mailer->send($request->getRequester(), 'email.request_accepted.subject', $params,
            'email/request_accepted.html.twig', ['request' => $request, 'book' => $book, 'loan' => $loan]);
        $this->push->notify($request->getRequester(), 'push.request_accepted.title', [], 'push.request_accepted.body', $params,
            'app_book_show', ['id' => $book->getId()], 'request-'.$request->getId());
    }

    public function requestDeclined(LoanRequest $request): void
    {
        $book = $request->getBook();

        $this->mailer->send($request->getRequester(), 'email.request_declined.subject', ['title' => $book->getTitle()],
            'email/request_declined.html.twig', ['request' => $request, 'book' => $book]);
        $this->push->notify($request->getRequester(), 'push.request_declined.title', [], 'push.request_declined.body',
            ['name' => $book->getOwner()->getName(), 'title' => $book->getTitle()],
            'app_book_index', ['status' => 'available'], 'request-'.$request->getId());
    }

    /** Der Besitzer hat das Buch direkt eingetragen (ohne vorherige Anfrage) */
    public function loanCreated(Loan $loan): void
    {
        $book = $loan->getBook();
        $this->mailer->send($loan->getBorrower(), 'email.loan_created.subject', ['title' => $book->getTitle()],
            'email/loan_created.html.twig', ['loan' => $loan, 'book' => $book]);
        $this->push->notify($loan->getBorrower(), 'push.loan_created.title', [], 'push.loan_created.body',
            ['name' => $book->getOwner()->getName(), 'title' => $book->getTitle()],
            'app_book_show', ['id' => $book->getId()], 'loan-'.$loan->getId());
    }

    /** @return bool true, wenn die Erinnerung per Mail oder Push angekommen ist */
    public function overdueReminder(Loan $loan): bool
    {
        $book = $loan->getBook();
        $borrower = $loan->getBorrower();

        $mailed = $this->mailer->send($borrower, 'email.reminder.subject', ['title' => $book->getTitle()],
            'email/overdue_reminder.html.twig', ['loan' => $loan, 'book' => $book]);
        $pushed = $this->push->notify($borrower, 'push.reminder.title', [], 'push.reminder.body', [
            'title' => $book->getTitle(),
            'name' => $book->getOwner()->getName(),
            'date' => LocalizedDate::format($loan->getDueAt(), $borrower->getLocale()),
        ], 'app_book_show', ['id' => $book->getId()], 'reminder-'.$loan->getId());

        return $mailed || $pushed > 0;
    }

    /** Erinnerung kurz VOR dem Rückgabetermin (einmal pro Ausleihe) */
    public function dueSoonReminder(Loan $loan): bool
    {
        $book = $loan->getBook();
        $borrower = $loan->getBorrower();
        $params = [
            'title' => $book->getTitle(),
            'name' => $book->getOwner()->getName(),
            'date' => LocalizedDate::format($loan->getDueAt(), $borrower->getLocale()),
        ];

        $mailed = $this->mailer->send($borrower, 'email.due_soon.subject', ['title' => $book->getTitle()],
            'email/due_soon.html.twig', ['loan' => $loan, 'book' => $book]);
        $pushed = $this->push->notify($borrower, 'push.due_soon.title', [], 'push.due_soon.body', $params,
            'app_book_show', ['id' => $book->getId()], 'due-soon-'.$loan->getId());

        return $mailed || $pushed > 0;
    }

    /** Das Buch ist zurück – die nächste Person auf der Warteliste bekommt Bescheid. */
    public function waitlistAvailable(WaitlistEntry $entry): void
    {
        $book = $entry->getBook();
        $params = ['title' => $book->getTitle(), 'name' => $book->getOwner()->getName()];

        $this->mailer->send($entry->getUser(), 'email.waitlist_available.subject', $params,
            'email/waitlist_available.html.twig', ['book' => $book]);
        $this->push->notify($entry->getUser(), 'push.waitlist_available.title', [], 'push.waitlist_available.body', $params,
            'app_book_show', ['id' => $book->getId()], 'waitlist-'.$book->getId());
    }

    /** Die ausleihende Person hat verlängert – der Eigentümer bekommt einen Push. */
    public function loanExtended(Loan $loan): void
    {
        $book = $loan->getBook();
        $owner = $book->getOwner();
        $this->push->notify($owner, 'push.loan_extended.title', [], 'push.loan_extended.body', [
            'title' => $book->getTitle(),
            'name' => $loan->getBorrower()->getName(),
            'date' => LocalizedDate::format($loan->getDueAt(), $owner->getLocale()),
        ], 'app_book_show', ['id' => $book->getId()], 'loan-'.$loan->getId());
    }
}
