<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ORM\UniqueConstraint(name: 'uniq_articles_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé.')]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::STRING, length: 220, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 220)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $nom_auteur = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Url(protocols: ['http','https'], message: 'URL invalide.')]
    #[Assert\Length(max: 255)]
    private ?string $image_miniature = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $galerie_json = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $extrait = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $contenu_html = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: ['brouillon','publie','archive'])]
    private string $statut = 'brouillon';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publie_le = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $seo_titre = null;

    #[ORM\Column(type: Types::STRING, length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $seo_description = null;



    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $cree_le;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $modifie_le;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->cree_le = $now;
        $this->modifie_le = $now;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->cree_le ??= $now;
        $this->modifie_le = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->modifie_le = new \DateTimeImmutable();
    }

    // ---------------- Getters / Setters ----------------

    public function getId(): ?string { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getNomAuteur(): ?string { return $this->nom_auteur; }
    public function setNomAuteur(?string $nom): self { $this->nom_auteur = $nom; return $this; }

    public function getImageMiniature(): ?string { return $this->image_miniature; }
    public function setImageMiniature(?string $img): self { $this->image_miniature = $img; return $this; }

    public function getGalerieJson(): ?array { return $this->galerie_json; }
    public function setGalerieJson(?array $g): self { $this->galerie_json = $g; return $this; }

    public function getExtrait(): ?string { return $this->extrait; }
    public function setExtrait(?string $e): self { $this->extrait = $e; return $this; }

    public function getContenuHtml(): ?string { return $this->contenu_html; }
    public function setContenuHtml(string $c): self { $this->contenu_html = $c; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }

    public function getPublieLe(): ?\DateTimeInterface { return $this->publie_le; }
    public function setPublieLe(?\DateTimeInterface $d): self { $this->publie_le = $d; return $this; }

    public function getSeoTitre(): ?string { return $this->seo_titre; }
    public function setSeoTitre(?string $t): self { $this->seo_titre = $t; return $this; }

    public function getSeoDescription(): ?string { return $this->seo_description; }
    public function setSeoDescription(?string $d): self { $this->seo_description = $d; return $this; }

    public function getCreeLe(): \DateTimeInterface { return $this->cree_le; }
    public function setCreeLe(\DateTimeInterface $d): self { $this->cree_le = $d; return $this; }

    public function getModifieLe(): \DateTimeInterface { return $this->modifie_le; }
    public function setModifieLe(\DateTimeInterface $d): self { $this->modifie_le = $d; return $this; }
}
