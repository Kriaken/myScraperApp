<?php

namespace App\Entity;

use App\Repository\CompaniesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompaniesRepository::class)]
class Companies
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column]
    private ?int $registrationCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyVAT = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $companyAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $companyPhone = null;

    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    private ?array $companyTurnover = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getRegistrationCode(): ?int
    {
        return $this->registrationCode;
    }

    public function setRegistrationCode(int $registrationCode): static
    {
        $this->registrationCode = $registrationCode;

        return $this;
    }

    public function getCompanyVAT(): ?string
    {
        return $this->companyVAT;
    }

    public function setCompanyVAT(?string $companyVAT): static
    {
        $this->companyVAT = $companyVAT;

        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(?string $companyAddress): static
    {
        $this->companyAddress = $companyAddress;

        return $this;
    }

    public function getCompanyPhone(): ?string
    {
        return $this->companyPhone;
    }

    public function setCompanyPhone(?string $companyPhone): static
    {
        $this->companyPhone = $companyPhone;

        return $this;
    }

    public function getCompanyTurnover(): ?array
    {
        return $this->companyTurnover;
    }

    public function setCompanyTurnover(?array $companyTurnover): static
    {
        $this->companyTurnover = $companyTurnover;

        return $this;
    }
}
