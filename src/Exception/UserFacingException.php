<?php

namespace App\Exception;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * Fehler, der Nutzern angezeigt wird. Die Meldung ist ein Übersetzungsschlüssel
 * (translations/messages+intl-icu.*.yaml), damit sie in jeder Sprache passt.
 */
abstract class UserFacingException extends \RuntimeException
{
    /** @param array<string, mixed> $parameters */
    public function __construct(string $translationKey, private readonly array $parameters = [], ?\Throwable $previous = null)
    {
        parent::__construct($translationKey, 0, $previous);
    }

    public function toMessage(): TranslatableMessage
    {
        return new TranslatableMessage($this->getMessage(), $this->parameters);
    }
}
