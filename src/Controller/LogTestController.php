<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LogTestController extends AbstractController
{
    #[Route('/log-test', name: 'log_test')]
    public function index(LoggerInterface $logger): Response
    {
        $logger->info('✅ Test log depuis environnement PROD.');
        return new Response('Log écrit dans prod.log');
    }
}
