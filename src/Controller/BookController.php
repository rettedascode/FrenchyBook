<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Genre;
use App\Entity\User;
use App\Enum\BookFormat;
use App\Exception\CoverException;
use App\Exception\OpenLibraryException;
use App\Form\BookType;
use App\Form\LendType;
use App\Model\BookFilter;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Repository\GenreRepository;
use App\Repository\LoanRepository;
use App\Repository\UserRepository;
use App\Security\Voter\BookVoter;
use App\Service\CoverManager;
use App\Service\OpenLibraryClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/buecher')]
class BookController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookRepository $books,
        private readonly CoverManager $covers,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // ------------------------------------------------------------------
    // Bücherliste
    // ------------------------------------------------------------------

    #[Route('', name: 'app_book_index', methods: ['GET'])]
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        LoanRepository $loans,
        GenreRepository $genres,
        UserRepository $users,
        AuthorRepository $authors,
    ): Response {
        $filter = BookFilter::fromRequest($request);
        $result = $this->books->search($filter, $user);
        $params = [
            'filter' => $filter,
            'books' => $result['books'],
            'hasMore' => $result['hasMore'],
            'activeLoans' => $loans->findActiveByBooks($result['books']),
        ];

        // Weitere Seiten werden per Turbo-Frame „lazy“ nachgeladen – dann reicht der Grid-Ausschnitt.
        if ($filter->page > 1) {
            return $this->render('book/_grid_page.html.twig', $params);
        }

        $params['total'] = $this->books->countMatching($filter, $user);
        $params['totalAll'] = $filter->hasActiveFilters() ? $this->books->count([]) : null;

        // Suche/Filter per Turbo-Frame: nur die Trefferliste neu rendern
        if ('book-results' === $request->headers->get('Turbo-Frame')) {
            return $this->render('book/_results.html.twig', $params);
        }

        return $this->render('book/index.html.twig', $params + [
            'genres' => $genres->findUsed(),
            'languages' => $this->books->findUsedLanguages(),
            'formats' => $this->books->findUsedFormats(),
            'owners' => $users->findOwnersWithBooks(),
            'activeAuthor' => $filter->author ? $authors->find($filter->author) : null,
        ]);
    }

    // ------------------------------------------------------------------
    // Buch hinzufügen: 1. ISBN scannen/eintippen → 2. Daten prüfen & speichern
    // ------------------------------------------------------------------

    #[Route('/neu', name: 'app_book_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        #[CurrentUser] User $user,
        OpenLibraryClient $openLibrary,
        ValidatorInterface $validator,
        AuthorRepository $authors,
    ): Response {
        // Jedes Mitglied darf höchstens Book::MAX_PER_OWNER Bücher einstellen
        $myBookCount = $this->books->countByOwner($user);
        if ($myBookCount >= Book::MAX_PER_OWNER) {
            $this->addFlash('warning', new TranslatableMessage('flash.book.limit', ['max' => Book::MAX_PER_OWNER]));

            return $this->redirectToRoute('app_book_index', ['mine' => 1], Response::HTTP_SEE_OTHER);
        }

        $book = new Book();
        $book->setOwner($user);
        $scanMode = $request->query->getBoolean('scan') || $request->request->getBoolean('scan');
        $isbnInput = trim($request->query->getString('isbn'));
        $manual = $request->query->getBoolean('manuell');

        $form = $this->createForm(BookType::class, $book, [
            'action' => $this->generateUrl('app_book_new', array_filter(['scan' => $scanMode ? 1 : null])),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid() && $this->handleCover($form, $book)) {
                $this->addNewGenre($form, $book);
                $this->em->persist($book);
                $this->em->flush();

                $this->addFlash('success', new TranslatableMessage('flash.book.saved', ['title' => $book->getTitle()]));

                return $scanMode
                    ? $this->redirectToRoute('app_book_new', ['scan' => 1], Response::HTTP_SEE_OTHER)
                    : $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
            }

            return $this->renderForm($form, $book, 'new', ['scanMode' => $scanMode]);
        }

        // Schritt 1: Noch keine ISBN und keine manuelle Eingabe gewählt
        if ('' === $isbnInput && !$manual) {
            return $this->render('book/new_isbn.html.twig', ['scanMode' => $scanMode, 'isbn' => '', 'myBookCount' => $myBookCount]);
        }

        // Schritt 2: Formular – ggf. mit Daten aus Open Library vorausgefüllt
        $lookup = null;
        $lookupStatus = null;
        if ('' !== $isbnInput) {
            $isbn = strtoupper((string) preg_replace('/[^0-9Xx]/', '', $isbnInput));
            if (0 < \count($validator->validate($isbn, new Isbn()))) {
                return $this->render('book/new_isbn.html.twig', [
                    'scanMode' => $scanMode,
                    'myBookCount' => $myBookCount,
                    'isbn' => $isbnInput,
                    'isbnError' => 'isbn.error.invalid',
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $book->setIsbn($isbn);
            try {
                $lookup = $openLibrary->findByIsbn($isbn);
                $lookupStatus = null === $lookup ? 'not_found' : 'found';
            } catch (OpenLibraryException) {
                $lookupStatus = 'unavailable';
            }
        }

        if (null !== $lookup) {
            $lookup->applyTo($book);
            $book->setAuthors($authors->findOrCreateByNames($lookup->authors));
        }
        $form = $this->createForm(BookType::class, $book, [
            'action' => $this->generateUrl('app_book_new', array_filter(['scan' => $scanMode ? 1 : null])),
        ]);
        $form->get('coverUrl')->setData($lookup?->coverUrl);

        return $this->renderForm($form, $book, 'new', [
            'scanMode' => $scanMode,
            'lookupStatus' => $lookupStatus,
            'duplicate' => $book->getIsbn() ? $this->books->findOneByOwnerAndIsbn($user, $book->getIsbn()) : null,
        ]);
    }

    // ------------------------------------------------------------------
    // Detailseite
    // ------------------------------------------------------------------

    #[Route('/{id}', name: 'app_book_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user, UserRepository $users, ?FormInterface $lendForm = null): Response
    {
        $book = $this->books->findForDetail($id) ?? throw new NotFoundHttpException('Buch nicht gefunden.');
        $this->denyAccessUnlessGranted(BookVoter::VIEW, $book);

        if (null === $lendForm && $this->isGranted(BookVoter::LEND, $book)) {
            $lendForm = $this->createForm(LendType::class, null, [
                'friends' => $users->findAllExcept($user),
                'action' => $this->generateUrl('app_loan_create', ['id' => $book->getId()]),
            ]);
        }

        return $this->render('book/show.html.twig', [
            'book' => $book,
            'activeLoan' => $book->getActiveLoan(),
            'myRequest' => $book->getOpenRequestBy($user),
            'lendForm' => $lendForm,
        ]);
    }

    // ------------------------------------------------------------------
    // Bearbeiten & Löschen (nur Besitzer)
    // ------------------------------------------------------------------

    #[Route('/{id}/bearbeiten', name: 'app_book_edit', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(BookVoter::EDIT, subject: 'book')]
    public function edit(Request $request, Book $book): Response
    {
        $form = $this->createForm(BookType::class, $book, [
            'allow_remove_cover' => null !== $book->getCoverImage(),
            'show_owner_note' => $book->isOwnedBy($this->getUser()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid() && $this->handleCover($form, $book)) {
                $this->addNewGenre($form, $book);
                $book->touch();
                $this->em->flush();
                $this->addFlash('success', new TranslatableMessage('flash.book.updated'));

                return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->renderForm($form, $book, 'edit');
    }

    #[Route('/{id}/loeschen', name: 'app_book_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(BookVoter::DELETE, subject: 'book')]
    public function delete(Request $request, Book $book): Response
    {
        if (!$this->isCsrfTokenValid('delete-book-'.$book->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', new TranslatableMessage('flash.csrf_failed'));

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()], Response::HTTP_SEE_OTHER);
        }

        $title = $book->getTitle();
        $cover = $book->getCoverImage();
        $ownBook = $book->isOwnedBy($this->getUser());

        $this->em->remove($book);
        $this->em->flush();
        $this->covers->remove($cover);

        $this->addFlash('success', new TranslatableMessage('flash.book.deleted', ['title' => $title]));

        // Admin hat ein fremdes Buch gelöscht → zurück in die Verwaltung
        return $ownBook
            ? $this->redirectToRoute('app_book_index', ['mine' => 1], Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute('admin_books', [], Response::HTTP_SEE_OTHER);
    }

    // ------------------------------------------------------------------
    // Hilfsmethoden
    // ------------------------------------------------------------------

    /**
     * Verarbeitet Upload, Open-Library-Cover oder „Cover entfernen“.
     * Gibt false zurück, wenn das Bild nicht verarbeitet werden konnte (Fehler steht dann am Feld).
     */
    private function handleCover(FormInterface $form, Book $book): bool
    {
        $upload = $form->get('coverFile')->getData();
        $remoteUrl = trim((string) $form->get('coverUrl')->getData());
        $remove = $form->has('removeCover') && true === $form->get('removeCover')->getData();
        $oldCover = $book->getCoverImage();

        try {
            if ($upload instanceof UploadedFile) {
                $book->setCoverImage($this->covers->storeUpload($upload));
            } elseif ('' !== $remoteUrl) {
                // Schlägt der Download fehl, bekommt das Buch eben den hübschen Platzhalter.
                $book->setCoverImage($this->covers->storeFromUrl($remoteUrl) ?? $oldCover);
            } elseif ($remove) {
                $book->setCoverImage(null);
            }
        } catch (CoverException $e) {
            $form->get('coverFile')->addError(new FormError($e->toMessage()->trans($this->translator)));

            return false;
        }

        if (null !== $oldCover && $oldCover !== $book->getCoverImage()) {
            $this->covers->remove($oldCover);
        }

        return true;
    }

    private function addNewGenre(FormInterface $form, Book $book): void
    {
        $name = trim((string) $form->get('newGenre')->getData());
        if ('' !== $name) {
            $book->addGenre($this->em->getRepository(Genre::class)->findOrCreate($name));
        }
    }

    private function renderForm(FormInterface $form, Book $book, string $mode, array $extra = []): Response
    {
        return $this->render('book/form.html.twig', [
            'form' => $form,
            'book' => $book,
            'mode' => $mode,
            'formats' => BookFormat::cases(),
            'authorSuggestions' => $this->em->getRepository(Author::class)->findBy([], ['name' => 'ASC'], 300),
        ] + $extra + ['scanMode' => false, 'lookupStatus' => null, 'duplicate' => null]);
    }
}
