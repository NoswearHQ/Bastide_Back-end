<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LogTestController
{
    #[Route('/log-test', name: 'log_test', methods: ['GET'])]
    public function test(LoggerInterface $logger): JsonResponse
    {
        $logger->info('✅ Test log depuis environnement PROD.');
        return new JsonResponse(['message' => '✅ Log écrit avec succès (prod)']);
    }
}
