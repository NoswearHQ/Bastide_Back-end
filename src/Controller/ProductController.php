<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;

#[Route('/crud/products')]
class ProductController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {

        [$page, $limit] = $this->paginateParams($request);

        $qb = $this->em->getRepository(Product::class)->createQueryBuilder('e')
            ->select("
                e.id                 AS id,
                e.titre              AS titre,
                e.slug               AS slug,
                e.prix               AS prix,
                e.devise             AS devise,
                e.image_miniature    AS image_miniature,
                e.galerie_json       AS galerie_json,
                e.description_html    AS seo_description,
                e.cree_le            AS cree_le,
                e.modifie_le         AS modifie_le,
                IDENTITY(e.categorie)      AS categorie_id,
                IDENTITY(e.sous_categorie) AS sous_categorie_id
            ");

        // Tri sécurisé (whitelist). Par défaut: titre ASC
        $allowed = ['titre','prix','cree_le','modifie_le','id'];
        $order   = $request->query->get('order'); // ex: "prix:asc"
        $this->applySafeOrdering($qb, $order, $allowed, 'titre', 'ASC');

        // Filtres
        $search = trim((string)$request->query->get('search', ''));
        if ($search !== '') {
            $qb->andWhere('LOWER(e.titre) LIKE :s OR LOWER(e.seo_description) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        if ($request->query->has('categoryId')) {
            $qb->andWhere('IDENTITY(e.categorie) = :cid')
                ->setParameter('cid', (int)$request->query->get('categoryId'));
        }
        if ($request->query->has('subCategoryId')) {
            $qb->andWhere('IDENTITY(e.sous_categorie) = :scid')
                ->setParameter('scid', (int)$request->query->get('subCategoryId'));
        }

        // Count propre (pas d'ORDER BY dans le COUNT)
        $qbCount = clone $qb;
        $qbCount->resetDQLPart('orderBy');
        $qbCount->resetDQLPart('groupBy');
        $qbCount->resetDQLPart('having');
        $qbCount->resetDQLPart('select')->select('COUNT(e.id)');
        $total = (int)$qbCount->getQuery()->getSingleScalarResult();

        $rows = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()->getResult(Query::HYDRATE_ARRAY);

        return new JsonResponse([
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'rows'  => $rows,
        ], 200);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $qb = $this->em->getRepository(Product::class)->createQueryBuilder('e')
            ->leftJoin('e.categorie', 'c')
            ->leftJoin('e.sous_categorie', 'sc')
            ->select("
            e.id                 AS id,
            e.titre              AS titre,
            e.slug               AS slug,
            e.prix               AS prix,
            e.devise             AS devise,
            e.image_miniature    AS image_miniature,
            e.galerie_json       AS galerie_json,
            e.seo_description    AS seo_description,
            e.seo_titre          AS seo_titre,
            e.description_courte AS description_courte,
            e.description_html   AS description_html,
            IDENTITY(e.categorie)      AS categorie_id,
            IDENTITY(e.sous_categorie) AS sous_categorie_id,
            c.nom                AS categorie_nom,
            sc.nom               AS sous_categorie_nom
        ")
            ->andWhere('e.id = :id')->setParameter('id', $id)
            ->setMaxResults(1);

        $row = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);
        if (!$row) {
            return new JsonResponse(['message' => 'Not found'], 404);
        }
        return new JsonResponse($row, 200);
    }


    #[IsGranted('ROLE_ADMIN')]
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->jsonBody($request);
        if ($data === null) return $this->error('Invalid JSON', 400);

        $missing = [];
        foreach (['titre','slug','categorie_id','sous_categorie_id'] as $f) {
            if (!isset($data[$f]) || $data[$f] === '') $missing[] = $f;
        }
        if ($missing) return $this->error('Missing: '.implode(',', $missing), 400);

        // Vérif catégories
        $cat = $this->em->getRepository(Category::class)->find((int)$data['categorie_id']);
        if (!$cat) return $this->error('Not found', 404);

        $sub = $this->em->getRepository(Category::class)->find((int)$data['sous_categorie_id']);
        if (!$sub) return $this->error('Not found', 404);

        $e = new Product();

        // Textes
        $this->setIfExists($e, 'setTitre', $data, 'titre');
        $this->setIfExists($e, 'setSlug', $data, 'slug');
        $this->setIfExists($e, 'setSku', $data, 'sku');
        $this->setIfExists($e, 'setMarque', $data, 'marque');
        $this->setIfExists($e, 'setDescriptionCourte', $data, 'description_courte');
        $this->setIfExists($e, 'setDescriptionHtml', $data, 'description_html');
        $this->setIfExists($e, 'setSeoTitre', $data, 'seo_titre');
        $this->setIfExists($e, 'setSeoDescription', $data, 'seo_description');

        // Images / JSON
        $this->setIfExists($e, 'setImageMiniature', $data, 'image_miniature');
        $this->setIfExists($e, 'setGalerieJson', $data, 'galerie_json');

        // Prix / devise
        if (array_key_exists('prix', $data) && method_exists($e, 'setPrix')) $e->setPrix($data['prix']);
        $this->setIfExists($e, 'setDevise', $data, 'devise');

        // Etats / dates
        $this->setIfExists($e, 'setEstActif', $data, 'est_actif');
        if (array_key_exists('publie_le', $data) && method_exists($e, 'setPublieLe')) {
            $e->setPublieLe($this->parseDateTime($data['publie_le']));
        }

        // Relations
        if (method_exists($e, 'setCategorie')) $e->setCategorie($cat);
        if (method_exists($e, 'setSousCategorie')) $e->setSousCategorie($sub);

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
        $e = $this->em->getRepository(Product::class)->find($id);
        if (!$e) return $this->error('Not found', 404);

        $data = $this->jsonBody($request);
        if ($data === null) return $this->error('Invalid JSON', 400);

        // Textes
        $this->setIfExists($e, 'setTitre', $data, 'titre');
        $this->setIfExists($e, 'setSlug', $data, 'slug');
        $this->setIfExists($e, 'setSku', $data, 'sku');
        $this->setIfExists($e, 'setMarque', $data, 'marque');
        $this->setIfExists($e, 'setDescriptionCourte', $data, 'description_courte');
        $this->setIfExists($e, 'setDescriptionHtml', $data, 'description_html');
        $this->setIfExists($e, 'setSeoTitre', $data, 'seo_titre');
        $this->setIfExists($e, 'setSeoDescription', $data, 'seo_description');

        // Images / JSON
        $this->setIfExists($e, 'setImageMiniature', $data, 'image_miniature');
        $this->setIfExists($e, 'setGalerieJson', $data, 'galerie_json');

        // Prix / devise
        if (array_key_exists('prix', $data) && method_exists($e, 'setPrix')) $e->setPrix($data['prix']);
        $this->setIfExists($e, 'setDevise', $data, 'devise');

        // Etats / dates
        $this->setIfExists($e, 'setEstActif', $data, 'est_actif');
        if (array_key_exists('publie_le', $data) && method_exists($e, 'setPublieLe')) {
            $e->setPublieLe($this->parseDateTime($data['publie_le']));
        }

        // Relations si fournies
        if (array_key_exists('categorie_id', $data)) {
            $cat = null;
            if ($data['categorie_id']) {
                $cat = $this->em->getRepository(Category::class)->find((int)$data['categorie_id']);
                if (!$cat) return $this->error('Not found', 404);
            }
            if (method_exists($e, 'setCategorie')) $e->setCategorie($cat);
        }
        if (array_key_exists('sous_categorie_id', $data)) {
            $sub = null;
            if ($data['sous_categorie_id']) {
                $sub = $this->em->getRepository(Category::class)->find((int)$data['sous_categorie_id']);
                if (!$sub) return $this->error('Not found', 404);
            }
            if (method_exists($e, 'setSousCategorie')) $e->setSousCategorie($sub);
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
        $e = $this->em->getRepository(Product::class)->find($id);
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

    /**
     * @param \Doctrine\ORM\QueryBuilder $qb
     * @param string|null $orderParam ex: "prix:asc" ou "titre:desc"
     * @param array<string> $allowed ex: ['titre','prix','cree_le','modifie_le','id']
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
}
