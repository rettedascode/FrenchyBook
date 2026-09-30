<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum LoanRequestStatus: string implements TranslatableInterface
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Declined = 'declined';

    /** Übersetzte Bezeichnung, z. B. in Twig: {{ book.format|trans }} */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.request_status.'.$this->value, [], 'messages', $locale);
    }
}
