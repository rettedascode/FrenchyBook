<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Einheitliche Zeitzone für Webseite und Cronjobs, unabhängig von der Server-Einstellung
        // (wichtig z. B. dafür, ab wann eine Ausleihe „überfällig“ ist). Änderbar per APP_TIMEZONE.
        date_default_timezone_set($_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? 'Europe/Berlin');

        parent::boot();
    }
}
