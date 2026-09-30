<?php

namespace App\Exception;

/**
 * Open Library war nicht erreichbar (Timeout, Netzwerkfehler, Serverfehler).
 * „Kein Treffer“ ist dagegen kein Fehler, sondern ein null-Ergebnis.
 */
final class OpenLibraryException extends \RuntimeException
{
}
