<?php


namespace App\Service;

use App\Entity\EmailTemplate;
use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Form\EmailTemplateType;
use App\Form\FactureLigneType;
use App\Form\FactureType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class LigneFactureService
{
    private $entityManager;
    private $requestStack;
    private $formFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        FormFactoryInterface $formFactory,
    ) {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->formFactory = $formFactory;
    }

    public function addFactureLine(Facture $facture)
    {
        $request = $this->requestStack->getCurrentRequest();

        $ligneFacture = new LigneFacture();
        $form = $this->formFactory->create(FactureLigneType::class, $ligneFacture);
        $form->handleRequest($request);

        $editfacture = $this->formFactory->create(FactureType::class, $facture);
        $editfacture->handleRequest($request);

        $emailTemplate = new EmailTemplate();
        $formemail = $this->formFactory->create(EmailTemplateType::class, $emailTemplate);
        $formemail->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les objets Service et Prestataire depuis le formulaire
            $service = $ligneFacture->getService();
            $prestataire = $ligneFacture->getPrestataire();

            // Mettre à jour l'objet LigneFacture avec les données du service et du prestataire
            $ligneFacture->setNbJours($prestataire->getNbJours());
            $ligneFacture->setPrixUnitaire($service->getPrixUnitaireHT());
            $ligneFacture->setMontantTotalHT($ligneFacture->getPrixUnitaire() * $ligneFacture->getNbJours());
            $ligneFacture->setDescription($service->getDescription());

            // Ajouter la ligne de facture à la facture
            $facture->addLigneFacture($ligneFacture);

            // Mettre à jour les totaux de la facture
            $facture->setTotalTaxe($facture->calculerTotalTaxeTVA());
            $facture->setTotalTTC($facture->calculerTotalTTC());

            // Persister et enregistrer les modifications
            $this->entityManager->persist($facture);
            $this->entityManager->flush();

            // Si la requête est une requête AJAX, renvoyer une réponse JSON
            if ($request->isXmlHttpRequest()) {
                $ligneFactureId = $ligneFacture->getId();
                return [
                    'isAjax' => true,
                    'response' => [
                        'id' => $ligneFactureId,
                        'service' => [
                            'description' => $ligneFacture->getService()->getDescription(),
                        ],
                        'prestataire' => [
                            'nom' => $ligneFacture->getPrestataire()->getNom(),
                        ],
                        'nbJours' => $ligneFacture->getNbJours(),
                        'prixUnitaire' => $ligneFacture->getPrixUnitaire(),
                        'taxeTVA' => $ligneFacture->getTaxeTVA(),
                        'montantTotalHT' => $ligneFacture->getMontantTotalHT(),
                    ],
                ];
            }
        }

        return [
            'isAjax' => false,
            'factureForm' => $editfacture->createView(),
            'form' => $form->createView(),
            'facture' => $facture,
            'formemail' => $formemail->createView(),
        ];
    }

    public function deleteLigneFacture(int $id): bool
    {
        $ligneFacture = $this->entityManager->find(LigneFacture::class, $id);

        if ($ligneFacture) {
            $this->entityManager->remove($ligneFacture);
            $this->entityManager->flush();
            return true;
        }

        return false;
    }
}