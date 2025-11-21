<?php

namespace App\Entity;

use App\Repository\ProduitsDetailsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitsDetailsRepository::class)]
#[ORM\Table(name: 'produits_details', options: ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'])]
#[ORM\UniqueConstraint(name: 'uniq_produits_details_produit_id', columns: ['produit_id'])]
#[ORM\HasLifecycleCallbacks]
class ProduitsDetails
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\OneToOne(targetEntity: Product::class, inversedBy: 'details')]
    #[ORM\JoinColumn(name: 'produit_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le produit est obligatoire.')]
    private ?Product $produit = null;

    #[ORM\Column(type: Types::STRING, length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $brand = null;

    #[ORM\Column(type: Types::STRING, length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $sku = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description_seo = null;

    #[ORM\Column(type: Types::FLOAT, nullable: false, options: ['default' => 0.0])]
    #[Assert\Range(
        min: 0.0,
        max: 5.0,
        notInRangeMessage: 'La valeur de notation doit être entre {{ min }} et {{ max }}.'
    )]
    private float $rating_value = 0.0;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\GreaterThanOrEqual(0, message: 'Le nombre d\'avis ne peut pas être négatif.')]
    private int $rating_count = 0;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Assert\Choice(
        choices: ['InStock', 'OutOfStock', 'PreOrder', 'InStoreOnly', 'OnlineOnly', 'SoldOut', 'Discontinued'],
        message: 'La disponibilité doit être une valeur valide selon schema.org.'
    )]
    private ?string $availability = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $gtin = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $mpn = null;

    #[ORM\Column(name: 'condition', type: Types::STRING, length: 50, nullable: true, options: ['comment' => 'Product condition'])]
    #[Assert\Length(max: 50)]
    #[Assert\Choice(
        choices: ['NewCondition', 'UsedCondition', 'RefurbishedCondition', 'DamagedCondition'],
        message: 'La condition du produit doit être une valeur valide selon schema.org.'
    )]
    private ?string $condition = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $price_valid_until = null;

    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    private ?string $category_schema = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updated_at;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->created_at = $now;
        $this->updated_at = $now;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->created_at ??= $now;
        $this->updated_at = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTimeImmutable();
    }

    // ---------------- Getters / Setters ----------------

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProduit(): ?Product
    {
        return $this->produit;
    }

    public function setProduit(?Product $produit): self
    {
        $this->produit = $produit;
        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): self
    {
        $this->brand = $brand;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    public function getDescriptionSeo(): ?string
    {
        return $this->description_seo;
    }

    public function setDescriptionSeo(?string $description_seo): self
    {
        $this->description_seo = $description_seo;
        return $this;
    }

    public function getRatingValue(): float
    {
        return $this->rating_value;
    }

    public function setRatingValue(float $rating_value): self
    {
        $this->rating_value = $rating_value;
        return $this;
    }

    public function getRatingCount(): int
    {
        return $this->rating_count;
    }

    public function setRatingCount(int $rating_count): self
    {
        $this->rating_count = $rating_count;
        return $this;
    }

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function setAvailability(?string $availability): self
    {
        $this->availability = $availability;
        return $this;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function setGtin(?string $gtin): self
    {
        $this->gtin = $gtin;
        return $this;
    }

    public function getMpn(): ?string
    {
        return $this->mpn;
    }

    public function setMpn(?string $mpn): self
    {
        $this->mpn = $mpn;
        return $this;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(?string $condition): self
    {
        $this->condition = $condition;
        return $this;
    }

    public function getPriceValidUntil(): ?\DateTimeInterface
    {
        return $this->price_valid_until;
    }

    public function setPriceValidUntil(?\DateTimeInterface $price_valid_until): self
    {
        $this->price_valid_until = $price_valid_until;
        return $this;
    }

    public function getCategorySchema(): ?string
    {
        return $this->category_schema;
    }

    public function setCategorySchema(?string $category_schema): self
    {
        $this->category_schema = $category_schema;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }
}

