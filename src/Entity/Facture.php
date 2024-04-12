<?php

namespace App\Entity;

use App\Repository\FactureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\LigneFacture;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]   
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotBlank (message : "vous devez indiquer le numéro de facture")]
    private ?string $numFacture = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank (message : "la date de facturation est obligatoire")]
    private ?\DateTimeInterface $dateFacturation = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank (message : "la date d'échéance est obligatoire")]
    private ?\DateTimeInterface $dateEcheance = null;


    #[ORM\ManyToOne(inversedBy: 'facture')]
    #[ORM\JoinColumn(onDelete:"CASCADE") ]
    private ?Client $client = null;




    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['ouvert', 'fermé'])]
    private ?string $etat = 'ouvert'; 


    #[ORM\Column(type: "float", nullable: true)]
    private ?float $totalTTC = null;


    #[ORM\Column(type: "float", nullable: true)]
    private ?float $totaltaxe = null;

    #[ORM\OneToMany(mappedBy: 'facture', targetEntity: LigneFacture::class, cascade: ['persist', 'remove'])]
    private Collection $lignesFacture;

    public function __construct()
    {
        $this->lignesFacture = new ArrayCollection();
    }

    public function getLignesFacture(): Collection
    {
        return $this->lignesFacture;
    }

    public function addLigneFacture(LigneFacture $ligneFacture): self
    {
        if (!$this->lignesFacture->contains($ligneFacture)) {
            $this->lignesFacture[] = $ligneFacture;
            $ligneFacture->setFacture($this);
        }
        return $this;
    }

    public function removeLigneFacture(LigneFacture $ligneFacture): self
    {
        if ($this->lignesFacture->removeElement($ligneFacture)) {
            // set the owning side to null (unless already changed)
            if ($ligneFacture->getFacture() === $this) {
                $ligneFacture->setFacture(null);
            }
        }
        return $this;
    }


    public function getTotalTaxe(): ?float
    {
        return $this->totaltaxe;
    }

    public function setTotalTaxe(?float $totaltaxe): self
    {
        $this->totaltaxe = $totaltaxe;

        return $this;
    }

    public function getTotalTTC(): ?float
    {
        return $this->totalTTC;
    }

    public function setTotalTTC(?float $totalTTC): self
    {
        $this->totalTTC = $totalTTC;

        return $this;
    }




    public function getId(): ?int
    {
        return $this->id;
    }
    public function getNumFacture(): ?string
    {
        return $this->numFacture;
    }

    public function setNumFacture(string $numFacture): self
    {
        $this->numFacture = $numFacture;

        return $this;
    }

    public function getDateFacturation(): ?\DateTimeInterface
    {
        return $this->dateFacturation;
    }

    public function setDateFacturation(\DateTimeInterface $dateFacturation): self
    {
        $this->dateFacturation = $dateFacturation;

        return $this;
    }

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(\DateTimeInterface $dateEcheance): self
    {
        $this->dateEcheance = $dateEcheance;

        return $this;
    }

   


    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;

        return $this;
    }


    public function calculerTotalTTC(): ?float
    {
        if ($this->lignesFacture->isEmpty()) {
            return null;
        }
    
        $totalTTC = 0;
    
        foreach ($this->lignesFacture as $ligneFacture) {
            $montantTotalHT = $ligneFacture->getMontantTotalHT();
            $montantTVA = ($montantTotalHT *  $ligneFacture->getTaxeTVA()) / 100;
            $totalTTC += $montantTotalHT + $montantTVA;
        }
    
        return $totalTTC;
    }

    public function calculerTotalTaxeTVA(): ?float
    {
        if ($this->lignesFacture->isEmpty()) {
            return null;
        }

        $totalTaxeTVA = 0;

        foreach ($this->lignesFacture as $ligneFacture) {
            $montantTotalHT = $ligneFacture->getMontantTotalHT();
            $montantTVA = ($montantTotalHT * $ligneFacture->getTaxeTVA()) / 100;
            $totalTaxeTVA += $montantTVA;
        }

        return $totalTaxeTVA;
    }   

    


    
}
