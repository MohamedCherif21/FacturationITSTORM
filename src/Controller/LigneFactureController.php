<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LigneFactureController extends AbstractController
{
    #[Route('/ligne/facture', name: 'app_ligne_facture')]
    public function index(): Response
    {
        return $this->render('ligne_facture/index.html.twig', [
            'controller_name' => 'LigneFactureController',
        ]);
    }
}
