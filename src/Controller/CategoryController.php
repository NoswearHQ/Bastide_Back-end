<?php

namespace App\Controller;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;

#[Route('/crud/categories')]
class CategoryController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        [$page, $limit] = $this->paginateParams($request);

        $qb = $this->em->getRepository(Category::class)->createQueryBuilder('e')
            ->select("
                e.id        AS id,
                e.nom       AS nom,
                e.slug      AS slug,
                e.position  AS position,
                e.est_active AS est_active,
                e.cree_le   AS cree_le,
                e.modifie_le AS modifie_le,
                IDENTITY(e.parent) AS parent_id
            ")
            ->orderBy('e.parent', 'ASC')
            ->addOrderBy('e.nom', 'ASC');
        $allowed = ['nom','position','id'];
        $order   = $request->query->get('order');
        $this->applySafeOrdering($qb, $order, $allowed, 'nom', 'ASC');
// si tu veux garder le tri par parent en priorité :
        $qb->addOrderBy('e.parent', 'ASC');
        $search = trim((string)$request->query->get('search', ''));
        if ($search !== '') {
            $qb->andWhere('LOWER(e.nom) LIKE :s OR LOWER(e.slug) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        $qbCount = clone $qb;
        $qbCount->resetDQLPart('select')->select('COUNT(e.id)');

        $total = (int)$qbCount->getQuery()->getSingleScalarResult();

        $rows = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult(Query::HYDRATE_ARRAY);

        return new JsonResponse([
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'rows'  => $rows,
        ], 200);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $qb = $this->em->getRepository(Category::class)->createQueryBuilder('e')
            ->select("
                e.id        AS id,
                e.nom       AS nom,
                e.slug      AS slug,
                e.position  AS position,
                e.est_active AS est_active,
                e.cree_le   AS cree_le,
                e.modifie_le AS modifie_le,
                IDENTITY(e.parent) AS parent_id
            ")
            ->andWhere('e.id = :id')->setParameter('id', $id)
            ->setMaxResults(1);

        $row = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);
        if (!$row) {
            return $this->error('Not found', 404);
        }
        return new JsonResponse($row, 200);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->jsonBody($request);
        if ($data === null) {
            return $this->error('Invalid JSON', 400);
        }

        $missing = [];
        foreach (['nom','slug'] as $f) {
            if (!isset($data[$f]) || $data[$f] === '') $missing[] = $f;
        }
        if ($missing) {
            return $this->error('Missing: '.implode(',', $missing), 400);
        }

        $e = new Category();

        // Champs simples
        $this->setIfExists($e, 'setNom', $data, 'nom');
        $this->setIfExists($e, 'setSlug', $data, 'slug');
        $this->setIfExists($e, 'setPosition', $data, 'position');
        $this->setIfExists($e, 'setEstActive', $data, 'est_active');

        // Relation parent (optionnelle)
        if (array_key_exists('parent_id', $data)) {
            $parent = null;
            if ($data['parent_id']) {
                $parent = $this->em->getRepository(Category::class)->find((int)$data['parent_id']);
                if (!$parent) {
                    return $this->error('Not found', 404);
                }
            }
            if (method_exists($e, 'setParent')) {
                $e->setParent($parent);
            }
        }

        // Timestamps
        $now = new \DateTimeImmutable();
        if (method_exists($e, 'setCreeLe') && null === ($e->getCreeLe() ?? null)) $e->setCreeLe($now);
        if (method_exists($e, 'setModifieLe')) $e->setModifieLe($now);

        try {
            $this->em->persist($e);
            $this->em->flush();
        } catch (\Throwable $ex) {
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(['id' => $e->getId()], 201);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['PATCH'])]
    public function patch(int $id, Request $request): JsonResponse
    {
        $e = $this->em->getRepository(Category::class)->find($id);
        if (!$e) return $this->error('Not found', 404);

        $data = $this->jsonBody($request);
        if ($data === null) {
            return $this->error('Invalid JSON', 400);
        }

        $this->setIfExists($e, 'setNom', $data, 'nom');
        $this->setIfExists($e, 'setSlug', $data, 'slug');
        $this->setIfExists($e, 'setPosition', $data, 'position');
        $this->setIfExists($e, 'setEstActive', $data, 'est_active');

        if (array_key_exists('parent_id', $data)) {
            $parent = null;
            if ($data['parent_id']) {
                $parent = $this->em->getRepository(Category::class)->find((int)$data['parent_id']);
                if (!$parent) return $this->error('Not found', 404);
            }
            if (method_exists($e, 'setParent')) {
                $e->setParent($parent);
            }
        }

        if (method_exists($e, 'setModifieLe')) $e->setModifieLe(new \DateTimeImmutable());

        try {
            $this->em->flush();
        } catch (\Throwable $ex) {
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(['ok' => true], 200);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $e = $this->em->getRepository(Category::class)->find($id);
        if (!$e) return $this->error('Not found', 404);

        try {
            $this->em->remove($e);
            $this->em->flush();
        } catch (\Throwable $ex) {
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(null, 204);
    }

    // ===== Helpers =====

    private function paginateParams(Request $r): array
    {
        $page  = max(1, (int)$r->query->get('page', 1));
        $limit = (int)$r->query->get('limit', 50);
        $limit = max(1, min(200, $limit));
        return [$page, $limit];
    }

    private function error(string $msg, int $code): JsonResponse
    {
        return new JsonResponse(['error' => $msg], $code);
    }

    private function jsonBody(Request $r): ?array
    {
        $raw = $r->getContent();
        if ($raw === '' || $raw === null) return [];
        $data = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    private function setIfExists(object $obj, string $setter, array $data, string $key): void
    {
        if (array_key_exists($key, $data) && method_exists($obj, $setter)) {
            $obj->{$setter}($data[$key]);
        }
    }
    /**
     * @param \Doctrine\ORM\QueryBuilder $qb
     * @param string|null $orderParam ex: "prix:asc" ou "titre:desc"
     * @param array<string> $allowed ex: ['titre','publie_le','cree_le']
     */
    private function applySafeOrdering($qb, ?string $orderParam, array $allowed, string $defaultField, string $defaultDir = 'ASC'): void
    {
        $field = $defaultField;
        $dir   = $defaultDir;

        if ($orderParam) {
            $parts = explode(':', $orderParam, 2);
            $candidateField = $parts[0] ?? '';
            $candidateDir   = strtoupper($parts[1] ?? 'ASC');

            if (in_array($candidateField, $allowed, true)) {
                $field = $candidateField;
            }
            if (in_array($candidateDir, ['ASC','DESC'], true)) {
                $dir = $candidateDir;
            }
        }

        // Toujours préfixer par alias "e."
        $qb->addOrderBy('e.' . $field, $dir);
    }

}
