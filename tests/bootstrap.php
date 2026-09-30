<?php

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Frische Test-Datenbank (SQLite-Datei var/data_test.db) mit aktuellem Schema anlegen.
// Die einzelnen Tests laufen dank DAMA\DoctrineTestBundle in zurückgerollten Transaktionen.
$kernel = new Kernel('test', true);
$kernel->boot();
/** @var EntityManagerInterface $em */
$em = $kernel->getContainer()->get('doctrine')->getManager();
$schemaTool = new SchemaTool($em);
$metadata = $em->getMetadataFactory()->getAllMetadata();
$schemaTool->dropSchema($metadata);
$schemaTool->createSchema($metadata);
(new Filesystem())->remove(dirname(__DIR__).'/public/uploads/test-covers');
$kernel->shutdown();
