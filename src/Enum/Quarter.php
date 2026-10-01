<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Die 86 Kölner Stadtteile mit ihrer offiziellen Nummer (erste Ziffer = Stadtbezirk).
 * Quelle: https://de.wikipedia.org/wiki/Liste_der_Stadtbezirke_und_Stadtteile_Kölns
 *
 * Stadtteilnamen sind Eigennamen und werden nicht übersetzt.
 */
enum Quarter: int implements TranslatableInterface
{
    case AltstadtSued = 101;
    case NeustadtSued = 102;
    case AltstadtNord = 103;
    case NeustadtNord = 104;
    case Deutz = 105;
    case Bayenthal = 201;
    case Marienburg = 202;
    case Raderberg = 203;
    case Raderthal = 204;
    case Zollstock = 205;
    case Rondorf = 206;
    case Hahnwald = 207;
    case Rodenkirchen = 208;
    case Weiss = 209;
    case Suerth = 210;
    case Godorf = 211;
    case Immendorf = 212;
    case Meschenich = 213;
    case Klettenberg = 301;
    case Suelz = 302;
    case Lindenthal = 303;
    case Braunsfeld = 304;
    case Muengersdorf = 305;
    case Junkersdorf = 306;
    case Weiden = 307;
    case Loevenich = 308;
    case Widdersdorf = 309;
    case Ehrenfeld = 401;
    case Neuehrenfeld = 402;
    case Bickendorf = 403;
    case Vogelsang = 404;
    case BocklemuendMengenich = 405;
    case Ossendorf = 406;
    case Nippes = 501;
    case Mauenheim = 502;
    case Riehl = 503;
    case Niehl = 504;
    case Weidenpesch = 505;
    case Longerich = 506;
    case Bilderstoeckchen = 507;
    case Merkenich = 601;
    case Fuehlingen = 602;
    case Seeberg = 603;
    case Heimersdorf = 604;
    case Lindweiler = 605;
    case Pesch = 606;
    case EschAuweiler = 607;
    case VolkhovenWeiler = 608;
    case Chorweiler = 609;
    case Blumenberg = 610;
    case RoggendorfThenhoven = 611;
    case Worringen = 612;
    case Poll = 701;
    case Westhoven = 702;
    case Ensen = 703;
    case Gremberghoven = 704;
    case Eil = 705;
    case Porz = 706;
    case Urbach = 707;
    case Elsdorf = 708;
    case Grengel = 709;
    case Wahnheide = 710;
    case Wahn = 711;
    case Lind = 712;
    case Libur = 713;
    case Zuendorf = 714;
    case Langel = 715;
    case Finkenberg = 716;
    case HumboldtGremberg = 801;
    case Kalk = 802;
    case Vingst = 803;
    case Hoehenberg = 804;
    case Ostheim = 805;
    case Merheim = 806;
    case Brueck = 807;
    case RathHeumar = 808;
    case Neubrueck = 809;
    case Muelheim = 901;
    case Buchforst = 902;
    case Buchheim = 903;
    case Holweide = 904;
    case Dellbrueck = 905;
    case Hoehenhaus = 906;
    case Duennwald = 907;
    case Stammheim = 908;
    case Flittard = 909;

    public function label(): string
    {
        return match ($this) {
            self::AltstadtSued => 'Altstadt-Süd',
            self::NeustadtSued => 'Neustadt-Süd',
            self::AltstadtNord => 'Altstadt-Nord',
            self::NeustadtNord => 'Neustadt-Nord',
            self::Deutz => 'Deutz',
            self::Bayenthal => 'Bayenthal',
            self::Marienburg => 'Marienburg',
            self::Raderberg => 'Raderberg',
            self::Raderthal => 'Raderthal',
            self::Zollstock => 'Zollstock',
            self::Rondorf => 'Rondorf',
            self::Hahnwald => 'Hahnwald',
            self::Rodenkirchen => 'Rodenkirchen',
            self::Weiss => 'Weiß',
            self::Suerth => 'Sürth',
            self::Godorf => 'Godorf',
            self::Immendorf => 'Immendorf',
            self::Meschenich => 'Meschenich',
            self::Klettenberg => 'Klettenberg',
            self::Suelz => 'Sülz',
            self::Lindenthal => 'Lindenthal',
            self::Braunsfeld => 'Braunsfeld',
            self::Muengersdorf => 'Müngersdorf',
            self::Junkersdorf => 'Junkersdorf',
            self::Weiden => 'Weiden',
            self::Loevenich => 'Lövenich',
            self::Widdersdorf => 'Widdersdorf',
            self::Ehrenfeld => 'Ehrenfeld',
            self::Neuehrenfeld => 'Neuehrenfeld',
            self::Bickendorf => 'Bickendorf',
            self::Vogelsang => 'Vogelsang',
            self::BocklemuendMengenich => 'Bocklemünd/Mengenich',
            self::Ossendorf => 'Ossendorf',
            self::Nippes => 'Nippes',
            self::Mauenheim => 'Mauenheim',
            self::Riehl => 'Riehl',
            self::Niehl => 'Niehl',
            self::Weidenpesch => 'Weidenpesch',
            self::Longerich => 'Longerich',
            self::Bilderstoeckchen => 'Bilderstöckchen',
            self::Merkenich => 'Merkenich',
            self::Fuehlingen => 'Fühlingen',
            self::Seeberg => 'Seeberg',
            self::Heimersdorf => 'Heimersdorf',
            self::Lindweiler => 'Lindweiler',
            self::Pesch => 'Pesch',
            self::EschAuweiler => 'Esch/Auweiler',
            self::VolkhovenWeiler => 'Volkhoven/Weiler',
            self::Chorweiler => 'Chorweiler',
            self::Blumenberg => 'Blumenberg',
            self::RoggendorfThenhoven => 'Roggendorf/Thenhoven',
            self::Worringen => 'Worringen',
            self::Poll => 'Poll',
            self::Westhoven => 'Westhoven',
            self::Ensen => 'Ensen',
            self::Gremberghoven => 'Gremberghoven',
            self::Eil => 'Eil',
            self::Porz => 'Porz',
            self::Urbach => 'Urbach',
            self::Elsdorf => 'Elsdorf',
            self::Grengel => 'Grengel',
            self::Wahnheide => 'Wahnheide',
            self::Wahn => 'Wahn',
            self::Lind => 'Lind',
            self::Libur => 'Libur',
            self::Zuendorf => 'Zündorf',
            self::Langel => 'Langel',
            self::Finkenberg => 'Finkenberg',
            self::HumboldtGremberg => 'Humboldt/Gremberg',
            self::Kalk => 'Kalk',
            self::Vingst => 'Vingst',
            self::Hoehenberg => 'Höhenberg',
            self::Ostheim => 'Ostheim',
            self::Merheim => 'Merheim',
            self::Brueck => 'Brück',
            self::RathHeumar => 'Rath/Heumar',
            self::Neubrueck => 'Neubrück',
            self::Muelheim => 'Mülheim',
            self::Buchforst => 'Buchforst',
            self::Buchheim => 'Buchheim',
            self::Holweide => 'Holweide',
            self::Dellbrueck => 'Dellbrück',
            self::Hoehenhaus => 'Höhenhaus',
            self::Duennwald => 'Dünnwald',
            self::Stammheim => 'Stammheim',
            self::Flittard => 'Flittard',
        };
    }

    /** Der Stadtbezirk, zu dem der Stadtteil gehört (101 → 1 Innenstadt) */
    public function district(): District
    {
        return District::cases()[intdiv($this->value, 100) - 1];
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->label();
    }
}
