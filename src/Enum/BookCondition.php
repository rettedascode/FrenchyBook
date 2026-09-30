<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum BookCondition: string implements TranslatableInterface
{
    case New = 'new';
    case Good = 'good';
    case Used = 'used';
    case Damaged = 'damaged';

    /** Übersetzte Bezeichnung, z. B. in Twig: {{ book.format|trans }} */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.book_condition.'.$this->value, [], 'messages', $locale);
    }
}
