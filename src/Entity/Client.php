<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom= null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;


    #[ORM\Column(length: 255)]
    private ?string $numtel = null;

    #[ORM\Column(length: 255)]
    private ?string $adresse = null;

    #[ORM\Column(length: 255)]
    private ?string $siret  = null;

    #[ORM\Column(length: 255)]
    private ?string $contrat = null;

    #[ORM\Column(length: 255)]
    private ?string $referencebancaire = null;

    #[ORM\Column(type:"string", length:2)]
    private ?string $pays = null;



    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Facture::class)]
    private Collection $facture;


    public function __toString()
    {
        return $this -> nom ;
    }

   
    public function __construct()
    {
        //$this->soldeConges = new ArrayCollection();
        $this->facture = new ArrayCollection();
        
    }
    /**
     * @return Collection|Facture[]
     */
    public function getFactures(): Collection
    {
        return $this->facture;
    }

    public function addFacture(Facture $facture): self
    {
        if (!$this->facture->contains($facture)) {
            $this->facture[] = $facture;
            $facture->setClient($this);
        }

        return $this;
    }

    public function removeFacture(Facture $facture): self
    {
        if ($this->facture->removeElement($facture)) {
            // set the owning side to null (unless already changed)
            if ($facture->getClient() === $this) {
                $facture->setClient(null);
            }
        }

        return $this;
    }


    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(string $pays): self
    {
        $this->pays = $pays;

        return $this;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
    public function getNumtel(): ?string
    {
        return $this->numtel;
    }

    public function setNumtel(string $numtel): self
    {
        $this->numtel = $numtel;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): self
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getContrat(): ?string
    {
        return $this->contrat;
    }

    public function setContrat(string $contrat): self
    {
        $this->contrat = $contrat;

        return $this;
    }

    public function getReferencebancaire(): ?string
    {
        return $this->referencebancaire;
    }

    public function setReferencebancaire(string $referencebancaire): self
    {
        $this->referencebancaire = $referencebancaire;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(string $siret): self
    {
        $this->siret = $siret;

        return $this;
    }


    // /**
    //  * @return Collection<int, SoldeConge>
    //  */
    // public function getSoldeConges(): Collection
    // {
    //     return $this->soldeConges;
    // }

    // public function addSoldeConge(SoldeConge $soldeConge): self
    // {
    //     if (!$this->soldeConges->contains($soldeConge)) {
    //         $this->soldeConges->add($soldeConge);
    //         $soldeConge->setEmploye($this);
    //     }

    //     return $this;
    // }

    // public function removeSoldeConge(SoldeConge $soldeConge): self
    // {
    //     if ($this->soldeConges->removeElement($soldeConge)) {
    //         // set the owning side to null (unless already changed)
    //         if ($soldeConge->getEmploye() === $this) {
    //             $soldeConge->setEmploye(null);
    //         }
    //     }

    //     return $this;
    // }

    // /**
    //  * @return Collection<int, Facture>
    //  */
    // public function getFactures(): Collection
    // {
    //     return $this->factures;
    // }

    // public function addFacture(Facture $facture): self
    // {
    //     if (!$this->factures->contains($facture)) {
    //         $this->factures->add($facture);
    //         $facture->setPrestataire($this);
    //     }

    //     return $this;
    // }

    // public function removeFacture(Facture $facture): self
    // {
    //     if ($this->factures->removeElement($facture)) {
    //         // set the owning side to null (unless already changed)
    //         if ($facture->getPrestataire() === $this) {
    //             $facture->setPrestataire(null);
    //         }
    //     }

    //     return $this;
    // }


}
