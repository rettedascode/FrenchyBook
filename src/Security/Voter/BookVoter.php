<?php

namespace App\Security\Voter;

use App\Entity\Book;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Wer darf was mit einem Buch?
 * - Besitzer: bearbeiten, löschen, verleihen, Rückgabe bestätigen, private Notiz sehen, Anfragen beantworten
 * - Admins: zusätzlich bearbeiten und löschen (um Fehler zu korrigieren) – nicht die private Notiz
 * - Alle anderen eingeloggten Nutzer: ansehen und (wenn verfügbar) anfragen
 *
 * @extends Voter<string, Book>
 */
final class BookVoter extends Voter
{
    public const VIEW = 'BOOK_VIEW';
    public const EDIT = 'BOOK_EDIT';
    public const DELETE = 'BOOK_DELETE';
    public const LEND = 'BOOK_LEND';
    public const RETURN = 'BOOK_RETURN';
    public const VIEW_NOTE = 'BOOK_VIEW_NOTE';
    public const RESPOND = 'BOOK_RESPOND';
    public const REQUEST = 'BOOK_REQUEST';
    public const WAITLIST = 'BOOK_WAITLIST';

    private const ATTRIBUTES = [
        self::VIEW, self::EDIT, self::DELETE, self::LEND, self::RETURN,
        self::VIEW_NOTE, self::RESPOND, self::REQUEST, self::WAITLIST,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true) && $subject instanceof Book;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Book $book */
        $book = $subject;
        $isOwner = $book->isOwnedBy($user);

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT, self::DELETE => $isOwner || $user->isAdmin(),
            self::VIEW_NOTE, self::RESPOND => $isOwner,
            self::LEND => $isOwner && $book->isAvailable(),
            self::RETURN => $isOwner && !$book->isAvailable(),
            self::REQUEST => $this->canRequest($book, $user, $isOwner, $vote),
            // Warteliste: nur bei verliehenen Büchern, nicht für Eigentümer und die Person, die es gerade hat
            self::WAITLIST => !$isOwner && !$book->isAvailable()
                && $book->getActiveLoan()?->getBorrower()?->getId() !== $user->getId()
                && null === $book->getWaitlistEntryOf($user),
            default => false,
        };
    }

    private function canRequest(Book $book, User $user, bool $isOwner, ?Vote $vote): bool
    {
        if ($isOwner) {
            $vote?->addReason('Das eigene Buch kann man nicht anfragen.');

            return false;
        }
        if (!$book->isAvailable()) {
            $vote?->addReason('Das Buch ist gerade ausgeliehen.');

            return false;
        }

        return null === $book->getOpenRequestBy($user);
    }
}
