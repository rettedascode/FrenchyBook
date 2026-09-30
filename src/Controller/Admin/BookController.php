<?php

namespace App\Controller\Admin;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/admin/buecher')]
class BookController extends AbstractAdminController
{
    #[Route('', name: 'admin_books', methods: ['GET'])]
    public function index(Request $request, BookRepository $books, UserRepository $users, LoanRepository $loans): Response
    {
        $query = trim($request->query->getString('q'));
        $ownerId = $request->query->getInt('besitzer');
        $owner = $ownerId > 0 ? $users->find($ownerId) : null;
        $result = $books->adminSearch($query, $owner);

        return $this->render('admin/books.html.twig', [
            'books' => $result,
            'activeLoans' => $loans->findActiveByBooks($result),
            'users' => $users->findBy([], ['name' => 'ASC']),
            'query' => $query,
            'owner' => $owner,
        ]);
    }

    /** Buch wurde versehentlich beim falschen Mitglied eingetragen → umhängen */
    #[Route('/{id}/besitzer', name: 'admin_book_owner', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function changeOwner(Request $request, Book $book, UserRepository $users, LoanRepository $loans, EntityManagerInterface $em): Response
    {
        $back = $this->redirectToRoute('admin_books', array_filter(['q' => $request->getPayload()->getString('q')]), Response::HTTP_SEE_OTHER);
        if (!$this->csrfOk($request, 'admin-book-owner-'.$book->getId())) {
            return $back;
        }

        $newOwner = $users->find($request->getPayload()->getInt('owner'));
        if (null === $newOwner || $book->isOwnedBy($newOwner)) {
            return $back;
        }
        if ($loans->findActiveForBook($book)?->getBorrower() === $newOwner) {
            $this->flash('error', 'admin.books.owner_is_borrower', ['name' => $newOwner->getName()]);

            return $back;
        }
        // Eine offene Anfrage des neuen Besitzers auf sein eigenes Buch ergibt keinen Sinn mehr
        if (null !== ($own = $book->getOpenRequestBy($newOwner))) {
            $book->getLoanRequests()->removeElement($own);
            $em->remove($own);
        }

        $oldOwner = $book->getOwner();
        $book->setOwner($newOwner);
        $em->flush();
        $this->flash('success', 'admin.books.flash_owner', [
            'title' => $book->getTitle(), 'from' => $oldOwner->getName(), 'to' => $newOwner->getName(),
        ]);

        return $back;
    }
}
