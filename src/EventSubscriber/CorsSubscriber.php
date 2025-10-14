<?php
// src/EventSubscriber/CorsSubscriber.php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriber implements EventSubscriberInterface
{
    // Autorise uniquement ces origines (ajoute/retire selon ton cas)
    private array $allowedOrigins = [
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 1024], // avant les contrôleurs
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $req = $event->getRequest();
        if ($req->getMethod() !== 'OPTIONS') return;

        $origin = $req->headers->get('Origin');
        if (!$origin || !in_array($origin, $this->allowedOrigins, true)) return;

        $resp = new Response();
        $this->setCorsHeaders($resp, $origin);
        $resp->setStatusCode(Response::HTTP_NO_CONTENT); // 204
        $event->setResponse($resp);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $req  = $event->getRequest();
        $resp = $event->getResponse();

        $origin = $req->headers->get('Origin');
        if (!$origin || !in_array($origin, $this->allowedOrigins, true)) return;

        $this->setCorsHeaders($resp, $origin);
    }

    private function setCorsHeaders(Response $resp, string $origin): void
    {
        $resp->headers->set('Access-Control-Allow-Origin', $origin);
        $resp->headers->set('Vary', 'Origin'); // important quand plusieurs origines
        // Si tu n’utilises pas de cookies, tu peux laisser à false. Ici on met true au cas où.
        $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        $resp->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $resp->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS');
        $resp->headers->set('Access-Control-Max-Age', '86400');
        // Optionnel: expose certains headers utiles
        $resp->headers->set('Access-Control-Expose-Headers', 'Location, Content-Disposition');
    }
}
