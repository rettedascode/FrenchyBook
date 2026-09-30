<?php

namespace App\Command;

use App\Repository\LoanRepository;
use App\Service\LoanNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Erinnert Ausleiher per E-Mail und Push:
 * - einmal kurz VOR dem Rückgabetermin (--before Tage vorher, Standard: 2)
 * - wenn die Ausleihe überfällig ist – höchstens alle --interval Tage (Standard: 7)
 *
 * Für den täglichen Cronjob gedacht.
 */
#[AsCommand(
    name: 'app:send-reminders',
    description: 'Erinnert Ausleiher per E-Mail an überfällige Bücher',
)]
final class SendRemindersCommand
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly LoanNotifier $notifier,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nur anzeigen, wer erinnert würde – nichts verschicken')] bool $dryRun = false,
        #[Option(description: 'Mindestabstand in Tagen zwischen zwei Erinnerungen für dieselbe Ausleihe')] int $interval = 7,
        #[Option(description: 'So viele Tage vor dem Rückgabetermin wird einmal erinnert')] int $before = 2,
    ): int {
        $now = new \DateTimeImmutable();
        $interval = max(1, $interval);

        // 1) Vorab-Erinnerung: Rückgabetermin steht kurz bevor
        $dueSoon = $this->loans->findDueSoon($now, max(0, $before));
        $soonRows = [];
        foreach ($dueSoon as $loan) {
            if ($dryRun) {
                $status = 'würde erinnert';
            } elseif ($this->notifier->dueSoonReminder($loan)) {
                $loan->setDueSoonReminderSentAt($now);
                $status = 'erinnert';
            } else {
                $status = 'FEHLER beim Versand';
            }
            $soonRows[] = [$loan->getBook()->getTitle(), $loan->getBorrower()->getName(), $loan->getDueAt()?->format('d.m.Y'), $status];
        }
        $this->em->flush();
        if ([] !== $soonRows) {
            $io->section('Bald fällig');
            $io->table(['Buch', 'Ausleiher', 'Fällig am', 'Status'], $soonRows);
        }

        // 2) Überfällig
        $overdue = $this->loans->findOverdue($now);

        if ([] === $overdue) {
            $io->success('Keine überfälligen Ausleihen – alle sind pünktlich. 📚');

            return Command::SUCCESS;
        }

        $rows = [];
        $sent = 0;
        foreach ($overdue as $loan) {
            $last = $loan->getReminderSentAt();
            $due = null === $last || $last <= $now->modify(sprintf('-%d days', $interval))->modify('+1 hour');
            $status = 'übersprungen (zuletzt '.$last?->format('d.m.Y').')';

            if ($due) {
                if ($dryRun) {
                    $status = 'würde erinnert';
                } elseif ($this->notifier->overdueReminder($loan)) {
                    $loan->setReminderSentAt($now);
                    $status = 'erinnert';
                    ++$sent;
                } else {
                    $status = 'FEHLER beim Versand';
                }
            }

            $rows[] = [
                $loan->getBook()->getTitle(),
                $loan->getBorrower()->getName().' <'.$loan->getBorrower()->getEmail().'>',
                $loan->getDueAt()?->format('d.m.Y'),
                $loan->getDaysOverdue(),
                $status,
            ];
        }

        $this->em->flush();

        $io->table(['Buch', 'Ausleiher', 'Fällig am', 'Tage drüber', 'Status'], $rows);
        $dryRun
            ? $io->note('Testlauf – es wurde nichts verschickt.')
            : $io->success(sprintf('%d Erinnerung(en) verschickt.', $sent));

        return Command::SUCCESS;
    }
}
