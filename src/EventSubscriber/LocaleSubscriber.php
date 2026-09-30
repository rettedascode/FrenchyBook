<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Legt die Sprache für jeden Request fest:
 * 1. angemeldet → Spracheinstellung aus dem Profil
 * 2. sonst → zuletzt gewählte Sprache (Session, dann Cookie – bleibt auch nach dem Abmelden)
 * 3. sonst → Browsersprache (framework.set_locale_from_accept_language), Standard Deutsch
 *
 * Läuft direkt nach der Firewall (Priorität 7), damit der eingeloggte Nutzer bekannt ist.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY = '_locale';
    public const COOKIE = 'frenchybook_locale';

    /** @param list<string> $enabledLocales */
    public function __construct(
        private readonly Security $security,
        private readonly LocaleSwitcher $localeSwitcher,
        #[Autowire('%kernel.enabled_locales%')] private readonly array $enabledLocales,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /** Cookie für die gewählte Sprache – ein Jahr gültig */
    public static function cookie(string $locale, Request $request): Cookie
    {
        return Cookie::create(self::COOKIE, $locale, new \DateTimeImmutable('+1 year'), '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX);
    }

    /**
     * Läuft auch für Unter-Requests – Fehlerseiten (403, 404, 500) werden in einem
     * Unter-Request gerendert und sollen ebenfalls in der richtigen Sprache erscheinen.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $locale = null;
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $locale = $user->getLocale();
            // In der Session merken: greift auch bei Fehlern, die noch vor der Firewall entstehen (z. B. 404)
            if ($event->isMainRequest() && $request->hasSession() && $request->getSession()->get(self::SESSION_KEY) !== $locale) {
                $request->getSession()->set(self::SESSION_KEY, $locale);
            }
        } else {
            if ($request->hasPreviousSession()) {
                $locale = $request->getSession()->get(self::SESSION_KEY);
            }
            $locale ??= $request->cookies->get(self::COOKIE);
        }

        if (\is_string($locale) && \in_array($locale, $this->enabledLocales, true)) {
            $request->setLocale($locale);
            $this->localeSwitcher->setLocale($locale);
        }
    }

    /** Angemeldet: Cookie an das Profil angleichen, damit die Sprache nach dem Abmelden bleibt */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $user = $this->security->getUser();
        $request = $event->getRequest();
        if ($user instanceof User && $request->cookies->get(self::COOKIE) !== $user->getLocale()) {
            $event->getResponse()->headers->setCookie(self::cookie($user->getLocale(), $request));
        }
    }
}
