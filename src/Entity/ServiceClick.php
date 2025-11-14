<?php

namespace App\Entity;

use App\Repository\ServiceClickRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceClickRepository::class)]
#[ORM\Table(name: 'service_clicks')]
#[ORM\Index(columns: ['service_name'], name: 'idx_service_name')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class ServiceClick
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $service_name;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $user_agent = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip_address = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getServiceName(): string { return $this->service_name; }
    public function setServiceName(string $v): self { $this->service_name = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(\DateTimeInterface $d): self { $this->created_at = $d; return $this; }

    public function getUserAgent(): ?string { return $this->user_agent; }
    public function setUserAgent(?string $v): self { $this->user_agent = $v; return $this; }

    public function getIpAddress(): ?string { return $this->ip_address; }
    public function setIpAddress(?string $v): self { $this->ip_address = $v; return $this; }
}

