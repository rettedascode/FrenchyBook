<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum BookFormat: string implements TranslatableInterface
{
    case Paperback = 'paperback';
    case Hardcover = 'hardcover';
    case Ebook = 'ebook';
    case Audiobook = 'audiobook';
    case Comic = 'comic';

    /** Übersetzte Bezeichnung, z. B. in Twig: {{ book.format|trans }} */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.book_format.'.$this->value, [], 'messages', $locale);
    }
}
