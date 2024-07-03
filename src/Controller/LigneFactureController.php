<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Service\LigneFactureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LigneFactureController extends AbstractController
{

    private $lignefactureService;

    public function __construct(LigneFactureService $lignefactureService)
    {
        $this->lignefactureService = $lignefactureService;
    }

    #[Route('/addfactureline/{id}', name: 'app_facture_addline', methods: ['GET', 'POST'])]
    public function addFactureLine( Facture $facture): Response
    {
        $result = $this->lignefactureService->addFactureLine($facture);

        if ($result['isAjax']) {
            return $this->json($result['response']);
        }

        return $this->render('ligne_facture/addfactureline.html.twig', [
            'facture' => $result['factureForm'],
            'form' => $result['form'],
            'lfacture' => $result['facture'],
            'formemail' => $result['formemail'],
        ]);
    }

    
    #[Route('/line/{id}', name: 'delete_ligne_facture', methods: ['POST'])]
    public function deleteLigneFacture(int $id): JsonResponse
    {
        $success = $this->lignefactureService->deleteLigneFacture($id);

        if ($success) {
            return new JsonResponse(['message' => 'La ligne de facture a été supprimée avec succès'], JsonResponse::HTTP_OK);
        }

        return new JsonResponse(['error' => 'La ligne de facture n\'a pas été trouvée'], JsonResponse::HTTP_NOT_FOUND);
    }
}
