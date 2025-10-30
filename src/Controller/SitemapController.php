<?php

namespace App\Controller;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sitemap')]
class SitemapController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'generate_sitemap', methods: ['GET', 'POST'])]
    public function generate(): Response
    {
        $siteBase = 'https://bastide.tn';
        
        // URLs statiques
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
        
        // Récupérer tous les articles publiés
        $articles = $this->em->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->where('a.statut = :statut')
            ->setParameter('statut', 'publie')
            ->getQuery()
            ->getResult();
        
        // Construire le XML
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Ajouter les URLs statiques
        foreach ($staticUrls as $url) {
            $sitemap .= sprintf(
                "  <url>\n    <loc>%s</loc>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
                htmlspecialchars($url['loc']),
                htmlspecialchars($url['changefreq']),
                htmlspecialchars($url['priority'])
            );
        }
        
        // Ajouter les articles
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
        
        // Retourner le XML
        $response = new Response($sitemap, 200);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        
        return $response;
    }
    
    #[Route('/save', name: 'save_sitemap', methods: ['POST'])]
    public function save(): Response
    {
        try {
            // Générer le sitemap
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
            
            // Sauvegarder dans le front-end (chemin relatif depuis l'API)
            // Note: Ajustez ce chemin selon votre structure de déploiement
            $frontendPath = dirname(__DIR__, 3) . '/../Front end/public/sitemap.xml';
            
            if (file_exists($frontendPath)) {
                file_put_contents($frontendPath, $sitemap);
            }
            
            return new Response(json_encode([
                'success' => true,
                'message' => 'Sitemap généré avec succès',
                'count' => count($staticUrls) + count($articles)
            ]), 200, ['Content-Type' => 'application/json']);
            
        } catch (\Exception $e) {
            return new Response(json_encode([
                'success' => false,
                'message' => 'Erreur lors de la génération du sitemap: ' . $e->getMessage()
            ]), 500, ['Content-Type' => 'application/json']);
        }
    }
}

