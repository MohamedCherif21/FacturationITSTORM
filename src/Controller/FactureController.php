<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Entity\Prestataire;
use App\Form\FactureType;
use App\Form\FactureLigneType;
use App\Repository\FactureRepository;
use App\Repository\SoldeCongeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Service;
use App\Entity\LigneFacture;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/facture')]
class FactureController extends AbstractController
{
    #[Route('/', name: 'app_facture_index', methods: ['GET'])]
    public function index(FactureRepository $FactureRepository): Response
    {
        return $this->render('facture/index.html.twig', [
            'factures' => $FactureRepository->findAll(),
        ]);
    }

    #[Route('/impayee', name: 'app_factureimpayee_index', methods: ['GET'])]
    public function indeximpaye(FactureRepository $FactureRepository): Response
    {
        return $this->render('facture/indeximpayee.html.twig', [
            'factures' => $FactureRepository->findNonPayeeFactures(),
        ]);
    }


    #[Route('/new', name: 'app_facture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, FactureRepository $factureRepository): Response
    {
     $facture = new Facture();
        
     $lastFactureNumber = $this->getDoctrine()->getRepository(Facture::class)->getLastFactureNumber();
     $newFactureNumber = $lastFactureNumber + 1;

     $facture->setNumFacture('FAC/' . date('Y') . '/' . str_pad($newFactureNumber, 6, '0', STR_PAD_LEFT));
    //  $facture->setDateFacturation(new \DateTime());
    $facture->setEtat("ouvert");
     $form = $this->createForm(FactureType::class, $facture);
     $form->handleRequest($request);

     if ($form->isSubmitted() && $form->isValid()) {

         $entityManager = $this->getDoctrine()->getManager();
         $entityManager->persist($facture);
         $entityManager->flush();

        //  return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
        return $this->redirectToRoute('app_facture_addline', ['id' => $facture->getId()]);
       }

     return $this->render('facture/new.html.twig', [
         'facture' => $facture,
         'form' => $form->createView(),
     ]);
 }
     
 #[Route('/addfactureline/{id}', name: 'app_facture_addline', methods: ['GET', 'POST'])]
 public function addFactureLine(Request $request, Facture $facture): Response
 {
     $ligneFacture = new LigneFacture();
     $form = $this->createForm(FactureLigneType::class, $ligneFacture);
     $form->handleRequest($request);
     $editfacture = $this->createForm(FactureType::class, $facture);
     $editfacture->handleRequest($request);
 
     if ($form->isSubmitted() && $form->isValid()) {
         // Ajoutez la ligne de facture à la facture

         $services = $ligneFacture->getService();
         $prestataire = $ligneFacture->getPrestataire();
         $ligneFacture->setNbJours($prestataire->getNbJours());
         $ligneFacture->setPrixUnitaire($services->getPrixUnitaireHT());
         $ligneFacture->setmontantTotalHT($ligneFacture->getPrixUnitaire()*$ligneFacture->getNbJours());
         $ligneFacture->setDescription($services->getDescription());

         $facture->addLigneFacture($ligneFacture);

      

         $facture->setTotalTaxe($facture->calculerTotalTaxeTVA());
         $facture->setTotalTTC($facture->calculerTotalTTC());
         
         
 
         // Persistez la facture mise à jour dans la base de données
         $entityManager = $this->getDoctrine()->getManager();
         $entityManager->persist($facture);
         $entityManager->flush();
 
       // Traitez la requête AJAX et renvoyez la réponse au format JSON
        if ($request->isXmlHttpRequest()) {
            $ligneFactureId = $ligneFacture->getId();
            $response = [
                'id' => $ligneFactureId,
                'service' => $ligneFacture->getService(),
                'prestataire' => $ligneFacture->getPrestataire(),
                'nbJours' => $ligneFacture->getNbJours(),
                'prixUnitaire' => $ligneFacture->getPrixUnitaire(),
                'taxeTVA' => $ligneFacture->getTaxeTVA(),
                'montantTotalHT' => $ligneFacture->getMontantTotalHT(),
            ];

            return $this->json($response);
        }
     }
 
     return $this->render('ligne_facture/addfactureline.html.twig', [
         'facture' => $editfacture->createView(),
         'form' => $form->createView(),
         'lfacture'=>$facture
        
     ]);
 }

    

    

    #[Route('/{id}', name: 'app_facture_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        return $this->render('facture/show.html.twig', [
            'facture' => $facture,
        ]);
    }



    #[Route('/{id}', name: 'app_facture_delete', methods: ['POST'])]
    public function delete(Request $request, Facture $facture, FactureRepository $factureRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$facture->getId(), $request->request->get('_token'))) {
            $factureRepository->remove($facture, true);
        }

        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/line/{id}', name: 'delete_ligne_facture', methods: ['POST'])]
    public function deleteLigneFacture(Request $request, int $id): JsonResponse
    {
        $entityManager = $this->getDoctrine()->getManager();
        
        // Récupérer la ligne de facture à supprimer
        $ligneFacture = $entityManager->find(LigneFacture::class, $id);

        // Vérifier si la ligne de facture existe
        if ($ligneFacture) {
            // Supprimer la ligne de facture de la base de données
            $entityManager->remove($ligneFacture);
            $entityManager->flush();

            // Réponse JSON pour indiquer que la suppression a réussi
            return new JsonResponse(['message' => 'La ligne de facture a été supprimée avec succès'], JsonResponse::HTTP_OK);
        }

        // Réponse JSON pour indiquer que la ligne de facture n'a pas été trouvée
        return new JsonResponse(['error' => 'La ligne de facture n\'a pas été trouvée'], JsonResponse::HTTP_NOT_FOUND);
    }
}
