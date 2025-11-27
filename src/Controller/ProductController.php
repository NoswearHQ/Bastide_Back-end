<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Category;
use App\Entity\ProduitsDetails;
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
            e.is_landing_page    AS is_landing_page,
            e.position           AS position,
            e.cree_le            AS cree_le,
            e.modifie_le         AS modifie_le,
            IDENTITY(e.categorie)      AS categorie_id,
            IDENTITY(e.sous_categorie) AS sous_categorie_id,
            c.nom                AS categorie_nom,
            sc.nom               AS sous_categorie_nom
        ");

        $allowed = ['titre', 'prix', 'cree_le', 'modifie_le', 'id', 'position'];
        $order = $request->query->get('order');
        
        // If category is selected, prioritize position ordering
        if ($request->query->has('categoryId')) {
            // Order by position with NULL handling
            // Use a simple approach: order by position ASC (NULLs come first in MySQL by default)
            // To put NULLs last, we'll use a CASE expression in addSelect
            // This is the most compatible approach with Doctrine DQL
            $qb->addSelect('(CASE WHEN e.position IS NULL THEN 1 ELSE 0 END) AS HIDDEN pos_is_null');
            $qb->addOrderBy('pos_is_null', 'ASC');  // 0 (has position) comes before 1 (NULL)
            $qb->addOrderBy('e.position', 'ASC');    // Then order by position value
            $qb->addOrderBy('e.id', 'ASC');          // Then by ID for consistency
            // Note: User's sort preference is ignored when category is selected
            // to ensure position-based ordering is always respected
        } else {
            // No category selected, use normal ordering
            $this->applySafeOrdering($qb, $order, $allowed, 'titre', 'ASC');
        }

        $search = trim((string)$request->query->get('search', ''));
        if ($search !== '') {
            if (is_numeric($search)) {
                $qb->andWhere('e.reference = :ref')->setParameter('ref', $search);
            } else {
                $qb->andWhere('(
                    LOWER(e.titre) LIKE :s OR 
                    LOWER(e.reference) LIKE :s OR 
                    LOWER(e.seo_description) LIKE :s
                )')->setParameter('s', '%'.mb_strtolower($search).'%');
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

        // Filter by est_actif - only show active products by default
        $showInactive = $request->query->get('showInactive', false);
        if (!$showInactive) {
            $qb->andWhere('e.est_actif = :est_actif')
                ->setParameter('est_actif', true);
        }

        // Filter by is_landing_page for homepage featured products
        if ($request->query->has('isLandingPage')) {
            $isLandingPage = filter_var($request->query->get('isLandingPage'), FILTER_VALIDATE_BOOLEAN);
            $qb->andWhere('e.is_landing_page = :is_landing_page')
                ->setParameter('is_landing_page', $isLandingPage);
        }

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
            ->leftJoin('e.details', 'd')
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
            e.is_landing_page    AS is_landing_page,
            e.position           AS position,
            IDENTITY(e.categorie)      AS categorie_id,
            IDENTITY(e.sous_categorie) AS sous_categorie_id,
            c.nom                AS categorie_nom,
            sc.nom               AS sous_categorie_nom,
            d.id                 AS details_id,
            d.brand              AS details_brand,
            d.sku                AS details_sku,
            d.description_seo    AS details_description_seo,
            d.rating_value       AS details_rating_value,
            d.rating_count       AS details_rating_count,
            d.availability       AS details_availability,
            d.gtin               AS details_gtin,
            d.mpn                AS details_mpn,
            d.condition          AS details_condition,
            d.price_valid_until  AS details_price_valid_until,
            d.category_schema    AS details_category_schema
        ")
            ->andWhere('e.id = :id')->setParameter('id', $id)
            ->setMaxResults(1);

        $row = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);
        if (!$row) {
            return new JsonResponse(['message' => 'Not found'], 404);
        }

        // Transform details fields into a nested object
        $details = null;
        if ($row['details_id'] !== null) {
            $details = [
                'id' => $row['details_id'],
                'brand' => $row['details_brand'],
                'sku' => $row['details_sku'],
                'description_seo' => $row['details_description_seo'],
                'rating_value' => $row['details_rating_value'],
                'rating_count' => $row['details_rating_count'],
                'availability' => $row['details_availability'],
                'gtin' => $row['details_gtin'],
                'mpn' => $row['details_mpn'],
                'condition' => $row['details_condition'],
                'price_valid_until' => $row['details_price_valid_until'] ? 
                    (new \DateTime($row['details_price_valid_until']))->format('Y-m-d') : null,
                'category_schema' => $row['details_category_schema'],
            ];
        }

        // Remove details_ prefixed keys from main row
        $product = array_filter($row, function($key) {
            return !str_starts_with($key, 'details_');
        }, ARRAY_FILTER_USE_KEY);

        $product['details'] = $details;

        return new JsonResponse($product, 200);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/upload', name: 'product_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em, LoggerInterface $logger): JsonResponse
    {
        $titre = trim((string) $request->request->get('titre', ''));
        $reference = trim((string) $request->request->get('reference', '')) ?: null;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($titre ?: 'produit'));
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
            if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                continue;
            }
            $original = $file->getClientOriginalName();
            $safeOriginal = preg_replace('/\\s+/', '-', $original);
            $uniqueName = uniqid() . '-' . $safeOriginal;
            $file->move($uploadDir, $uniqueName);
            $savedPaths[] = 'images/' . $slug . '/' . $uniqueName; // relative
        }

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
        $product->setSousCategorie($categorie); // à ajuster si logique différente
        $product->setGalerieJson($savedPaths);
        $product->setImageMiniature($savedPaths[0] ?? null);
        $product->setEstActif((bool)$estActif);

        $em->persist($product);
        $em->flush();

        // Regenerate sitemap after creating product
        $this->regenerateSitemap();

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
        $logger->info("🔄 PATCH request received for product #{$id}");
        
        try {
            $e = $this->em->getRepository(Product::class)->find($id);
            if (!$e) {
                $logger->warning("⚠️ Product #{$id} not found");
                return $this->error('Not found', 404);
            }

            $isMultipart = str_contains($request->headers->get('Content-Type', ''), 'multipart/form-data');
            $logger->info("📦 Request type: " . ($isMultipart ? 'multipart/form-data' : 'JSON'));

        if ($isMultipart) {
            $titre        = $request->request->get('titre');
            $reference    = $request->request->get('reference');
            $description  = $request->request->get('description_html');
            $descriptionCourte = $request->request->get('description_courte');
            $categorieId  = $request->request->get('categorie_id');
            $prix         = $request->request->get('prix');
            $rawEstActif  = $request->request->get('est_actif');
            $rawIsLandingPage = $request->request->get('is_landing_page');
            $galerieJson  = $request->request->get('galerie_json');

            if ($rawEstActif !== null) {
                $e->setEstActif(in_array($rawEstActif, ['true','1',1,true,'on'], true));
            }

            if ($rawIsLandingPage !== null) {
                $newValue = in_array($rawIsLandingPage, ['true','1',1,true,'on'], true);
                // Validate: prevent more than 6 products being marked as landing page
                if ($newValue && !$e->isLandingPage()) {
                    $count = $this->em->getRepository(Product::class)
                        ->createQueryBuilder('p')
                        ->select('COUNT(p.id)')
                        ->where('p.is_landing_page = :true')
                        ->setParameter('true', true)
                        ->getQuery()
                        ->getSingleScalarResult();
                    
                    if ($count >= 6) {
                        return $this->error('Vous ne pouvez sélectionner que 6 produits maximum pour la page d\'accueil.', 400);
                    }
                }
                $e->setIsLandingPage($newValue);
            }
            
            $rawPosition = $request->request->get('position');
            if ($rawPosition !== null) {
                $e->setPosition($rawPosition === '' ? null : (int)$rawPosition);
            }

            if ($request->request->has('titre')) {
                $e->setTitre($titre ?? '');
            }
            if ($request->request->has('reference')) {
                // Normalize empty string to NULL to avoid unique index conflicts on ''
                $normalizedRef = ($reference !== null && trim($reference) !== '') ? $reference : null;
                $e->setReference($normalizedRef);
            }
            if ($request->request->has('description_html')) {
                $e->setDescriptionHtml($description ?? '');
            }
            if ($request->request->has('description_courte')) {
                $e->setDescriptionCourte($descriptionCourte ?? '');
            }
            if ($request->request->has('prix')) {
                $prixValue = $request->request->get('prix');
                $e->setPrix($prixValue !== null && $prixValue !== '' ? $prixValue : null);
            }

            if ($categorieId) {
                $cat = $this->em->getRepository(Category::class)->find((int)$categorieId);
                if ($cat) {
                    $e->setCategorie($cat);
                    // Ensure sous_categorie is also set (required field)
                    if (!$e->getSousCategorie()) {
                        $e->setSousCategorie($cat);
                    }
                }
            }

            if ($galerieJson !== null && $galerieJson !== '') {
                $decoded = json_decode($galerieJson, true);
                if (!is_array($decoded)) $decoded = [];
                $e->setGalerieJson($decoded);
            }

            // Determine slug for file uploads (use existing slug if titre not updated or empty)
            $slug = $e->getSlug() ?: 'produit';
            if ($request->request->has('titre') && !empty($titre)) {
                $newSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($titre)));
                if (!empty($newSlug) && $newSlug !== $slug) {
                    $e->setSlug($newSlug);
                    $slug = $newSlug;
                }
            }
            $projectDir   = $this->getParameter('kernel.project_dir');
            $uploadDir    = $projectDir . '/public/images/' . $slug;

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

            // Miniature
            $miniatureFile = $request->files->get('image_miniature');
            if ($miniatureFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $safeOriginal = preg_replace('/\\s+/', '-', $miniatureFile->getClientOriginalName());
                $uniqueName = uniqid() . '-' . $safeOriginal;
                $miniatureFile->move($uploadDir, $uniqueName);
                $relPath = 'images/' . $slug . '/' . $uniqueName;
                $e->setImageMiniature($relPath);
            }

            // Galerie — accepter 'images' ou 'images[]'
            $allFiles  = $request->files->all();
            $filesList = $allFiles['images'] ?? $allFiles['images[]'] ?? null;
            if ($filesList && is_array($filesList)) {
                $newPaths = [];
                foreach ($filesList as $file) {
                    if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) continue;
                    $safeOriginal = preg_replace('/\\s+/', '-', $file->getClientOriginalName());
                    $uniqueName = uniqid() . '-' . $safeOriginal;
                    $file->move($uploadDir, $uniqueName);
                    $relPath = 'images/' . $slug . '/' . $uniqueName;
                    $newPaths[] = $relPath;
                }

                $existing = $e->getGalerieJson();
                if (is_string($existing)) $existing = json_decode($existing, true);
                if (!is_array($existing)) $existing = [];
                $e->setGalerieJson(array_values(array_unique(array_merge($existing, $newPaths))));
            }

        } else {
            $data = $this->jsonBody($request);
            if ($data === null) return $this->error('Invalid JSON', 400);

            $this->setIfExists($e, 'setTitre',            $data, 'titre');
            // Handle reference separately to normalize empty string to NULL
            if (array_key_exists('reference', $data)) {
                $ref = $data['reference'];
                $normalizedRef = ($ref !== null && trim((string)$ref) !== '') ? $ref : null;
                $e->setReference($normalizedRef);
            }
            $this->setIfExists($e, 'setDescriptionHtml',  $data, 'description_html');
            $this->setIfExists($e, 'setDescriptionCourte', $data, 'description_courte');
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
            if (array_key_exists('is_landing_page', $data)) {
                $newValue = (bool)$data['is_landing_page'];
                // Validate: prevent more than 6 products being marked as landing page
                if ($newValue && !$e->isLandingPage()) {
                    $count = $this->em->getRepository(Product::class)
                        ->createQueryBuilder('p')
                        ->select('COUNT(p.id)')
                        ->where('p.is_landing_page = :true')
                        ->setParameter('true', true)
                        ->getQuery()
                        ->getSingleScalarResult();
                    
                    if ($count >= 6) {
                        return $this->error('Vous ne pouvez sélectionner que 6 produits maximum pour la page d\'accueil.', 400);
                    }
                }
                $e->setIsLandingPage($newValue);
            }
            if (array_key_exists('position', $data)) {
                $e->setPosition($data['position'] !== null ? (int)$data['position'] : null);
            }
            if (array_key_exists('categorie_id', $data)) {
                $cat = $data['categorie_id'] ? $this->em->getRepository(Category::class)->find((int)$data['categorie_id']) : null;
                if ($cat) $e->setCategorie($cat);
            }
            }

            if (method_exists($e, 'setModifieLe')) {
                $e->setModifieLe(new \DateTimeImmutable());
            }

            // Handle ProduitsDetails update - use custom SQL to avoid condition column SQL error
            try {
                $details = $e->getDetails();
                $detailsId = null;
                
                // Get product data for auto-population
                $productReference = $e->getReference(); // SKU = product reference
                $productDescriptionCourte = $e->getDescriptionCourte(); // description_seo = description_courte
                $productCategory = $e->getCategorie()?->getNom(); // category_schema = category name
                
                if (!$details) {
                    // Create new ProduitsDetails record with auto-populated fields
                    $conn = $this->em->getConnection();
                    $conn->executeStatement(
                        'INSERT INTO produits_details (produit_id, sku, description_seo, category_schema, availability, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                        [
                            $e->getId(),
                            $productReference, // Auto-populate SKU from product reference
                            $productDescriptionCourte, // Auto-populate description_seo from description_courte
                            $productCategory, // Auto-populate category_schema from category name
                            'InStock'
                        ]
                    );
                    $detailsId = $conn->lastInsertId();
                } else {
                    $detailsId = $details->getId();
                }
                
                // Update ProduitsDetails using raw SQL to avoid condition column issue
                $conn = $this->em->getConnection();
                $updates = [];
                $params = [];
                
                // Always update auto-populated fields if they exist in product
                if ($productReference !== null) {
                    $updates[] = 'sku = ?';
                    $params[] = $productReference;
                }
                if ($productDescriptionCourte !== null) {
                    $updates[] = 'description_seo = ?';
                    $params[] = $productDescriptionCourte;
                }
                if ($productCategory !== null) {
                    $updates[] = 'category_schema = ?';
                    $params[] = $productCategory;
                }
                
                if ($isMultipart) {
                    $formData = $request->request->all();
                    if ($request->request->has('details_brand')) {
                        $updates[] = 'brand = ?';
                        $params[] = $formData['details_brand'] ?: null;
                    }
                    if ($request->request->has('details_availability')) {
                        $updates[] = 'availability = ?';
                        $params[] = $formData['details_availability'] ?: 'InStock';
                    }
                }
                
                if (!empty($updates)) {
                    $updates[] = 'updated_at = NOW()';
                    $params[] = $detailsId;
                    $sql = 'UPDATE produits_details SET ' . implode(', ', $updates) . ' WHERE id = ?';
                    $conn->executeStatement($sql, $params);
                }
            } catch (\Throwable $detailsEx) {
                $logger->error("⚠️ Erreur dans updateProduitsDetails : " . $detailsEx->getMessage());
                $logger->error("⚠️ Stack trace : ".$detailsEx->getTraceAsString());
                // Continue with product update even if details update fails
            }

            // Log what we're about to save for debugging
            $logger->info("🔄 Updating product #{$id}", [
                'titre' => $e->getTitre(),
                'slug' => $e->getSlug(),
                'categorie_id' => $e->getCategorie()?->getId(),
                'sous_categorie_id' => $e->getSousCategorie()?->getId(),
                'description_courte' => substr($e->getDescriptionCourte() ?? '', 0, 50),
                'reference' => $e->getReference(),
                'prix' => $e->getPrix(),
            ]);
            
            // Ensure sous_categorie is set (required field)
            if (!$e->getSousCategorie() && $e->getCategorie()) {
                $e->setSousCategorie($e->getCategorie());
                $logger->info("⚠️ sous_categorie was null, setting to categorie");
            }
            
            // Ensure slug is set (required field)
            if (!$e->getSlug() || empty($e->getSlug())) {
                $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($e->getTitre() ?: 'produit')));
                $e->setSlug($slug);
                $logger->info("⚠️ slug was empty, generated: {$slug}");
            }
            
            // Flush changes to database (entity is already managed, no need to persist)
            $this->em->flush();
            
            $logger->info("✅ Product #{$id} updated successfully");
            
            // Regenerate sitemap after updating product (don't fail if this errors)
            try {
                $this->regenerateSitemap();
            } catch (\Throwable $sitemapEx) {
                $logger->warning("⚠️ Failed to regenerate sitemap: " . $sitemapEx->getMessage());
                // Don't fail the whole update if sitemap generation fails
            }
            
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $ex) {
            $logger->error("⚠️ Erreur contrainte unique : ".$ex->getMessage());
            $logger->error("⚠️ Stack trace : ".$ex->getTraceAsString());
            return $this->error('Une valeur unique existe déjà (slug ou SKU peut-être dupliqué): ' . $ex->getMessage(), 400);
        } catch (\Doctrine\ORM\Exception\ORMException $ex) {
            $logger->error("⚠️ Erreur ORM : ".$ex->getMessage());
            $logger->error("⚠️ Stack trace : ".$ex->getTraceAsString());
            return $this->error('Erreur ORM: ' . $ex->getMessage(), 500);
        } catch (\Symfony\Component\Validator\Exception\ValidationFailedException $ex) {
            $violations = [];
            foreach ($ex->getViolations() as $violation) {
                $violations[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            $errorMsg = 'Erreur de validation: ' . implode(', ', $violations);
            $logger->error("⚠️ " . $errorMsg);
            return $this->error($errorMsg, 400);
        } catch (\Throwable $ex) {
            $errorMsg = $ex->getMessage();
            $logger->error("⚠️ Erreur update produit : " . $errorMsg);
            $logger->error("⚠️ Fichier : " . $ex->getFile() . ":" . $ex->getLine());
            $logger->error("⚠️ Stack trace : ".$ex->getTraceAsString());
            return $this->error('Erreur lors de la mise à jour: ' . $errorMsg, 500);
        }

        return new JsonResponse(['ok' => true], 200);
    }

    /**
     * Update or create ProduitsDetails for a product
     */
    private function updateProduitsDetails(Product $product, Request $request, bool $isMultipart): void
    {
        $detailsRepo = $this->em->getRepository(ProduitsDetails::class);
        $details = $product->getDetails();

        // Create if doesn't exist
        if (!$details) {
            $details = new ProduitsDetails();
            $details->setProduit($product);
            $this->em->persist($details);
        }

        if ($isMultipart) {
            // Handle multipart form data
            $formData = $request->request->all();
            $this->setIfExists($details, 'setBrand', $formData, 'details_brand');
            $this->setIfExists($details, 'setSku', $formData, 'details_sku');
            $this->setIfExists($details, 'setDescriptionSeo', $formData, 'details_description_seo');
            $this->setIfExists($details, 'setAvailability', $formData, 'details_availability');
            $this->setIfExists($details, 'setGtin', $formData, 'details_gtin');
            $this->setIfExists($details, 'setMpn', $formData, 'details_mpn');
            // Skip condition field - it's a reserved keyword and not used in the form
            // $this->setIfExists($details, 'setCondition', $formData, 'details_condition');
            $this->setIfExists($details, 'setCategorySchema', $formData, 'details_category_schema');
            
            // Note: condition field is excluded from updates due to reserved keyword issue

            // Handle numeric fields
            if ($request->request->has('details_rating_value')) {
                $val = $request->request->get('details_rating_value');
                if ($val !== null && $val !== '') {
                    $details->setRatingValue((float)$val);
                }
            }
            if ($request->request->has('details_rating_count')) {
                $val = $request->request->get('details_rating_count');
                if ($val !== null && $val !== '') {
                    $details->setRatingCount((int)$val);
                }
            }
            if ($request->request->has('details_price_valid_until')) {
                $val = $request->request->get('details_price_valid_until');
                if ($val !== null && $val !== '') {
                    try {
                        $details->setPriceValidUntil(new \DateTimeImmutable($val));
                    } catch (\Throwable) {
                        // Invalid date, skip
                    }
                }
            }
        } else {
            // Handle JSON data
            $data = $this->jsonBody($request);
            if ($data === null) return;

            // Check if details object exists in payload
            if (isset($data['details']) && is_array($data['details'])) {
                $detailsData = $data['details'];
                
                $this->setIfExists($details, 'setBrand', $detailsData, 'brand');
                $this->setIfExists($details, 'setSku', $detailsData, 'sku');
                $this->setIfExists($details, 'setDescriptionSeo', $detailsData, 'description_seo');
                $this->setIfExists($details, 'setAvailability', $detailsData, 'availability');
                $this->setIfExists($details, 'setGtin', $detailsData, 'gtin');
                $this->setIfExists($details, 'setMpn', $detailsData, 'mpn');
                // Skip condition field - it's a reserved keyword and not used in the form
                // $this->setIfExists($details, 'setCondition', $detailsData, 'condition');
                $this->setIfExists($details, 'setCategorySchema', $detailsData, 'category_schema');
                
                // Note: condition field is excluded from updates due to reserved keyword issue

                if (array_key_exists('rating_value', $detailsData)) {
                    $details->setRatingValue((float)$detailsData['rating_value']);
                }
                if (array_key_exists('rating_count', $detailsData)) {
                    $details->setRatingCount((int)$detailsData['rating_count']);
                }
                if (array_key_exists('price_valid_until', $detailsData) && $detailsData['price_valid_until'] !== null) {
                    try {
                        $details->setPriceValidUntil(new \DateTimeImmutable($detailsData['price_valid_until']));
                    } catch (\Throwable) {
                        // Invalid date, skip
                    }
                }
            }
        }
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
            $slug = $product->getSlug();
            $backendDir = $this->getParameter('kernel.project_dir') . '/public/images/' . $slug;

            if (is_dir($backendDir)) {
                $this->deleteDirectory($backendDir);
                $logger->info("🗑️ Dossier supprimé (back) : $backendDir");
            }

            $this->em->remove($product);
            $this->em->flush();
            
            // Regenerate sitemap after deleting product
            $this->regenerateSitemap();

        } catch (\Throwable $ex) {
            $logger->error("⚠️ Erreur suppression produit : " . $ex->getMessage());
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(['message' => 'Produit et images supprimés'], 200);
    }

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
        // Ensure error message is always returned
        return new JsonResponse([
            'error' => $msg,
            'message' => $msg, // Also include as 'message' for compatibility
        ], $code);
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

    /**
     * Regenerate sitemap after product changes
     */
    private function regenerateSitemap(): void
    {
        try {
            $siteBase = 'https://bastide.tn';
            
            $staticUrls = [
                ['loc' => $siteBase . '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
                ['loc' => $siteBase . '/services', 'changefreq' => 'weekly', 'priority' => '0.8'],
                ['loc' => $siteBase . '/produits', 'changefreq' => 'daily', 'priority' => '0.9'],
                ['loc' => $siteBase . '/location-materiel', 'changefreq' => 'weekly', 'priority' => '0.7'],
                ['loc' => $siteBase . '/actualites', 'changefreq' => 'weekly', 'priority' => '0.7'],
                ['loc' => $siteBase . '/engagements', 'changefreq' => 'weekly', 'priority' => '0.7'],
                ['loc' => $siteBase . '/catalogue', 'changefreq' => 'weekly', 'priority' => '0.7'],
                ['loc' => $siteBase . '/contact', 'changefreq' => 'weekly', 'priority' => '0.7'],
            ];
            
            $articles = $this->em->getRepository(\App\Entity\Article::class)
                ->createQueryBuilder('a')
                ->where('a.statut = :statut')
                ->setParameter('statut', 'publie')
                ->getQuery()
                ->getResult();
            
            // Récupérer tous les produits actifs
            $products = $this->em->getRepository(Product::class)
                ->createQueryBuilder('p')
                ->where('p.est_actif = :actif')
                ->setParameter('actif', true)
                ->orderBy('p.id', 'ASC')
                ->getQuery()
                ->getResult();
            
            $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            
            foreach ($staticUrls as $url) {
                $sitemap .= sprintf(
                    "  <url>\n    <loc>%s</loc>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
                    htmlspecialchars($url['loc']),
                    htmlspecialchars($url['changefreq']),
                    htmlspecialchars($url['priority'])
                );
            }
            
            // Ajouter les produits (format: /produit/{id}-{slug})
            foreach ($products as $product) {
                if ($product->getSlug()) {
                    $productUrl = sprintf('%s/produit/%s-%s', 
                        $siteBase,
                        $product->getId(),
                        htmlspecialchars($product->getSlug())
                    );
                    $sitemap .= sprintf(
                        "  <url>\n    <loc>%s</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.9</priority>\n  </url>\n",
                        $productUrl
                    );
                }
            }
            
            foreach ($articles as $article) {
                if ($article->getSlug()) {
                    $sitemap .= sprintf(
                        "  <url>\n    <loc>%s/articles/%s</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n",
                        $siteBase,
                        htmlspecialchars($article->getSlug())
                    );
                }
            }
            
            $sitemap .= '</urlset>';
            
            // Save to frontend public and dist directories
            $possiblePaths = [
                dirname(__DIR__, 3) . '/Front end/public/sitemap.xml',
                dirname(__DIR__, 2) . '/../frontend/public/sitemap.xml',
                '/var/www/bastide/www/frontend/public/sitemap.xml',
            ];
            
            $distPaths = [
                dirname(__DIR__, 3) . '/Front end/dist/sitemap.xml',
                dirname(__DIR__, 2) . '/../frontend/dist/sitemap.xml',
                '/var/www/bastide/www/frontend/dist/sitemap.xml',
            ];
            
            // Save to public directory
            foreach ($possiblePaths as $path) {
                $dir = dirname($path);
                if (file_exists($dir) && is_writable($dir)) {
                    if (file_put_contents($path, $sitemap)) {
                        error_log("Sitemap saved to: $path");
                        break;
                    }
                }
            }
            
            // Save to dist directory
            foreach ($distPaths as $path) {
                $dir = dirname($path);
                if (file_exists($dir) && is_writable($dir)) {
                    if (file_put_contents($path, $sitemap)) {
                        error_log("Sitemap saved to dist: $path");
                        break;
                    }
                }
            }
        } catch (\Throwable $ex) {
            // Silently fail - don't break product operations if sitemap generation fails
            error_log("Sitemap generation error: " . $ex->getMessage());
        }
    }
}