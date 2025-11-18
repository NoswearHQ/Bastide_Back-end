<?php

namespace App\Controller;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;

#[Route('/crud/articles')]
class ArticleController extends AbstractController
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
            e.galerie_json     AS galerie_json,
            e.contenu_html     AS contenu_html,
            e.statut           AS statut,
            e.publie_le        AS publie_le,
            e.cree_le          AS cree_le,
            e.modifie_le       AS modifie_le
        ");

        $allowed = ['titre', 'cree_le', 'modifie_le', 'id', 'publie_le'];
        $order = $request->query->get('order');
        $this->applySafeOrdering($qb, $order, $allowed, 'titre', 'ASC');

        $search = trim((string)$request->query->get('search', ''));
        if ($search !== '') {
            $qb->andWhere('(
                LOWER(e.titre) LIKE :s OR 
                LOWER(e.extrait) LIKE :s OR 
                LOWER(e.contenu_html) LIKE :s
            )')->setParameter('s', '%'.mb_strtolower($search).'%');
        }

        // Filter by statut - only show published articles by default
        $showDraft = $request->query->get('showDraft', false);
        if (!$showDraft) {
            $qb->andWhere('e.statut = :statut')
                ->setParameter('statut', 'publie');
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
        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('e')
            ->select("
            e.id               AS id,
            e.titre            AS titre,
            e.slug             AS slug,
            e.extrait          AS extrait,
            e.image_miniature  AS image_miniature,
            e.galerie_json     AS galerie_json,
            e.contenu_html     AS contenu_html,
            e.statut           AS statut,
            e.publie_le        AS publie_le,
            e.cree_le          AS cree_le,
            e.modifie_le       AS modifie_le
        ")
            ->andWhere('e.id = :id')->setParameter('id', $id)
            ->setMaxResults(1);

        $row = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);
        if (!$row) {
            return new JsonResponse(['message' => 'Not found'], 404);
        }
        return new JsonResponse($row, 200);
    }

    #[Route('/slug/{slug}', name: 'showbyslug', methods: ['GET'])]
    public function showBySlug(string $slug): JsonResponse
    {
        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('e')
            ->select("
            e.id               AS id,
            e.titre            AS titre,
            e.slug             AS slug,
            e.extrait          AS extrait,
            e.image_miniature  AS image_miniature,
            e.galerie_json     AS galerie_json,
            e.contenu_html     AS contenu_html,
            e.statut           AS statut,
            e.publie_le        AS publie_le,
            e.cree_le          AS cree_le,
            e.modifie_le       AS modifie_le
        ")
            ->andWhere('e.slug = :slug')->setParameter('slug', $slug)
            ->setMaxResults(1);

        $row = $qb->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);
        if (!$row) {
            return new JsonResponse(['message' => 'Not found'], 404);
        }
        return new JsonResponse($row, 200);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/upload', name: 'article_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em, LoggerInterface $logger): JsonResponse
    {
        $titre = trim((string) $request->request->get('titre', ''));
        $slug = $this->generateUniqueSlug($titre ?: 'article');
        $extrait = $request->request->get('extrait', '');
        $contenu = $request->request->get('contenu_html', '');
        $statut = $request->request->get('statut', 'brouillon');

        // Handle both 'images' and 'images[]' formats
        $allFiles = $request->files->all();
        $files = $allFiles['images'] ?? $allFiles['images[]'] ?? null;

        if (!$files || !is_array($files)) {
            return new JsonResponse(['error' => 'Aucune image fournie'], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/articles/' . $slug;
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
            $savedPaths[] = 'images/articles/' . $slug . '/' . $uniqueName; // relative
        }

        $article = new Article();
        $article->setTitre($titre);
        $article->setSlug($slug);
        $article->setExtrait($extrait);
        $article->setContenuHtml($contenu);
        $article->setStatut($statut);
        $article->setGalerieJson($savedPaths);
        $article->setImageMiniature($savedPaths[0] ?? null);
        
        if ($statut === 'publie') {
            $article->setPublieLe(new \DateTimeImmutable());
        }

        $em->persist($article);
        $em->flush();
        
        // Regenerate sitemap after upload
        $this->regenerateSitemap();

        return new JsonResponse([
            'message' => 'Article ajouté avec succès',
            'id' => $article->getId(),
            'images' => $savedPaths,
        ], 201);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->jsonBody($request);
        if ($data === null) return $this->error('Invalid JSON', 400);

        $article = new Article();
        $this->setIfExists($article, 'setTitre', $data, 'titre');
        $this->setIfExists($article, 'setExtrait', $data, 'extrait');
        $this->setIfExists($article, 'setContenuHtml', $data, 'contenu_html');
        $this->setIfExists($article, 'setImageMiniature', $data, 'image_miniature');
        $this->setIfExists($article, 'setStatut', $data, 'statut');

        if (array_key_exists('galerie_json', $data)) {
            $val = $data['galerie_json'];
            if (is_string($val)) $val = json_decode($val, true);
            if (!is_array($val)) $val = [];
            $article->setGalerieJson($val);
        }

        if (array_key_exists('publie_le', $data)) {
            $article->setPublieLe($this->parseDateTime($data['publie_le']));
        }

        // Generate slug if not provided
        if (!$article->getSlug() && $article->getTitre()) {
            $slug = $this->generateUniqueSlug($article->getTitre());
            $article->setSlug($slug);
        }

        try {
            $this->em->persist($article);
            $this->em->flush();
            
            // Regenerate sitemap after creating article
            $this->regenerateSitemap();
        } catch (\Throwable $ex) {
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(['id' => $article->getId()], 201);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['PATCH','POST'])]
    public function patch(int $id, Request $request, LoggerInterface $logger): JsonResponse
    {
        $e = $this->em->getRepository(Article::class)->find($id);
        if (!$e) return $this->error('Not found', 404);

        $isMultipart = str_contains($request->headers->get('Content-Type', ''), 'multipart/form-data');

        if ($isMultipart) {
            $titre        = $request->request->get('titre');
            $extrait      = $request->request->get('extrait');
            $contenu      = $request->request->get('contenu_html');
            $statut       = $request->request->get('statut');
            $rawPublieLe  = $request->request->get('publie_le');
            $galerieJson  = $request->request->get('galerie_json');

            if ($titre) $e->setTitre($titre);
            if ($extrait) $e->setExtrait($extrait);
            if ($contenu) $e->setContenuHtml($contenu);
            if ($statut) $e->setStatut($statut);

            if ($rawPublieLe !== null) {
                $e->setPublieLe($this->parseDateTime($rawPublieLe));
            }

            if ($galerieJson !== null && $galerieJson !== '') {
                $decoded = json_decode($galerieJson, true);
                if (!is_array($decoded)) $decoded = [];
                $e->setGalerieJson($decoded);
            }

            $slug = $e->getSlug() ?: preg_replace('/[^a-z0-9]+/i', '-', strtolower($titre ?: 'article'));
            $projectDir   = $this->getParameter('kernel.project_dir');
            $uploadDir    = $projectDir . '/public/images/articles/' . $slug;

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

            // Miniature
            $miniatureFile = $request->files->get('image_miniature');
            if ($miniatureFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $safeOriginal = preg_replace('/\\s+/', '-', $miniatureFile->getClientOriginalName());
                $uniqueName = uniqid() . '-' . $safeOriginal;
                $miniatureFile->move($uploadDir, $uniqueName);
                $relPath = 'images/articles/' . $slug . '/' . $uniqueName;
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
                    $relPath = 'images/articles/' . $slug . '/' . $uniqueName;
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
            $this->setIfExists($e, 'setExtrait',          $data, 'extrait');
            $this->setIfExists($e, 'setContenuHtml',      $data, 'contenu_html');
            $this->setIfExists($e, 'setImageMiniature',   $data, 'image_miniature');
            $this->setIfExists($e, 'setStatut',           $data, 'statut');

            if (array_key_exists('galerie_json', $data)) {
                $val = $data['galerie_json'];
                if (is_string($val)) $val = json_decode($val, true);
                if (!is_array($val)) $val = [];
                $e->setGalerieJson($val);
            }
            if (array_key_exists('publie_le', $data)) {
                $e->setPublieLe($this->parseDateTime($data['publie_le']));
            }
        }

        if (method_exists($e, 'setModifieLe')) {
            $e->setModifieLe(new \DateTimeImmutable());
        }

        try {
            $this->em->flush();
            
            // Regenerate sitemap after updating article
            $this->regenerateSitemap();
        } catch (\Throwable $ex) {
            $logger->error("⚠️ Erreur update article : ".$ex->getMessage());
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(['ok' => true], 200);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, LoggerInterface $logger): JsonResponse
    {
        $article = $this->em->getRepository(Article::class)->find($id);
        if (!$article) {
            return $this->error('Not found', 404);
        }

        try {
            $slug = $article->getSlug();
            $backendDir = $this->getParameter('kernel.project_dir') . '/public/images/articles/' . $slug;

            if (is_dir($backendDir)) {
                $this->deleteDirectory($backendDir);
                $logger->info("🗑️ Dossier supprimé (back) : $backendDir");
            }

            $this->em->remove($article);
            $this->em->flush();
            
            // Regenerate sitemap after deleting article
            $this->regenerateSitemap();
        } catch (\Throwable $ex) {
            $logger->error("⚠️ Erreur suppression article : " . $ex->getMessage());
            return $this->error('Internal error', 500);
        }

        return new JsonResponse(['message' => 'Article et images supprimés'], 200);
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
     * @param string|null $orderParam ex: "titre:asc" ou "cree_le:desc"
     * @param array<string> $allowed ex: ['titre','cree_le','modifie_le','id']
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

    private function generateUniqueSlug(string $titre): string
    {
        $baseSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($titre)));
        $baseSlug = trim($baseSlug, '-');
        
        if (empty($baseSlug)) {
            $baseSlug = 'article';
        }
        
        $slug = $baseSlug;
        $counter = 1;
        
        // Check if slug exists and make it unique
        while (true) {
            $existing = $this->em->getRepository(Article::class)->findOneBy(['slug' => $slug]);
            if (!$existing) {
                break;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

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
            
            $articles = $this->em->getRepository(Article::class)
                ->createQueryBuilder('a')
                ->where('a.statut = :statut')
                ->setParameter('statut', 'publie')
                ->getQuery()
                ->getResult();
            
            // Récupérer tous les produits actifs
            $products = $this->em->getRepository(\App\Entity\Product::class)
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
            // Try multiple possible paths depending on deployment
            $possiblePaths = [
                // Local development (Windows)
                dirname(__DIR__, 3) . '/Front end/public/sitemap.xml',
                // Production
                dirname(__DIR__, 2) . '/../frontend/public/sitemap.xml',
                '/var/www/bastide/www/frontend/public/sitemap.xml',
            ];
            
            $distPaths = [
                dirname(__DIR__, 3) . '/Front end/dist/sitemap.xml',
                dirname(__DIR__, 2) . '/../frontend/dist/sitemap.xml',
                '/var/www/bastide/www/frontend/dist/sitemap.xml',
            ];
            
            // Save to public directory
            $savedPublic = false;
            foreach ($possiblePaths as $path) {
                $dir = dirname($path);
                if (file_exists($dir) && is_writable($dir)) {
                    if (file_put_contents($path, $sitemap)) {
                        $savedPublic = true;
                        error_log("Sitemap saved to: $path");
                        break;
                    }
                } else {
                    error_log("Cannot write sitemap to: $path (dir exists: " . (file_exists($dir) ? 'yes' : 'no') . ", writable: " . (file_exists($dir) ? (is_writable($dir) ? 'yes' : 'no') : 'N/A') . ")");
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
            // Silently fail - don't break article operations if sitemap generation fails
            error_log("Sitemap generation error: " . $ex->getMessage());
        }
    }
}