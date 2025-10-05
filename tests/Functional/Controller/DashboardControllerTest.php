<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class DashboardControllerTest extends WebTestCase
{
    private function createAuthenticatedClient(User $user = null): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        
        if ($user === null) {
            $user = new User();
            $user->setEmail('test@example.com');
            $user->setPassword('password');
            $user->setRoles(['ROLE_USER']);
            $user->setQuotaTotalBytes(10737418240); // 10GB
            $user->setQuotaUsedBytes(0);
        }

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $client->getContainer()->get('security.token_storage')->setToken($token);

        return $client;
    }

    public function testDashboardAccess(): void
    {
        $client = $this->createAuthenticatedClient();
        
        $client->request('GET', '/dashboard');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Dashboard');
    }

    public function testDashboardRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/dashboard');
        
        $this->assertResponseRedirects('/login');
    }

    public function testUploadAccessWithUploaderRole(): void
    {
        $user = new User();
        $user->setEmail('uploader@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_UPLOADER']);
        $user->setQuotaTotalBytes(10737418240);
        $user->setQuotaUsedBytes(0);

        $client = $this->createAuthenticatedClient($user);
        
        $client->request('GET', '/upload');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Upload de fichier');
    }

    public function testUploadAccessDeniedWithoutUploaderRole(): void
    {
        $user = new User();
        $user->setEmail('viewer@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_VIEWER']); // Only viewer role, no uploader
        $user->setQuotaTotalBytes(10737418240);
        $user->setQuotaUsedBytes(0);

        $client = $this->createAuthenticatedClient($user);
        
        $client->request('GET', '/upload');
        
        $this->assertResponseStatusCodeSame(403);
    }

    public function testFilesListAccess(): void
    {
        $client = $this->createAuthenticatedClient();
        
        $client->request('GET', '/files');
        
        $this->assertResponseIsSuccessful();
    }

    public function testProfileAccess(): void
    {
        $client = $this->createAuthenticatedClient();
        
        $client->request('GET', '/profile');
        
        $this->assertResponseIsSuccessful();
    }
}
