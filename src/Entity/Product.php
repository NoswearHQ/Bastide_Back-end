<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'produits')]
#[ORM\UniqueConstraint(name: 'uniq_produits_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_produits_sku', columns: ['sku'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé.')]
#[UniqueEntity(fields: ['sku'], message: 'Ce SKU est déjà utilisé.')]
class Product
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
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $reference = null;

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $r): self { $this->reference = $r; return $this; }

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'categorie_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'La catégorie est obligatoire.')]
    private ?Category $categorie = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'sous_categorie_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'La sous-catégorie est obligatoire.')]
    private ?Category $sous_categorie = null;

    #[ORM\Column(type: Types::STRING, length: 80, unique: true, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $sku = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $marque = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description_courte = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description_html = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    /**
     * NB: stockée en string (Decimal), valide ex: "0.00", "1234.56"
     */
    #[Assert\Regex(
        pattern: '/^\d{1,8}(\.\d{1,2})?$/',
        message: 'Format de prix invalide (max 8 chiffres + 2 décimales).'
    )]
    private ?string $prix = null;

    #[ORM\Column(type: Types::STRING, length: 3)]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    private string $devise = 'EUR';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $image_miniature = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $galerie_json = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $caracteristiques_json = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $est_actif = true;

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
    public function setTitre(string $t): self { $this->titre = $t; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $s): self { $this->slug = $s; return $this; }

    public function getCategorie(): ?Category { return $this->categorie; }
    public function setCategorie(?Category $c): self { $this->categorie = $c; return $this; }

    public function getSousCategorie(): ?Category { return $this->sous_categorie; }
    public function setSousCategorie(?Category $c): self { $this->sous_categorie = $c; return $this; }

    public function getSku(): ?string { return $this->sku; }
    public function setSku(?string $s): self { $this->sku = $s; return $this; }

    public function getMarque(): ?string { return $this->marque; }
    public function setMarque(?string $m): self { $this->marque = $m; return $this; }

    public function getDescriptionCourte(): ?string { return $this->description_courte; }
    public function setDescriptionCourte(?string $d): self { $this->description_courte = $d; return $this; }

    public function getDescriptionHtml(): ?string { return $this->description_html; }
    public function setDescriptionHtml(?string $d): self { $this->description_html = $d; return $this; }

    public function getPrix(): ?string { return $this->prix; }
    public function setPrix(?string $p): self { $this->prix = $p; return $this; }

    public function getDevise(): string { return $this->devise; }
    public function setDevise(string $d): self { $this->devise = $d; return $this; }

    public function getImageMiniature(): ?string { return $this->image_miniature; }
    public function setImageMiniature(?string $i): self { $this->image_miniature = $i; return $this; }

    public function getGalerieJson(): ?array { return $this->galerie_json; }
    public function setGalerieJson(?array $g): self { $this->galerie_json = $g; return $this; }

    public function getCaracteristiquesJson(): ?array { return $this->caracteristiques_json; }
    public function setCaracteristiquesJson(?array $c): self { $this->caracteristiques_json = $c; return $this; }

    public function isEstActif(): bool { return $this->est_actif; }
    public function setEstActif(bool $v): self { $this->est_actif = $v; return $this; }

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
