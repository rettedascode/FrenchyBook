<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Verschickt E-Mails an Nutzer – immer in deren Sprache (Betreff und Inhalt).
 * Schlägt der Versand fehl, wird das geloggt und false zurückgegeben.
 */
class UserMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $subjectParams
     * @param array<string, mixed> $context
     */
    public function send(User $to, string $subjectKey, array $subjectParams, string $template, array $context = []): bool
    {
        // Demo-/Testkonten (reservierte Endung .invalid) bekommen keine Mails – sonst gäbe es Rückläufer beim Mailanbieter
        if (str_ends_with(strtolower((string) $to->getEmail()), '.invalid')) {
            return false;
        }

        $locale = $to->getLocale();
        $email = (new TemplatedEmail())
            ->to(new Address((string) $to->getEmail(), (string) $to->getName()))
            ->subject($this->translator->trans($subjectKey, $subjectParams, 'messages', $locale))
            ->htmlTemplate($template)
            ->locale($locale)
            ->context($context + ['recipient' => $to]);

        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('E-Mail konnte nicht verschickt werden.', ['to' => $to->getEmail(), 'subject' => $subjectKey, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
