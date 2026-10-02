<?php

namespace App\Form;

use App\Entity\Book;
use App\Entity\Genre;
use App\Enum\BookCondition;
use App\Enum\BookFormat;
use App\Enum\Language;
use App\Service\CoverManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Buchformular: Titel und Autor sind Pflicht und stehen oben,
 * alles andere steckt im einklappbaren Bereich „Weitere Details“.
 * Alle Beschriftungen sind Übersetzungsschlüssel (translations/messages+intl-icu.*.yaml).
 */
class BookType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // --- Pflichtfelder ---
            ->add('title', TextType::class, [
                'label' => 'book.field.title',
                'attr' => ['autocapitalize' => 'sentences', 'enterkeyhint' => 'next'],
            ])
            ->add('authors', AuthorsType::class, [
                'label' => 'book.field.authors',
                'help' => 'book.field.authors_help',
                'attr' => ['autocapitalize' => 'words', 'autocomplete' => 'off', 'list' => 'author-suggestions'],
            ])

            // --- Cover ---
            ->add('coverFile', FileType::class, [
                'label' => 'book.field.cover',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'data-cover-preview-target' => 'input',
                    'data-action' => 'change->cover-preview#preview',
                ],
                'constraints' => [
                    new Assert\Image(
                        maxSize: CoverManager::MAX_UPLOAD_BYTES,
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        maxSizeMessage: 'cover.too_large',
                        mimeTypesMessage: 'cover.format',
                        uploadIniSizeErrorMessage: 'cover.too_large_simple',
                        uploadFormSizeErrorMessage: 'cover.too_large_simple',
                        uploadErrorMessage: 'cover.upload_failed',
                        corruptedMessage: 'cover.corrupted',
                        maxPixels: 40_000_000,
                        maxPixelsMessage: 'cover.too_many_pixels',
                    ),
                ],
            ])
            // --- Rückseite (optional) ---
            ->add('backFile', FileType::class, [
                'label' => 'book.field.back_cover',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                    'data-cover-preview-target' => 'input',
                    'data-action' => 'change->cover-preview#preview',
                ],
                'constraints' => [
                    new Assert\Image(
                        maxSize: CoverManager::MAX_UPLOAD_BYTES,
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        maxSizeMessage: 'cover.too_large',
                        mimeTypesMessage: 'cover.format',
                        uploadIniSizeErrorMessage: 'cover.too_large_simple',
                        uploadFormSizeErrorMessage: 'cover.too_large_simple',
                        uploadErrorMessage: 'cover.upload_failed',
                        corruptedMessage: 'cover.corrupted',
                        maxPixels: 40_000_000,
                        maxPixelsMessage: 'cover.too_many_pixels',
                    ),
                ],
            ])
            // Von Open Library vorgeschlagenes Cover – wird erst beim Speichern heruntergeladen
            ->add('coverUrl', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['data-cover-preview-target' => 'remoteUrl'],
            ])

            // --- Weitere Details ---
            ->add('subtitle', TextType::class, [
                'label' => 'book.field.subtitle',
                'required' => false,
            ])
            ->add('isbn', TextType::class, [
                'label' => 'book.field.isbn',
                'required' => false,
                'help' => 'book.field.isbn_help',
                'attr' => ['inputmode' => 'numeric', 'autocomplete' => 'off'],
            ])
            ->add('publisher', TextType::class, [
                'label' => 'book.field.publisher',
                'required' => false,
            ])
            ->add('publishedYear', IntegerType::class, [
                'label' => 'book.field.year',
                'required' => false,
                'invalid_message' => 'book.year.invalid',
                'attr' => ['inputmode' => 'numeric', 'min' => 1450, 'max' => 2100, 'placeholder' => 'book.field.year_placeholder'],
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'book.field.language',
                'required' => false,
                'placeholder' => 'book.field.no_value',
                'choices' => Language::choices($this->translator->getLocale()),
                'choice_translation_domain' => false,
                'preferred_choices' => ['de', 'fr', 'en'],
            ])
            ->add('pageCount', IntegerType::class, [
                'label' => 'book.field.pages',
                'required' => false,
                'invalid_message' => 'book.pages.invalid',
                'attr' => ['inputmode' => 'numeric', 'min' => 1],
            ])
            ->add('format', EnumType::class, [
                'label' => 'book.field.format',
                'class' => BookFormat::class,
                'choice_label' => static fn (BookFormat $f) => $f,
                'required' => false,
                'expanded' => true,
                'placeholder' => false,
            ])
            ->add('genres', EntityType::class, [
                'label' => 'book.field.genres',
                'class' => Genre::class,
                'choice_label' => 'name',
                'choice_translation_domain' => 'genres',
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('g')->orderBy('g.name', 'ASC'),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
            ])
            ->add('newGenre', TextType::class, [
                'label' => 'book.field.new_genre',
                'mapped' => false,
                'required' => false,
                'help' => 'book.field.new_genre_help',
                'constraints' => [new Assert\Length(max: 80, maxMessage: 'book.genre.too_long')],
            ])
            ->add('series', TextType::class, [
                'label' => 'book.field.series',
                'required' => false,
                'attr' => ['placeholder' => 'book.field.series_placeholder'],
            ])
            ->add('seriesNumber', IntegerType::class, [
                'label' => 'book.field.series_number',
                'required' => false,
                'invalid_message' => 'book.series_number.invalid',
                'attr' => ['inputmode' => 'numeric', 'min' => 1, 'placeholder' => 'book.field.series_number_placeholder'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'book.field.description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('condition', EnumType::class, [
                'label' => 'book.field.condition',
                'class' => BookCondition::class,
                'choice_label' => static fn (BookCondition $c) => $c,
                'required' => false,
                'expanded' => true,
                'placeholder' => false,
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'book.field.rating',
                'required' => false,
                'expanded' => true,
                'placeholder' => false,
                'choices' => ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5],
                'choice_translation_domain' => false,
            ])
            ->add('review', TextareaType::class, [
                'label' => 'book.field.review',
                'required' => false,
                'help' => 'book.field.review_help',
                'attr' => ['rows' => 3, 'maxlength' => 2000, 'placeholder' => 'book.field.review_placeholder'],
            ])
            ->add('awards', TextType::class, [
                'label' => 'book.field.awards',
                'required' => false,
                'attr' => ['maxlength' => 255, 'placeholder' => 'book.field.awards_placeholder'],
            ])
        ;

        if ($options['show_owner_note']) {
            $builder->add('ownerNote', TextareaType::class, [
                'label' => 'book.field.owner_note',
                'required' => false,
                'help' => 'book.field.owner_note_help',
                'attr' => ['rows' => 2],
            ]);
        }

        // Die Vorderseite ist Pflicht – entfernen lässt sich nur die Rückseite
        if ($options['allow_remove_back']) {
            $builder->add('removeBack', CheckboxType::class, [
                'label' => 'book.field.remove_back',
                'mapped' => false,
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
            'allow_remove_back' => false,
            // Admins, die ein fremdes Buch korrigieren, sehen die private Notiz nicht
            'show_owner_note' => true,
        ]);
    }
}
