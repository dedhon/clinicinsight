<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomeRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/home');

        self::assertResponseRedirects('/login');
    }
}
