<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Gemeinsame Helfer für die Verwaltung (/admin, nur ROLE_ADMIN – siehe security.yaml).
 */
abstract class AbstractAdminController extends AbstractController
{
    /** Prüft das CSRF-Token eines Admin-Formulars; zeigt sonst eine Meldung. */
    protected function csrfOk(Request $request, string $id): bool
    {
        if ($this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            return true;
        }
        $this->addFlash('error', new TranslatableMessage('flash.csrf_failed'));

        return false;
    }

    protected function flash(string $type, string $key, array $params = []): void
    {
        $this->addFlash($type, new TranslatableMessage($key, $params));
    }
}
