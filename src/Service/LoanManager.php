<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\LoanRequest;
use App\Entity\User;
use App\Entity\WaitlistEntry;
use App\Exception\LoanException;
use App\Repository\LoanRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Alle Regeln rund ums Verleihen an einer Stelle:
 * - Ein Buch hat höchstens eine aktive Ausleihe.
 * - Man kann sich sein eigenes Buch nicht leihen.
 * - Anfragen gehen nur für verfügbare Bücher, pro Person höchstens eine offene.
 * - Ist ein Buch verliehen, kann man sich auf die Warteliste setzen; nach der Rückgabe
 *   wird der/die Erste auf der Liste benachrichtigt.
 * - Wer ein Buch hat, kann selbst verlängern (2 Wochen, max. 2×, nicht wenn jemand wartet).
 *
 * Fachliche Fehler werden als LoanException mit deutscher, freundlicher Meldung geworfen.
 */
class LoanManager
{
    /** Standard-Leihdauer, wenn eine Anfrage angenommen wird. */
    public const DEFAULT_LOAN_DAYS = 28;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoanRepository $loans,
        private readonly LoanNotifier $notifier,
    ) {
    }

    /**
     * @param bool $handedOver true = Buch wird gerade persönlich übergeben (direkt verleihen),
     *                         false = reserviert, die Übergabe bestätigt später jemand (angenommene Anfrage)
     */
    public function lend(Book $book, User $borrower, ?\DateTimeImmutable $dueAt = null, ?string $note = null, bool $handedOver = true): Loan
    {
        if ($book->isOwnedBy($borrower)) {
            throw new LoanException('loan.error.own_book');
        }

        $loan = $this->em->wrapInTransaction(function () use ($book, $borrower, $dueAt, $note, $handedOver): Loan {
            // Direkt in der Datenbank prüfen, nicht nur in der geladenen Collection
            if (null !== $this->loans->findActiveForBook($book)) {
                throw new LoanException('loan.error.already_lent', ['title' => $book->getTitle()]);
            }

            $loan = (new Loan($book))
                ->setBorrower($borrower)
                ->setDueAt($dueAt?->setTime(0, 0))
                ->setNote($note);
            if ($handedOver) {
                $loan->markHandedOver();
            }
            $this->em->persist($loan);

            // Hatte die Person das Buch angefragt, ist die Anfrage damit erledigt.
            if (null !== ($request = $book->getOpenRequestBy($borrower))) {
                $request->accept();
            }
            // Wer auf der Warteliste stand und das Buch jetzt bekommt, ist dort fertig.
            $this->removeFromWaitlist($book, $borrower);

            return $loan;
        });

        return $loan;
    }

    public function markReturned(Loan $loan): void
    {
        if (!$loan->isActive()) {
            throw new LoanException('loan.error.already_returned');
        }
        $loan->markReturned();
        $this->em->flush();

        // Wer als Nächstes auf der Warteliste steht, erfährt: Das Buch ist wieder da.
        $next = $loan->getBook()->getWaitlist()->first();
        if ($next instanceof WaitlistEntry) {
            $next->markNotified();
            $this->em->flush();
            $this->notifier->waitlistAvailable($next);
        }
    }

    public function changeDueDate(Loan $loan, ?\DateTimeImmutable $dueAt): void
    {
        if (!$loan->isActive()) {
            throw new LoanException('loan.error.finished');
        }
        $loan->setDueAt($dueAt?->setTime(0, 0));
        $loan->setReminderSentAt(null);
        $this->em->flush();
    }

    public function request(Book $book, User $requester, ?string $message = null): LoanRequest
    {
        if ($book->isOwnedBy($requester)) {
            throw new LoanException('request.error.own_book');
        }
        if (!$book->isAvailable()) {
            throw new LoanException('request.error.not_available', ['title' => $book->getTitle()]);
        }
        if (null !== $book->getOpenRequestBy($requester)) {
            throw new LoanException('request.error.duplicate');
        }

        $request = new LoanRequest($book, $requester, null !== $message ? mb_substr($message, 0, 300) : null);
        $this->em->persist($request);
        $this->removeFromWaitlist($book, $requester);
        $this->em->flush();

        $this->notifier->requestCreated($request);

        return $request;
    }

    public function accept(LoanRequest $request): Loan
    {
        if (!$request->isOpen()) {
            throw new LoanException('request.error.answered');
        }

        $dueAt = new \DateTimeImmutable(sprintf('today +%d days', self::DEFAULT_LOAN_DAYS));
        // Angenommen heißt: reserviert – die Leihfrist beginnt erst mit der Übergabe
        $loan = $this->lend($request->getBook(), $request->getRequester(), $dueAt, null, false);
        $request->accept();
        $this->em->flush();

        $this->notifier->requestAccepted($request, $loan);

        return $loan;
    }

    public function decline(LoanRequest $request): void
    {
        if (!$request->isOpen()) {
            throw new LoanException('request.error.answered');
        }
        $request->decline();
        $this->em->flush();

        $this->notifier->requestDeclined($request);
    }

    public function cancel(LoanRequest $request): void
    {
        if (!$request->isOpen()) {
            throw new LoanException('request.error.answered');
        }
        $request->getBook()->getLoanRequests()->removeElement($request);
        $this->em->remove($request);
        $this->em->flush();
    }

    /** Eigentümer oder ausleihende Person bestätigt: Das Buch ist übergeben. */
    public function confirmHandover(Loan $loan, User $user): void
    {
        $isBorrower = $loan->getBorrower()?->getId() === $user->getId();
        if (!$isBorrower && !$loan->getBook()->isOwnedBy($user)) {
            throw new LoanException('loan.error.not_involved');
        }
        if (!$loan->isActive()) {
            throw new LoanException('loan.error.finished');
        }
        if ($loan->isHandedOver()) {
            throw new LoanException('loan.error.already_handed_over');
        }

        $loan->confirmHandover();
        $this->em->flush();
    }

    /** Die ausleihende Person verlängert selbst um 2 Wochen. */
    public function extend(Loan $loan, User $user): void
    {
        if ($loan->getBorrower()?->getId() !== $user->getId()) {
            throw new LoanException('loan.error.not_borrower');
        }
        if (!$loan->isActive()) {
            throw new LoanException('loan.error.finished');
        }
        if (!$loan->isHandedOver()) {
            throw new LoanException('loan.error.not_handed_over');
        }
        if (!$loan->canBeExtended()) {
            throw new LoanException('loan.error.max_extensions', ['max' => Loan::MAX_EXTENSIONS]);
        }
        if (!$loan->getBook()->getWaitlist()->isEmpty()) {
            throw new LoanException('loan.error.waitlist');
        }

        $loan->extend();
        $this->em->flush();
        $this->notifier->loanExtended($loan);
    }

    public function joinWaitlist(Book $book, User $user): WaitlistEntry
    {
        if ($book->isOwnedBy($user)) {
            throw new LoanException('waitlist.error.own_book');
        }
        if ($book->isAvailable()) {
            throw new LoanException('waitlist.error.available');
        }
        if ($book->getActiveLoan()?->getBorrower()?->getId() === $user->getId()) {
            throw new LoanException('waitlist.error.has_it');
        }
        if (null !== ($existing = $book->getWaitlistEntryOf($user))) {
            return $existing;
        }

        $entry = new WaitlistEntry($book, $user);
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    public function leaveWaitlist(Book $book, User $user): void
    {
        $this->removeFromWaitlist($book, $user);
        $this->em->flush();
    }

    private function removeFromWaitlist(Book $book, User $user): void
    {
        if (null !== ($entry = $book->getWaitlistEntryOf($user))) {
            $book->getWaitlist()->removeElement($entry);
            $this->em->remove($entry);
        }
    }
}
