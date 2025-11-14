<?php

namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class JwtAuthenticationFailureSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
            Events::JWT_EXPIRED => 'onJwtExpired',
        ];
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $response = $event->getResponse();
        $exception = $event->getException();

        // Check if it's an expired token
        $message = $exception->getMessage();
        if (
            str_contains($message, 'expired') ||
            str_contains($message, 'Expired JWT Token') ||
            str_contains($message, 'JWT_EXPIRED')
        ) {
            $event->setResponse(new JsonResponse([
                'code' => 401,
                'message' => 'JWT_EXPIRED',
                'error' => 'Expired JWT Token'
            ], 401));
        } elseif ($response instanceof JsonResponse) {
            // Ensure all authentication failures return proper 401
            $data = json_decode($response->getContent(), true);
            if (!isset($data['code'])) {
                $event->setResponse(new JsonResponse([
                    'code' => 401,
                    'message' => $data['message'] ?? 'Authentication failed',
                    'error' => $data['message'] ?? 'Authentication failed'
                ], 401));
            }
        }
    }

    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(new JsonResponse([
            'code' => 401,
            'message' => 'JWT_EXPIRED',
            'error' => 'Expired JWT Token'
        ], 401));
    }
}

