<?php

namespace App\Twig;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\Language;
use App\Repository\LoanRequestRepository;
use App\Service\CoverManager;
use App\Service\PushNotifier;
use App\Util\LocalizedDate;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class AppExtension
{
    /** Ruhige, kontrastreiche Farben für Cover-Platzhalter und Avatare (weiße Schrift ≥ 4.5:1). */
    private const PALETTE = [
        '#2c55b8', // Blau
        '#8a3b5c', // Beere
        '#2f6b4f', // Tannengrün
        '#9a4a1c', // Terrakotta
        '#4b3f8f', // Indigo
        '#1f6474', // Petrol
        '#7a5a12', // Senf (dunkel)
        '#5a5f69', // Schiefer
    ];

    private ?int $openRequestCount = null;

    public function __construct(
        private readonly Security $security,
        private readonly LoanRequestRepository $loanRequests,
        private readonly CoverManager $covers,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(MAILER_DSN)%')] private readonly string $mailerDsn,
        private readonly PushNotifier $push,
    ) {
    }

    /** Sind Push-Benachrichtigungen eingerichtet (VAPID-Schlüssel vorhanden)? */
    #[AsTwigFunction('push_enabled')]
    public function pushEnabled(): bool
    {
        return $this->push->isEnabled();
    }

    #[AsTwigFunction('push_public_key')]
    public function pushPublicKey(): string
    {
        return $this->push->getPublicKey();
    }

    /** Ist ein echter Mailversand eingerichtet? (Sonst z. B. „Passwort vergessen“ ausblenden.) */
    #[AsTwigFunction('mail_enabled')]
    public function mailEnabled(): bool
    {
        return '' !== $this->mailerDsn && !str_starts_with($this->mailerDsn, 'null://');
    }

    /** Anzahl offener Anfragen an den eingeloggten Nutzer (Badge in der Navigation). */
    #[AsTwigFunction('open_request_count')]
    public function openRequestCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->openRequestCount ??= $this->loanRequests->countOpenForOwner($user);
    }

    /** Pfad des Covers relativ zu public/ (Eingabe für den imagine_filter) oder null. */
    #[AsTwigFunction('cover_path')]
    public function coverPath(Book $book): ?string
    {
        return null !== $book->getCoverImage() ? $this->covers->publicPath($book->getCoverImage()) : null;
    }

    #[AsTwigFunction('back_path')]
    public function backPath(Book $book): ?string
    {
        return null !== $book->getBackImage() ? $this->covers->publicPath($book->getBackImage()) : null;
    }

    #[AsTwigFunction('cover_color')]
    public function coverColor(Book $book): string
    {
        return self::PALETTE[crc32((string) $book->getTitle()) % \count(self::PALETTE)];
    }

    #[AsTwigFunction('avatar_color')]
    public function avatarColor(?User $user): string
    {
        return self::PALETTE[crc32((string) $user?->getEmail()) % \count(self::PALETTE)];
    }

    /** Datum im Format der aktuellen Sprache: {{ loan.dueAt|ldate }} oder {{ loan.dueAt|ldate('short') }} */
    #[AsTwigFilter('ldate')]
    public function localizedDate(?\DateTimeInterface $date, string $style = 'long'): string
    {
        return LocalizedDate::format($date, $this->translator->getLocale(), $style);
    }

    /** Name einer Buchsprache in der Oberflächensprache: {{ 'fr'|language_name }} → „Französisch“ / „Français“ */
    #[AsTwigFilter('language_name')]
    public function languageName(?string $code): string
    {
        return Language::name($code, $this->translator->getLocale());
    }

    /** Standard-Genres werden übersetzt, selbst angelegte bleiben wie eingegeben. */
    #[AsTwigFilter('genre_name')]
    public function genreName(string $name): string
    {
        return $this->translator->trans($name, [], 'genres');
    }

    /** „heute“, „gestern“, „vor 3 Tagen“ … sonst das Datum. */
    #[AsTwigFilter('relative_date')]
    public function relativeDate(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '';
        }
        $today = new \DateTimeImmutable('today');
        $day = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
        $diff = (int) $today->diff($day)->format('%r%a');

        return match (true) {
            0 === $diff => $this->translator->trans('date.today'),
            -1 === $diff => $this->translator->trans('date.yesterday'),
            1 === $diff => $this->translator->trans('date.tomorrow'),
            $diff < 0 && $diff > -7 => $this->translator->trans('date.days_ago', ['days' => -$diff]),
            $diff > 1 && $diff < 7 => $this->translator->trans('date.in_days', ['days' => $diff]),
            default => $this->localizedDate($date),
        };
    }

    /** „seit 12 Tagen“, „seit heute“ – für Ausleihen. */
    #[AsTwigFilter('since')]
    public function since(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '';
        }
        $days = (int) (new \DateTimeImmutable('today'))->diff(\DateTimeImmutable::createFromInterface($date)->setTime(0, 0))->days;

        return match (true) {
            0 === $days => $this->translator->trans('date.since_today'),
            1 === $days => $this->translator->trans('date.since_yesterday'),
            $days < 60 => $this->translator->trans('date.since_days', ['days' => $days]),
            default => $this->translator->trans('date.since_date', ['date' => $this->localizedDate($date)]),
        };
    }
}
