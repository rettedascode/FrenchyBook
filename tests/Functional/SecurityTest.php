<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\AppWebTestCase;

final class SecurityTest extends AppWebTestCase
{
    public function testAnonymousUsersAreRedirectedToLogin(): void
    {
        foreach (['/', '/buecher', '/buecher/neu', '/profil'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/anmelden', message: $url);
        }
    }

    public function testLoginPageIsGermanAndHasRememberMeChecked(): void
    {
        $crawler = $this->client->request('GET', '/anmelden');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Willkommen zurück');
        self::assertSame('checked', $crawler->filter('input[name="_remember_me"]')->attr('checked'));
    }

    public function testLoginWithWrongPasswordShowsFriendlyMessage(): void
    {
        $this->createUser('Lena');
        $this->client->request('GET', '/anmelden');
        $this->client->submitForm('Anmelden', ['email' => 'lena@example.com', 'password' => 'falsch']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.notice-danger', 'E-Mail-Adresse oder Passwort stimmen nicht');
    }

    public function testLoginLeadsToDashboardAndSetsRememberMeCookie(): void
    {
        $this->createUser('Lena');
        $this->client->request('GET', '/anmelden');
        $this->client->submitForm('Anmelden', ['email' => 'lena@example.com', 'password' => self::PASSWORD]);

        self::assertResponseRedirects('/');
        self::assertResponseHasCookie('REMEMBERME');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Hallo Lena');
    }

    public function testRegistrationCreatesUserAndLogsIn(): void
    {
        $this->client->request('GET', '/registrieren');
        $this->client->submitForm('Konto anlegen', [
            'registration_form[name]' => 'Max',
            'registration_form[email]' => 'Max@Example.com ',
            'registration_form[plainPassword]' => 'supergeheim',
        ]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Willkommen bei FrenchyBook, Max!');

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'max@example.com']);
        self::assertNotNull($user, 'E-Mail wird kleingeschrieben gespeichert');
        self::assertNotSame('supergeheim', $user->getPassword(), 'Passwort wird gehasht');
    }

    public function testRegistrationValidatesInGerman(): void
    {
        $this->createUser('Lena');
        $this->client->request('GET', '/registrieren');
        $this->client->submitForm('Konto anlegen', [
            'registration_form[name]' => '',
            'registration_form[email]' => 'lena@example.com',
            'registration_form[plainPassword]' => 'kurz',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Bitte gib einen Namen ein');
        self::assertSelectorTextContains('body', 'Mit dieser E-Mail-Adresse ist schon jemand registriert.');
        self::assertSelectorTextContains('body', 'mindestens 8 Zeichen');
    }

    public function testRegistrationRequiresInviteCodeWhenConfigured(): void
    {
        $_ENV['INVITE_CODE'] = $_SERVER['INVITE_CODE'] = 'Bücherwurm';
        try {
            $this->client->request('GET', '/registrieren');
            self::assertSelectorExists('input[name="registration_form[inviteCode]"]');

            $this->client->submitForm('Konto anlegen', [
                'registration_form[name]' => 'Tom',
                'registration_form[email]' => 'tom@example.com',
                'registration_form[plainPassword]' => 'supergeheim',
                'registration_form[inviteCode]' => 'falsch',
            ]);
            self::assertSelectorTextContains('body', 'Dieser Einladungscode stimmt leider nicht.');

            $this->client->submitForm('Konto anlegen', [
                'registration_form[name]' => 'Tom',
                'registration_form[email]' => 'tom@example.com',
                'registration_form[plainPassword]' => 'supergeheim',
                'registration_form[inviteCode]' => ' bücherwurm ',
            ]);
            self::assertResponseRedirects('/');
        } finally {
            $_ENV['INVITE_CODE'] = $_SERVER['INVITE_CODE'] = '';
        }
    }

    public function testLogout(): void
    {
        $this->client->loginUser($this->createUser('Lena'));
        $crawler = $this->client->request('GET', '/profil');
        $this->client->click($crawler->selectLink('Abmelden')->link());

        self::assertResponseRedirects('/anmelden');
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/anmelden');
    }
}
