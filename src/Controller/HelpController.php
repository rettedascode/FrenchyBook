<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * „So funktioniert FrenchyBook“ – Schritt-für-Schritt-Anleitung.
 * Auch ohne Anmeldung erreichbar, damit man den Link vor der Registrierung verschicken kann.
 */
class HelpController extends AbstractController
{
    #[Route('/hilfe', name: 'app_help', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('help/index.html.twig');
    }
}
