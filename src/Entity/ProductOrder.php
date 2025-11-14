<?php

namespace App\Entity;

use App\Repository\ProductOrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductOrderRepository::class)]
#[ORM\Table(name: 'product_orders')]
#[ORM\Index(columns: ['product_id'], name: 'idx_product_id')]
#[ORM\Index(columns: ['order_type'], name: 'idx_order_type')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class ProductOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $product_id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $product_reference = null;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $product_title;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $customer_email = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $customer_phone;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $order_type; // 'mail' or 'whatsapp'

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

    public function getProductId(): ?int { return $this->product_id; }
    public function setProductId(?int $v): self { $this->product_id = $v; return $this; }

    public function getProductReference(): ?string { return $this->product_reference; }
    public function setProductReference(?string $v): self { $this->product_reference = $v; return $this; }

    public function getProductTitle(): string { return $this->product_title; }
    public function setProductTitle(string $v): self { $this->product_title = $v; return $this; }

    public function getCustomerEmail(): ?string { return $this->customer_email; }
    public function setCustomerEmail(?string $v): self { $this->customer_email = $v; return $this; }

    public function getCustomerPhone(): string { return $this->customer_phone; }
    public function setCustomerPhone(string $v): self { $this->customer_phone = $v; return $this; }

    public function getOrderType(): string { return $this->order_type; }
    public function setOrderType(string $v): self { $this->order_type = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(\DateTimeInterface $d): self { $this->created_at = $d; return $this; }

    public function getUserAgent(): ?string { return $this->user_agent; }
    public function setUserAgent(?string $v): self { $this->user_agent = $v; return $this; }

    public function getIpAddress(): ?string { return $this->ip_address; }
    public function setIpAddress(?string $v): self { $this->ip_address = $v; return $this; }
}

