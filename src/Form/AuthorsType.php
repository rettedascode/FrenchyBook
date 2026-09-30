<?php

namespace App\Form;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Ein einfaches Textfeld für einen oder mehrere Autoren („Anne Frank, Mirjam Pressler“).
 * Bekannte Autoren werden wiederverwendet, neue automatisch angelegt.
 */
class AuthorsType extends AbstractType
{
    public function __construct(private readonly AuthorRepository $authors)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static function (iterable|null $authors): string {
                $names = [];
                foreach ($authors ?? [] as $author) {
                    $names[] = $author instanceof Author ? $author->getName() : (string) $author;
                }

                return implode(', ', $names);
            },
            fn (?string $value): array => $this->authors->findOrCreateByNames(self::split($value)),
        ));
    }

    /** @return list<string> */
    public static function split(?string $value): array
    {
        $parts = preg_split('/\s*[,;\n]\s*|\s+(?:und|and|&)\s+/u', trim((string) $value)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $s) => '' !== $s));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'empty_data' => '',
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
