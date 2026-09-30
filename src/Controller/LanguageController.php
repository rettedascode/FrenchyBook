<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\LocaleSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sprache umschalten (Deutsch/Français). Die Wahl wird in Session und Cookie gemerkt
 * (bleibt ein Jahr, auch nach dem Abmelden) und bei angemeldeten Nutzern im Profil.
 */
class LanguageController extends AbstractController
{
    #[Route('/sprache/{locale}', name: 'app_language', requirements: ['locale' => 'de|fr'], methods: ['GET'])]
    public function switch(string $locale, Request $request, EntityManagerInterface $em): Response
    {
        $request->getSession()->set(LocaleSubscriber::SESSION_KEY, $locale);

        $user = $this->getUser();
        if ($user instanceof User && $user->getLocale() !== $locale) {
            $user->setLocale($locale);
            $em->flush();
        }

        // Zurück auf die Seite, von der man kam (nur innerhalb der App)
        $referer = (string) $request->headers->get('referer');
        $response = '' !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost().'/')
            ? $this->redirect($referer)
            : $this->redirectToRoute($user ? 'app_dashboard' : 'app_login');
        $response->headers->setCookie(LocaleSubscriber::cookie($locale, $request));

        return $response;
    }
}
