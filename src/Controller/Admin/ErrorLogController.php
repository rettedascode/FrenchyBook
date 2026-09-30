<?php

namespace App\Controller\Admin;

use App\Service\ErrorLog;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/fehler')]
class ErrorLogController extends AbstractAdminController
{
    private const DAYS = 14;

    #[Route('', name: 'admin_errors', methods: ['GET'])]
    public function index(ErrorLog $errorLog): Response
    {
        return $this->render('admin/errors.html.twig', [
            'groups' => $errorLog->grouped(self::DAYS),
            'days' => self::DAYS,
        ]);
    }

    #[Route('/leeren', name: 'admin_errors_clear', methods: ['POST'])]
    public function clear(Request $request, ErrorLog $errorLog): Response
    {
        if ($this->csrfOk($request, 'admin-errors-clear')) {
            $errorLog->clear();
            $this->flash('success', 'admin.errors.flash_cleared');
        }

        return $this->redirectToRoute('admin_errors', [], Response::HTTP_SEE_OTHER);
    }
}
