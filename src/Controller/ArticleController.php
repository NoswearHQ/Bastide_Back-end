<?php

namespace App\Controller;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;

#[Route('/crud/articles')]
class ArticleController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        [$page, $limit] = $this->paginateParams($request);

        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('e')
            ->select("
            e.id               AS id,
            e.titre            AS titre,
            e.slug             AS slug,
            e.extrait          AS extrait,
            e.image_miniature  AS image_miniature,
            e.seo_titre        AS seo_titre,
            e.seo_description  AS seo_description,
            e.publie_le        AS publie_le,
            e.cree_le          AS cree_le,
            e.modifie_le       AS modifie_le
        ");

        // Tri sécurisé : publie_le DESC puis cree_le DESC par défaut
        $allowed = ['publie_le','cree_le','titre','slug','id'];
        $order   = $request->query->get('order'); // ex: "publie_le:desc"
        $this->applySafeOrdering($qb, $order, $allowed, 'publie_le', 'DESC');
        $qb->addOrderBy('e.cree_le', 'DESC');

        $search = trim((string)$request->query->get('search', ''));
        if ($search !== '') {
            $qb->andWhere('LOWER(e.titre) LIKE :s OR LOWER(e.seo_description) LIKE :s OR LOWER(e.extrait) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        // ⚠️ IMPORTANT : nettoyer le clone pour le COUNT
        $qbCount = clone $qb;
        $qbCount->resetDQLPart('orderBy');
        $qbCount->resetDQLPart('groupBy');
        $qbCount->resetDQLPart('having');
        $qbCount->resetDQLPart('select')->select('COUNT(e.id)');

        $total = (int)$qbCount->getQuery()->getSingleScalarResult();

        $rows = $qb->setFirstResult(($page - 1)*$limit)
            ->setMaxResults($limit)
            ->getQuery()->getResult(Query::HYDRATE_ARRAY);

        return new JsonResponse([
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'rows'  => $rows,
        ], 200);
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $qb
     * @param string|null $orderParam ex: "publie_le:desc"
     * @param array<string> $allowed
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

        $qb->addOrderBy('e.' . $field, $dir);
    }


    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('e')
            ->select("
                e.id               AS id,
                e.titre            AS titre,
                e.slug             AS slug,
                e.extrait          AS extrait,
                e.image_miniature  AS image_miniature,
                e.seo_titre        AS seo_titre,
                e.seo_description  AS seo_description,
                e.publie_le        AS publie_le,
                e.contenu_html     AS contenu_html,
                e.cree_le          AS cree_le,
                e.modifie_le       AS modifie_le
            ")
            ->andWhere('e.id = :id')->setParameter('id', $id)
            ->setMaxResults(1);

        $row = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);
        if (!$row) return $this->error('Not found', 404);
        return new JsonResponse($row, 200);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->jsonBody($request);
        if ($data === null) return $this->error('Invalid JSON', 400);

        $missing = [];
        foreach (['titre','slug','contenu_html'] as $f) {
            if (!isset($data[$f]) || $data[$f] === '') $missing[] = $f;
        }
        if ($missing) return $this->error('Missing: '.implode(',', $missing), 400);

        $e = new Article();

        $this->setIfExists($e, 'setTitre', $data, 'titre');
        $this->setIfExists($e, 'setSlug', $data, 'slug');
        $this->setIfExists($e, 'setNomAuteur', $data, 'nom_auteur');
        $this->setIfExists($e, 'setImageMiniature', $data, 'image_miniature');
        $this->setIfExists($e, 'setExtrait', $data, 'extrait');
        $this->setIfExists($e, 'setContenuHtml', $data, 'contenu_html');
        $this->setIfExists($e, 'setSeoTitre', $data, 'seo_titre');
        $this->setIfExists($e, 'setSeoDescription', $data, 'seo_description');
        $this->setIfExists($e, 'setGalerieJson', $data, 'galerie_json');
        $this->setIfExists($e, 'setStatut', $data, 'statut');

        if (array_key_exists('publie_le', $data) && method_exists($e, 'setPublieLe')) {
            $e->setPublieLe($this->parseDateTime($data['publie_le']));
        }

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
        $e = $this->em->getRepository(Article::class)->find($id);
        if (!$e) return $this->error('Not found', 404);

        $data = $this->jsonBody($request);
        if ($data === null) return $this->error('Invalid JSON', 400);

        $this->setIfExists($e, 'setTitre', $data, 'titre');
        $this->setIfExists($e, 'setSlug', $data, 'slug');
        $this->setIfExists($e, 'setNomAuteur', $data, 'nom_auteur');
        $this->setIfExists($e, 'setImageMiniature', $data, 'image_miniature');
        $this->setIfExists($e, 'setExtrait', $data, 'extrait');
        $this->setIfExists($e, 'setContenuHtml', $data, 'contenu_html');
        $this->setIfExists($e, 'setSeoTitre', $data, 'seo_titre');
        $this->setIfExists($e, 'setSeoDescription', $data, 'seo_description');
        $this->setIfExists($e, 'setGalerieJson', $data, 'galerie_json');
        $this->setIfExists($e, 'setStatut', $data, 'statut');

        if (array_key_exists('publie_le', $data) && method_exists($e, 'setPublieLe')) {
            $e->setPublieLe($this->parseDateTime($data['publie_le']));
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
        $e = $this->em->getRepository(Article::class)->find($id);
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

    private function parseDateTime(null|string $s): ?\DateTimeInterface
    {
        if (!$s) return null;
        try { return new \DateTimeImmutable($s); } catch (\Throwable) { return null; }
    }

    private function setIfExists(object $obj, string $setter, array $data, string $key): void
    {
        if (array_key_exists($key, $data) && method_exists($obj, $setter)) {
            $obj->{$setter}($data[$key]);
        }
    }

}
