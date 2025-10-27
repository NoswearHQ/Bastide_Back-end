<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;

#[Route('/crud/products')]
class ProductController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        [$page, $limit] = $this->paginateParams($request);

        $qb = $this->em->getRepository(Product::class)->createQueryBuilder('e')
            // 🟢 on ajoute les jointures
            ->leftJoin('e.categorie', 'c')
            ->leftJoin('e.sous_categorie', 'sc')
            ->select("
            e.id                 AS id,
            e.titre              AS titre,
            e.slug               AS slug,
            e.prix               AS prix,
            e.devise             AS devise,
            e.reference          AS reference,
            e.image_miniature    AS image_miniature,
            e.galerie_json       AS galerie_json,
            e.description_html   AS seo_description,
            e.est_actif          AS est_actif,
            e.cree_le            AS cree_le,
            e.modifie_le         AS modifie_le,
            IDENTITY(e.categorie)      AS categorie_id,
            IDENTITY(e.sous_categorie) AS sous_categorie_id,
            c.nom                AS categorie_nom,       -- 🟢 ajouté
            sc.nom               AS sous_categorie_nom   -- 🟢 ajouté
        ");

        // Tri sécurisé
        $allowed = ['titre', 'prix', 'cree_le', 'modifie_le', 'id'];
        $order = $request->query->get('order');
        $this->applySafeOrdering($qb, $order, $allowed, 'titre', 'ASC');

        // Filtres - Version optimisée pour MariaDB
        $search = trim((string)$request->query->get('search', ''));
        if ($search !== '') {
            // Détection du type de recherche
            if (is_numeric($search)) {
                // Recherche exacte pour les références numériques
                $qb->andWhere('e.reference = :ref')
                    ->setParameter('ref', $search);
            } else {
                // Recherche LIKE pour les textes
                $qb->andWhere('(
            LOWER(e.titre) LIKE :s OR 
            LOWER(e.reference) LIKE :s OR 
            LOWER(e.seo_description) LIKE :s
        )')
                    ->setParameter('s', '%'.mb_strtolower($search).'%');
            }
        }

        if ($request->query->has('categoryId')) {
            $qb->andWhere('IDENTITY(e.categorie) = :cid')
                ->setParameter('cid', (int)$request->query->get('categoryId'));
        }

        if ($request->query->has('subCategoryId')) {
            $qb->andWhere('IDENTITY(e.sous_categorie) = :scid')
                ->setParameter('scid', (int)$request->query->get('subCategoryId'));
        }

        // Count propre
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
            e.reference          AS reference,
            e.est_actif          AS est_actif,
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
    #[Route('/upload', name: 'product_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em, LoggerInterface $logger): JsonResponse
    {
        $titre = trim((string) $request->request->get('titre', ''));
        $reference = trim((string) $request->request->get('reference', ''));
        if ($reference === '') {
            $reference = null;
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($titre));
        $prix = $request->request->get('prix');
        $description = $request->request->get('description', '');
        $estActif = $request->request->get('est_actif', true);
        $categorieId = $request->request->get('categorie_id');

        $files = $request->files->get('images');
        if (!$files || !is_array($files)) {
            return new JsonResponse(['error' => 'Aucune image fournie'], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/' . $slug;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $savedPaths = [];
        foreach ($files as $file) {
            $uniqueName = uniqid() . '-' . $file->getClientOriginalName();
            $file->move($uploadDir, $uniqueName);
            $savedPaths[] = 'images/' . $slug . '/' . $uniqueName;
        }

        // ✅ copie des images vers le front
        try {
            $projectDir = $this->getParameter('kernel.project_dir');
            $frontendImagesDir = $projectDir . '/../Front end/src/assets/' . $slug;

            if (!is_dir($frontendImagesDir)) {
                mkdir($frontendImagesDir, 0775, true);
            }

            foreach ($savedPaths as $relPath) {
                $source = $projectDir . '/public/' . $relPath;
                $target = $frontendImagesDir . '/' . basename($relPath);
                if (file_exists($source)) {
                    copy($source, $target);
                    $logger->info("✅ Copie réussie vers frontend: {$target}");
                }
            }
        } catch (\Throwable $e) {
            $logger->error("⚠️ Erreur lors de la copie vers le front: " . $e->getMessage());
        }

        // ✅ Création de l'entité produit
        $categorie = $em->getRepository(Category::class)->find($categorieId);
        if (!$categorie) {
            return new JsonResponse(['error' => 'Catégorie non trouvée'], 400);
        }

        $product = new Product();
        $product->setTitre($titre);
        $product->setReference($reference);
        $product->setSlug($slug);
        $product->setPrix($prix);
        $product->setDescriptionHtml($description);
        $product->setCategorie($categorie);
        $product->setSousCategorie($categorie); // temp: à adapter
        $product->setGalerieJson($savedPaths);
        $product->setImageMiniature($savedPaths[0] ?? null);
        $product->setEstActif((bool)$estActif);

        $em->persist($product);
        $em->flush();

        return new JsonResponse([
            'message' => 'Produit ajouté avec succès',
            'id' => $product->getId(),
            'images' => $savedPaths,
        ], 201);
    }



    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['PATCH','POST'])]
    public function patch(int $id, Request $request, LoggerInterface $logger): JsonResponse
    {
        $e = $this->em->getRepository(Product::class)->find($id);
        if (!$e) return $this->error('Not found', 404);

        $isMultipart = str_contains($request->headers->get('Content-Type', ''), 'multipart/form-data');

        // --- FormData (fichiers + champs texte)
        if ($isMultipart) {
            $titre        = $request->request->get('titre');
            $reference    = $request->request->get('reference');
            $description  = $request->request->get('description_html');
            $categorieId  = $request->request->get('categorie_id');
            $prix         = $request->request->get('prix');
            $rawEstActif  = $request->request->get('est_actif'); // peut être null
            $galerieJson  = $request->request->get('galerie_json'); // JSON string attendu

            // N’appliquer est_actif QUE s’il est fourni (évite le null -> false)
            if ($rawEstActif !== null) {
                $e->setEstActif(in_array($rawEstActif, ['true','1',1,true,'on'], true));
            }

            if ($titre)       $e->setTitre($titre);
            if ($reference)   $e->setReference($reference);
            if ($description) $e->setDescriptionHtml($description);
            if ($prix !== null && $prix !== '') $e->setPrix($prix);

            if ($categorieId) {
                $cat = $this->em->getRepository(Category::class)->find((int)$categorieId);
                if ($cat) $e->setCategorie($cat);
            }

            // galerie_json : stockée en array PHP (pas string)
            if ($galerieJson !== null && $galerieJson !== '') {
                $decoded = json_decode($galerieJson, true);
                if (!is_array($decoded)) $decoded = [];
                $e->setGalerieJson($decoded);
            }

            // Dossiers
            $slug = $e->getSlug() ?: preg_replace('/[^a-z0-9]+/i', '-', strtolower($titre ?: 'produit'));
            $projectDir   = $this->getParameter('kernel.project_dir');
            $uploadDir    = $projectDir . '/public/images/' . $slug;
            $frontendDir  = $projectDir . '/../Front end/src/assets/' . $slug;

            if (!is_dir($uploadDir))   mkdir($uploadDir, 0775, true);
            if (!is_dir($frontendDir)) mkdir($frontendDir, 0775, true);

            // Miniature
            $miniatureFile = $request->files->get('image_miniature');
            if ($miniatureFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $uniqueName = uniqid() . '-' . $miniatureFile->getClientOriginalName();
                $miniatureFile->move($uploadDir, $uniqueName);
                $relPath = 'images/' . $slug . '/' . $uniqueName;
                $e->setImageMiniature($relPath);
                @copy($uploadDir . '/' . $uniqueName, $frontendDir . '/' . $uniqueName);
            }

            // Galerie — accepter 'images' ou 'images[]'
            $allFiles  = $request->files->all();
            $filesList = $allFiles['images'] ?? $allFiles['images[]'] ?? null;
            if ($filesList && is_array($filesList)) {
                $newPaths = [];
                foreach ($filesList as $file) {
                    if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) continue;
                    $uniqueName = uniqid() . '-' . $file->getClientOriginalName();
                    $file->move($uploadDir, $uniqueName);
                    $relPath = 'images/' . $slug . '/' . $uniqueName;
                    $newPaths[] = $relPath;
                    @copy($uploadDir . '/' . $uniqueName, $frontendDir . '/' . $uniqueName);
                }

                // fusionner avec l’existant
                $existing = $e->getGalerieJson();
                if (is_string($existing)) $existing = json_decode($existing, true);
                if (!is_array($existing)) $existing = [];
                $e->setGalerieJson(array_values(array_unique(array_merge($existing, $newPaths))));
            }

        } else {
            // --- JSON classique
            $data = $this->jsonBody($request);
            if ($data === null) return $this->error('Invalid JSON', 400);

            $this->setIfExists($e, 'setTitre',            $data, 'titre');
            $this->setIfExists($e, 'setReference',        $data, 'reference');
            $this->setIfExists($e, 'setDescriptionHtml',  $data, 'description_html');
            $this->setIfExists($e, 'setImageMiniature',   $data, 'image_miniature');

            if (array_key_exists('galerie_json', $data)) {
                $val = $data['galerie_json'];
                if (is_string($val)) $val = json_decode($val, true);
                if (!is_array($val)) $val = [];
                $e->setGalerieJson($val);
            }
            if (array_key_exists('prix', $data)) {
                $e->setPrix($data['prix']);
            }
            if (array_key_exists('est_actif', $data)) {
                $e->setEstActif((bool)$data['est_actif']);
            }
            if (array_key_exists('categorie_id', $data)) {
                $cat = $data['categorie_id'] ? $this->em->getRepository(Category::class)->find((int)$data['categorie_id']) : null;
                if ($cat) $e->setCategorie($cat);
            }
        }

        if (method_exists($e, 'setModifieLe')) {
            $e->setModifieLe(new \DateTimeImmutable());
        }

        try {
            $this->em->flush();
        } catch (\Throwable $ex) {
            $logger->error("⚠️ Erreur update produit : ".$ex->getMessage());
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(['ok' => true], 200);
    }



    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, LoggerInterface $logger): JsonResponse
    {
        $product = $this->em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->error('Not found', 404);
        }

        try {
            // 🔹 Récupérer le slug et les chemins d’images avant suppression
            $slug = $product->getSlug();
            $gallery = $product->getGalerieJson();

            if (is_string($gallery)) {
                $gallery = json_decode($gallery, true);
            }

            // 🔹 Dossiers à nettoyer
            $backendDir = $this->getParameter('kernel.project_dir') . '/public/images/' . $slug;
            $frontendDir = $this->getParameter('kernel.project_dir') . '/../Front end/src/assets/' . $slug;

            // --- Suppression côté backend ---
            if (is_dir($backendDir)) {
                $this->deleteDirectory($backendDir);
                $logger->info("🗑️ Dossier supprimé (back) : $backendDir");
            }

            // --- Suppression côté frontend ---
            if (is_dir($frontendDir)) {
                $this->deleteDirectory($frontendDir);
                $logger->info("🗑️ Dossier supprimé (front assets) : $frontendDir");
            }

            // --- Suppression BDD ---
            $this->em->remove($product);
            $this->em->flush();

        } catch (\Throwable $ex) {
            $logger->error("⚠️ Erreur suppression produit : " . $ex->getMessage());
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(['message' => 'Produit et images supprimés'], 200);
    }

    /**
     * Supprime récursivement un dossier et son contenu
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
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
