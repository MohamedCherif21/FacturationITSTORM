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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle;
use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;
use Swift_Image;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mailer\MailerInterface;
use DOMDocument;
use setasign\Fpdi\Fpdi;
use Spatie\PdfToImage\Pdf;
use Imagick;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\fpdi\src\TcpdfFpdi ;
use TCPDF_TCPDF;
use tecnickcom\FacturX\FacturX;
use tecnickcom\FacturX\XmlGenerator\Xml30Generator;

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

    #[Route('/payee', name: 'app_facturepayee_index', methods: ['GET'])]
    public function indexpayee(FactureRepository $FactureRepository): Response
    {
        return $this->render('facture/indexPayee.html.twig', [
            'factures' => $FactureRepository->findPayeeFactures(),
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

    #[Route('/e/{id}', name: 'app_efacture_show', methods: ['GET'])]
    public function showefacture(Facture $facture): Response
    {
        return $this->render('facture/efacture.html.twig', [
            'facture' => $facture,
        ]);
    }


    #[Route('/x/{id}', name: 'app_efacturx_show', methods: ['GET'])]
    public function showfacturx(Facture $facture): Response
    {
        // Générer le contenu XML et SVG
        $xmlContent = $this->generateXmlContent($facture);
        $svgContent = $this->generateSvgContent($xmlContent);
    
        // Afficher la facture avec le contenu SVG
        return $this->render('facture/efactur-x.html.twig', [
            'svgContent' => $svgContent, // Passer le contenu SVG à la vue
            'facture' => $facture,
        ]);
    }
    
    private function generateXmlContent(Facture $facture): string
    {
        // Générer le contenu XML
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<Facturexml>' . "\n" .
            '<NumFacture>' . htmlspecialchars($facture->getNumFacture()) . '</NumFacture>' . "\n" .
            '<dateFacturation>' . htmlspecialchars($facture->getDateFacturation()->format('Y-m-d')) . '</dateFacturation>' . "\n" .
            '<dateEcheance>' . htmlspecialchars($facture->getDateEcheance()->format('Y-m-d')) . '</dateEcheance>' . "\n" .
            '<client>' . htmlspecialchars($facture->getClient()) . '</client>' . "\n" .
            '<TotalTTC>' . htmlspecialchars($facture->getTotalTTC()) . '</TotalTTC>' . "\n" .
            '<TotalTaxe>' . htmlspecialchars($facture->getTotalTaxe()) . '</TotalTaxe>' . "\n" .
            '</Facturexml>';
    
        return $xmlContent;
    }
    
    private function generateSvgContent(string $xmlContent): string
    {
        // Construction du contenu SVG avec des tspan pour chaque ligne
        $lines = explode("\n", $xmlContent);
        $textContent = '';
        foreach ($lines as $line) {
            $textContent .= '<tspan x="10" dy="1.2em">' . htmlspecialchars($line) . '</tspan>';
        }
    
        $svgContent = '<svg width="500" height="200" xmlns="http://www.w3.org/2000/svg">' . "\n" .
            '<text x="10" y="20">' . $textContent . '</text>' . "\n" .
            '</svg>';
    
        return $svgContent;
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
        
        $ligneFacture = $entityManager->find(LigneFacture::class, $id);

        if ($ligneFacture) {
            $entityManager->remove($ligneFacture);
            $entityManager->flush();

            return new JsonResponse(['message' => 'La ligne de facture a été supprimée avec succès'], JsonResponse::HTTP_OK);
        }

        return new JsonResponse(['error' => 'La ligne de facture n\'a pas été trouvée'], JsonResponse::HTTP_NOT_FOUND);
    }
    


    #[Route('/sendemail/{id}', name: 'send_facture_email', methods: ['GET', 'POST'])]
    public function sendemail($id, Request $request, Facture $facture, \Swift_Mailer $mailer)
{

    $options = new Options();
    $options->set('isPhpEnabled', true); 
    $options->set('isHtml5ParserEnabled', true); 
    $options->set('isRemoteEnabled', true); 

    $dompdf = new Dompdf($options);

    $html = $this->renderView('facture/efacture.html.twig', ['facture' => $facture]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfContent = $dompdf->output();

    $pdfAttachment = new \Swift_Attachment($pdfContent, 'facture.pdf', 'application/pdf');


    $subject = 'Facture ' . $facture->getDateFacturation()->format('F Y') . ' - ' . $facture->getClient()->getNom();

    $imageUrl = 'http://localhost:8000/img/itstormsig.jpg';

    $signatureImage = (new Swift_Image($imageUrl))->setFilename('signature.png');

    $signatureHTML = '
    <p>Bien Cordialement,</p>
    <p style="margin: 0;">Farhat THABET, PhD</p>
    <p style="margin: 0;">Président IT STORM Consulting</p>
    <img src="' . $imageUrl . '" alt="Signature">';

    $messageBody = '
    <p>Bonjour,</p>
    <p>Veuillez trouver ci-joint les factures liées à nos prestations, pour la période indiquée dans l\'objet de ce mail.</p>
    <p>En attendant, nous restons à votre disposition pour tout complément d\'information.</p>
    ' . $signatureHTML;

    $message = (new Swift_Message())
        ->setSubject($subject)
        ->setFrom('cherifmouhamed9242@yahoo.fr')
        ->setTo('cherifmouhamed123@gmail.com')
        ->setBody($messageBody, 'text/html');

    $message->attach($pdfAttachment);


    try {
        $mailer->send($message);
        $this->addFlash('success', 'La facture a été envoyée par e-mail avec succès.');
        $facture->setEtat("envoyée");
        $entityManager = $this->getDoctrine()->getManager();
         $entityManager->persist($facture);
         $entityManager->flush();
    } catch (TransportExceptionInterface $e) {
        $this->addFlash('error', 'Une erreur s\'est produite lors de l\'envoi de la facture par e-mail.');
    }
    
        return $this->redirectToRoute('app_facture_show', ['id' => $facture->getId()]);
    }



    #[Route('/sendfacturx/{id}', name: 'send_facturx_email', methods: ['GET', 'POST'])]
    public function sendemailxfactur($id, Request $request, Facture $facture, \Swift_Mailer $mailer): Response
    {
        $xmlContent = $this->generateXmlContent($facture);
        $svgContent = $this->generateSvgContent($xmlContent);
        
        $options = new Options();
        $options->set('isPhpEnabled', true); 
        $options->set('isHtml5ParserEnabled', true); 
        $options->set('isRemoteEnabled', true); 
    
        $dompdf = new Dompdf($options);
    
        $html = $this->renderView('facture/efactur-x.html.twig', ['facture' => $facture,'svgContent' => $svgContent]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A3', 'landscape');
        $dompdf->render();
        $pdfContent = $dompdf->output();
    
        $pdfAttachment = new \Swift_Attachment($pdfContent, 'facture.pdf', 'application/pdf');
    
    
        $subject = 'Facture ' . $facture->getDateFacturation()->format('F Y') . ' - ' . $facture->getClient()->getNom();
    
        $imageUrl = 'http://localhost:8000/img/itstormsig.jpg';
    
        $signatureImage = (new Swift_Image($imageUrl))->setFilename('signature.png');
    
        $signatureHTML = '
        <p>Bien Cordialement,</p>
        <p style="margin: 0;">Farhat THABET, PhD</p>
        <p style="margin: 0;">Président IT STORM Consulting</p>
        <img src="' . $imageUrl . '" alt="Signature">';
    
        $messageBody = '
        <p>Bonjour,</p>
        <p>Veuillez trouver ci-joint les factures liées à nos prestations, pour la période indiquée dans l\'objet de ce mail.</p>
        <p>En attendant, nous restons à votre disposition pour tout complément d\'information.</p>
        ' . $signatureHTML;
    
        $message = (new Swift_Message())
            ->setSubject($subject)
            ->setFrom('cherifmouhamed9242@yahoo.fr')
            ->setTo('cherifmouhamed123@gmail.com')
            ->setBody($messageBody, 'text/html');
    
        $message->attach($pdfAttachment);
    
    
        try {
            $mailer->send($message);
            $this->addFlash('success', 'La facture a été envoyée par e-mail avec succès.');
            $facture->setEtat("envoyée");
            $entityManager = $this->getDoctrine()->getManager();
             $entityManager->persist($facture);
             $entityManager->flush();
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Une erreur s\'est produite lors de l\'envoi de la facture par e-mail.');
        }
        
            return $this->redirectToRoute('app_facture_show', ['id' => $facture->getId()]);
}

    
    
}
