<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Die neun Kölner Stadtbezirke – zeigt, wo man ein Buch ungefähr abholen kann
 * bzw. wo es sich gerade befindet.
 */
enum District: string implements TranslatableInterface
{
    case Innenstadt = 'innenstadt';
    case Rodenkirchen = 'rodenkirchen';
    case Lindenthal = 'lindenthal';
    case Ehrenfeld = 'ehrenfeld';
    case Nippes = 'nippes';
    case Chorweiler = 'chorweiler';
    case Porz = 'porz';
    case Kalk = 'kalk';
    case Muelheim = 'muelheim';

    /** {{ user.district|trans }} */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.district.'.$this->value, [], 'messages', $locale);
    }
}
