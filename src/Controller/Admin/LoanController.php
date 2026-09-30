<?php

namespace App\Controller\Admin;

use App\Entity\Loan;
use App\Entity\LoanRequest;
use App\Exception\LoanException;
use App\Repository\LoanRepository;
use App\Repository\LoanRequestRepository;
use App\Service\LoanManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Hängende Ausleihen und Anfragen korrigieren – ohne Benachrichtigungen,
 * weil es um das Aufräumen von Fehlern geht.
 */
#[Route('/admin')]
class LoanController extends AbstractAdminController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/ausleihen', name: 'admin_loans', methods: ['GET'])]
    public function index(LoanRepository $loans, LoanRequestRepository $requests): Response
    {
        return $this->render('admin/loans.html.twig', [
            'loans' => $loans->findAllActive(),
            'requests' => $requests->findAllOpen(),
        ]);
    }

    #[Route('/ausleihen/{id}/zurueck', name: 'admin_loan_return', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function markReturned(Request $request, Loan $loan, LoanManager $manager): Response
    {
        if ($this->csrfOk($request, 'admin-loan-return-'.$loan->getId())) {
            try {
                $manager->markReturned($loan);
                $this->flash('success', 'admin.loans.flash_returned', ['title' => $loan->getBook()->getTitle()]);
            } catch (LoanException $e) {
                $this->addFlash('error', $e->toMessage());
            }
        }

        return $this->redirectToRoute('admin_loans', [], Response::HTTP_SEE_OTHER);
    }

    /** Versehentlich eingetragene Ausleihe komplett entfernen (taucht auch nicht im Verlauf auf) */
    #[Route('/ausleihen/{id}/loeschen', name: 'admin_loan_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function deleteLoan(Request $request, Loan $loan): Response
    {
        if ($this->csrfOk($request, 'admin-loan-delete-'.$loan->getId())) {
            $title = $loan->getBook()->getTitle();
            $loan->getBook()->getLoans()->removeElement($loan);
            $this->em->remove($loan);
            $this->em->flush();
            $this->flash('success', 'admin.loans.flash_deleted', ['title' => $title]);
        }

        return $this->redirectToRoute('admin_loans', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/anfragen/{id}/loeschen', name: 'admin_request_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function deleteRequest(Request $request, LoanRequest $loanRequest): Response
    {
        if ($this->csrfOk($request, 'admin-request-delete-'.$loanRequest->getId())) {
            $title = $loanRequest->getBook()->getTitle();
            $loanRequest->getBook()->getLoanRequests()->removeElement($loanRequest);
            $this->em->remove($loanRequest);
            $this->em->flush();
            $this->flash('success', 'admin.loans.flash_request_deleted', ['title' => $title]);
        }

        return $this->redirectToRoute('admin_loans', ['_fragment' => 'anfragen'], Response::HTTP_SEE_OTHER);
    }
}
