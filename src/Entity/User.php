<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
#[ApiResource]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'mot_de_passe_hash', type: Types::STRING, length: 255)]
    private ?string $password = null;

    #[ORM\Column(name: 'roles_json', type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $est_actif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $derniere_connexion_le = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $cree_le;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $modifie_le;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->cree_le = $now;
        $this->modifie_le = $now;
        $this->roles = ['ROLE_USER'];
    }

    public function getId(): ?string { return $this->id; }
    public function getUserIdentifier(): string { return (string) $this->email; }
    public function getUsername(): string { return (string) $this->email; }
    public function getRoles(): array { return array_values(array_unique($this->roles)); }
    public function setRoles(array $r): self { $this->roles = $r; return $this; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $p): self { $this->password = $p; return $this; }
    public function eraseCredentials(): void {}
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $n): self { $this->nom = $n; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $e): self { $this->email = $e; return $this; }
    public function isEstActif(): bool { return $this->est_actif; }
    public function setEstActif(bool $v): self { $this->est_actif = $v; return $this; }
    public function getDerniereConnexionLe(): ?\DateTimeInterface { return $this->derniere_connexion_le; }
    public function setDerniereConnexionLe(?\DateTimeInterface $d): self { $this->derniere_connexion_le = $d; return $this; }
    public function getCreeLe(): \DateTimeInterface { return $this->cree_le; }
    public function setCreeLe(\DateTimeInterface $d): self { $this->cree_le = $d; return $this; }
    public function getModifieLe(): \DateTimeInterface { return $this->modifie_le; }
    public function setModifieLe(\DateTimeInterface $d): self { $this->modifie_le = $d; return $this; }
}
