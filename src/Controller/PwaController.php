<?php

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Service\PushNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Alles, was FrenchyBook zur installierbaren App (PWA) macht:
 * Web-App-Manifest in der Sprache des Besuchers und die Verwaltung der Push-Abos.
 */
class PwaController extends AbstractController
{
    #[Route('/manifest.webmanifest', name: 'app_manifest', methods: ['GET'])]
    public function manifest(Request $request, TranslatorInterface $translator, Packages $assets): JsonResponse
    {
        $t = static fn (string $key) => $translator->trans($key);
        $icon = static fn (string $path, string $sizes, string $type = 'image/png', string $purpose = 'any') => [
            'src' => $assets->getUrl($path), 'sizes' => $sizes, 'type' => $type, 'purpose' => $purpose,
        ];

        $manifest = [
            'id' => '/',
            'name' => 'FrenchyBook',
            'short_name' => 'FrenchyBook',
            'description' => $t('app.description'),
            'lang' => $request->getLocale(),
            'dir' => 'ltr',
            'start_url' => '/?source=app',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'portrait-primary',
            'background_color' => '#f6f5f2',
            'theme_color' => '#f6f5f2',
            'categories' => ['books', 'lifestyle', 'social'],
            'icons' => [
                $icon('icon-192.png', '192x192'),
                $icon('icon-512.png', '512x512'),
                $icon('icon-maskable-512.png', '512x512', purpose: 'maskable'),
                $icon('favicon.svg', 'any', 'image/svg+xml'),
            ],
            // Langes Drücken aufs App-Symbol
            'shortcuts' => [
                ['name' => $t('pwa.shortcut.scan'), 'short_name' => $t('pwa.shortcut.scan_short'), 'url' => '/buecher/neu?scan=1', 'icons' => [$icon('shortcut-scan.png', '96x96')]],
                ['name' => $t('nav.books'), 'url' => '/buecher', 'icons' => [$icon('shortcut-books.png', '96x96')]],
                ['name' => $t('nav.dashboard'), 'url' => '/', 'icons' => [$icon('shortcut-home.png', '96x96')]],
            ],
            // Vorschaubilder im Installationsdialog (Android/Chrome)
            'screenshots' => [
                ['src' => $assets->getUrl('screenshots/books.webp'), 'sizes' => '780x1688', 'type' => 'image/webp', 'form_factor' => 'narrow', 'label' => $t('pwa.screenshot.books')],
                ['src' => $assets->getUrl('screenshots/book.webp'), 'sizes' => '780x1688', 'type' => 'image/webp', 'form_factor' => 'narrow', 'label' => $t('pwa.screenshot.book')],
                ['src' => $assets->getUrl('screenshots/desktop.webp'), 'sizes' => '1280x800', 'type' => 'image/webp', 'form_factor' => 'wide', 'label' => $t('pwa.screenshot.books')],
            ],
        ];

        $response = new JsonResponse($manifest);
        $response->headers->set('Content-Type', 'application/manifest+json');
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setVary(['Accept-Language', 'Cookie']);

        return $response;
    }

    #[Route('/push/abonnieren', name: 'app_push_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        #[CurrentUser] User $user,
        PushSubscriptionRepository $subscriptions,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->checkToken($request);
        $data = $request->toArray();
        $endpoint = (string) ($data['endpoint'] ?? '');
        $publicKey = (string) ($data['keys']['p256dh'] ?? '');
        $authToken = (string) ($data['keys']['auth'] ?? '');

        if (!str_starts_with($endpoint, 'https://') || \strlen($endpoint) > 2000 || '' === $publicKey || '' === $authToken) {
            return new JsonResponse(['ok' => false], Response::HTTP_BAD_REQUEST);
        }

        $label = mb_substr(trim((string) ($data['device'] ?? '')), 0, 100) ?: null;
        $subscription = $subscriptions->findOneByEndpoint($endpoint);
        if (null === $subscription) {
            $subscription = new PushSubscription($user, $endpoint, $publicKey, $authToken);
            $subscription->setDeviceLabel($label);
            $em->persist($subscription);
        } else {
            $subscription->update($user, $publicKey, $authToken, $label);
        }
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/push/abbestellen', name: 'app_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(
        Request $request,
        #[CurrentUser] User $user,
        PushSubscriptionRepository $subscriptions,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->checkToken($request);
        $subscription = $subscriptions->findOneByEndpoint((string) ($request->toArray()['endpoint'] ?? ''));
        if (null !== $subscription && $subscription->getUser()->getId() === $user->getId()) {
            $em->remove($subscription);
            $em->flush();
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/push/test', name: 'app_push_test', methods: ['POST'])]
    public function test(Request $request, #[CurrentUser] User $user, PushNotifier $push): JsonResponse
    {
        $this->checkToken($request);
        $delivered = $push->notify($user, 'push.test.title', [], 'push.test.body', [], 'app_profile', [], 'test');

        return new JsonResponse(['ok' => $delivered > 0, 'delivered' => $delivered]);
    }

    private function checkToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('push', (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
    }
}
