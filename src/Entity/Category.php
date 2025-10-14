<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
#[ORM\UniqueConstraint(name: 'uniq_categories_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé.')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $parent = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $est_active = true;

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

    #[
        ORM\PrePersist
    ]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->cree_le ??= $now;
        $this->modifie_le = $now;
    }

    #[
        ORM\PreUpdate
    ]
    public function onPreUpdate(): void
    {
        $this->modifie_le = new \DateTimeImmutable();
    }

    // ---------------- Getters / Setters ----------------

    public function getId(): ?string { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getParent(): ?Category { return $this->parent; }
    public function setParent(?Category $parent): self { $this->parent = $parent; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): self { $this->position = $p; return $this; }

    public function isEstActive(): bool { return $this->est_active; }
    public function setEstActive(bool $v): self { $this->est_active = $v; return $this; }

    public function getCreeLe(): \DateTimeInterface { return $this->cree_le; }
    public function setCreeLe(\DateTimeInterface $d): self { $this->cree_le = $d; return $this; }

    public function getModifieLe(): \DateTimeInterface { return $this->modifie_le; }
    public function setModifieLe(\DateTimeInterface $d): self { $this->modifie_le = $d; return $this; }
}
