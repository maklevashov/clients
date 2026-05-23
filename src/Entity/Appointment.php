<?php

namespace App\Entity;

use App\Repository\AppointmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
class Appointment
{
    // Статусы записи
    public const STATUS_WAITING = 'waiting';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CAME = 'came';
    public const STATUS_NOT_CAME = 'not_came';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $appointmentDate = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'waiting'])]
    private string $status = self::STATUS_WAITING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $services = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $products = [];

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $totalPrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $deposit = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cameAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_WAITING;
        $this->services = [];
        $this->products = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;
        return $this;
    }

    public function getAppointmentDate(): ?\DateTimeInterface
    {
        return $this->appointmentDate;
    }

    public function setAppointmentDate(\DateTimeInterface $appointmentDate): static
    {
        $this->appointmentDate = $appointmentDate;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;
        $endTime = clone $startTime;
        $endTime->modify('+3 hours');
        $this->endTime = $endTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        if ($status === self::STATUS_CONFIRMED) {
            $this->confirmedAt = new \DateTimeImmutable();
        } elseif ($status === self::STATUS_CAME) {
            $this->cameAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getServices(): ?array
    {
        return $this->services;
    }

    public function setServices(?array $services): static
    {
        $this->services = $services;
        $this->recalculateTotal();
        return $this;
    }

    public function addService(array $service): static
    {
        $this->services[] = $service;
        $this->recalculateTotal();
        return $this;
    }

    public function removeService(int $index): static
    {
        if (isset($this->services[$index])) {
            array_splice($this->services, $index, 1);
            $this->recalculateTotal();
        }
        return $this;
    }

    public function getProducts(): ?array
    {
        return $this->products;
    }

    public function setProducts(?array $products): static
    {
        $this->products = $products;
        $this->recalculateTotal();
        return $this;
    }

    public function addProduct(array $product): static
    {
        $this->products[] = $product;
        $this->recalculateTotal();
        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function getDeposit(): ?float
    {
        return $this->deposit;
    }

    public function setDeposit(?float $deposit): static
    {
        $this->deposit = $deposit;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getCameAt(): ?\DateTimeImmutable
    {
        return $this->cameAt;
    }

    private function recalculateTotal(): void
    {
        $total = 0;

        foreach ($this->services as $service) {
            $total += $service['price'] ?? 0;
        }

        foreach ($this->products as $product) {
            $total += ($product['price'] ?? 0) * ($product['quantity'] ?? 1);
        }

        $this->totalPrice = $total;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_WAITING => 'Ожидание',
            self::STATUS_CONFIRMED => 'Подтвердил',
            self::STATUS_CAME => 'Пришёл',
            self::STATUS_NOT_CAME => 'Не пришёл',
            self::STATUS_CANCELLED => 'Отменён',
            default => 'Ожидание'
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_WAITING => '#FF9500',
            self::STATUS_CONFIRMED => '#34C759',
            self::STATUS_CAME => '#007AFF',
            self::STATUS_NOT_CAME => '#FF3B30',
            self::STATUS_CANCELLED => '#8E8E93',
            default => '#FF9500'
        };
    }
}