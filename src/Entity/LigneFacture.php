<?php

namespace App\Entity;

use App\Repository\FactureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
class LigneFacture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?string $description = null;


    #[ORM\Column(type: "float", nullable: true)]
    private ?float $prixUnitaire = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $montantTotalHT = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $taxeTVA = null;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $nb_jours = null;

    #[ORM\ManyToOne(targetEntity: Facture::class, inversedBy: 'lignesFacture')]
    #[ORM\JoinColumn(name: 'facture_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Facture $facture= null;

    
    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(name: 'service_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Service $service= null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(name: 'prestataire_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Prestataire $prestataire= null ;


    public function getPrestataire(): ?Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(?Prestataire $prestataire): self
    {
        $this->prestataire = $prestataire;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function getNbJours(): ?int
    {
        return $this->nb_jours;
    }

    public function setNbJours(int $nb_jours): self
    {
        $this->nb_jours = $nb_jours;

        return $this;
    }

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): self
    {
        $this->facture = $facture;
        return $this;
    }



    public function getTaxeTVA(): ?float
    {
        return $this->taxeTVA;
    }

    public function setTaxeTVA(?float $taxeTVA): self
    {
        $this->taxeTVA = $taxeTVA;

        return $this;
    }


    public function getPrixUnitaire(): ?float
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(?float $prixUnitaire): self
    {
        $this->prixUnitaire = $prixUnitaire;

        return $this;
    }

    public function getmontantTotalHT(): ?float
    {
        return $this->montantTotalHT;
    }

    public function setmontantTotalHT(?float $montantTotalHT): self
    {
        $this->montantTotalHT = $montantTotalHT;

        return $this;
    }


    public function getId(): ?int
    {
        return $this->id;
    }
   

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
   
    

}
