<?php

namespace App\Controller;

use App\Entity\RefreshToken;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController
{
    #[Route('/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        // Lire le JSON d'entrée: { "refreshToken": "..." }
        $data = json_decode($request->getContent() ?: '{}', true);
        $raw = is_array($data) ? ($data['refreshToken'] ?? null) : null;

        if (!$raw) {
            return new JsonResponse(['code' => 400, 'message' => 'Missing "refreshToken"'], 400);
        }

        /** @var RefreshToken|null $rt */
        $rt = $em->getRepository(RefreshToken::class)->findOneBy(['refreshToken' => $raw]);
        if (!$rt) {
            return new JsonResponse(['code' => 401, 'message' => 'Invalid refresh token'], 401);
        }

        // Vérifier expiration
        $now = new \DateTimeImmutable();
        if ($rt->getValid() < $now) {
            return new JsonResponse(['code' => 401, 'message' => 'Refresh token expired'], 401);
        }

        // Charger l'utilisateur depuis username (on a stocké l'email)
        $user = $users->findOneBy(['email' => $rt->getUsername()]);
        if (!$user) {
            return new JsonResponse(['code' => 401, 'message' => 'User not found'], 401);
        }

        // Ici tu peux vérifier est_actif si tu veux renforcer
        // if (method_exists($user, 'isEstActif') && !$user->isEstActif()) { ... }

        // Émettre un nouveau JWT
        $jwt = $jwtManager->create($user);

        // (Optionnel) rotation du refresh token: à implémenter plus tard si tu veux
        return new JsonResponse(['token' => $jwt]);
    }
}
