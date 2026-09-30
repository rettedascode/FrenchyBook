<?php

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Verschickt Push-Benachrichtigungen an alle Geräte eines Nutzers – in dessen Sprache.
 *
 * Ist Push nicht eingerichtet (keine VAPID-Schlüssel), passiert einfach nichts.
 * Geräte, die der Push-Dienst als abgemeldet meldet, werden automatisch entfernt.
 */
class PushNotifier
{
    public function __construct(
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')] private readonly string $publicKey,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')] private readonly string $privateKey,
        #[Autowire('%env(VAPID_SUBJECT)%')] private readonly string $subject,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->publicKey && '' !== $this->privateKey;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @param array<string, mixed> $titleParams
     * @param array<string, mixed> $bodyParams
     * @param array<string, mixed> $routeParams
     *
     * @return int Anzahl der Geräte, an die erfolgreich zugestellt wurde
     */
    public function notify(
        User $user,
        string $titleKey,
        array $titleParams,
        string $bodyKey,
        array $bodyParams,
        string $route,
        array $routeParams = [],
        ?string $tag = null,
    ): int {
        if (!$this->isEnabled()) {
            return 0;
        }
        $devices = $this->subscriptions->findByUser($user);
        if ([] === $devices) {
            return 0;
        }

        $locale = $user->getLocale();
        $payload = json_encode([
            'title' => $this->translator->trans($titleKey, $titleParams, 'messages', $locale),
            'body' => $this->translator->trans($bodyKey, $bodyParams, 'messages', $locale),
            'url' => $this->urlGenerator->generate($route, $routeParams),
            'tag' => $tag,
            'lang' => $locale,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        return $this->send($devices, $payload);
    }

    /** @param list<PushSubscription> $devices */
    private function send(array $devices, string $payload): int
    {
        try {
            // Kurzer Timeout: Ein hängender Push-Dienst soll keine Seite ausbremsen
            $psr17 = new Psr17Factory();
            $client = new Psr18Client(HttpClient::create(['timeout' => 5, 'max_duration' => 8]), $psr17, $psr17);
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $this->subject,
                    'publicKey' => $this->publicKey,
                    'privateKey' => $this->privateKey,
                ],
            ], ['TTL' => 86400, 'urgency' => 'normal'], $client, $psr17, $psr17);
        } catch (\Throwable $e) {
            $this->logger->error('Push ist falsch konfiguriert.', ['error' => $e->getMessage()]);

            return 0;
        }

        $byEndpoint = [];
        foreach ($devices as $device) {
            $byEndpoint[$device->getEndpoint()] = $device;
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $device->getEndpoint(),
                'publicKey' => $device->getPublicKey(),
                'authToken' => $device->getAuthToken(),
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
        }

        $delivered = 0;
        try {
            foreach ($webPush->flush() as $report) {
                $device = $byEndpoint[$report->getEndpoint()] ?? null;
                if ($report->isSuccess()) {
                    $device?->markSuccess();
                    ++$delivered;
                } elseif ($report->isSubscriptionExpired() && null !== $device) {
                    // Gerät hat Push abgemeldet oder App deinstalliert → aufräumen
                    $this->em->remove($device);
                } else {
                    $this->logger->warning('Push konnte nicht zugestellt werden.', ['reason' => $report->getReason()]);
                }
            }
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Push-Versand fehlgeschlagen.', ['error' => $e->getMessage()]);
        }

        return $delivered;
    }
}
