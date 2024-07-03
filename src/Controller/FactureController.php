<?php

namespace App\Controller;

use App\Entity\EmailTemplate;
use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Form\EmailTemplateType;
use App\Form\FactureLigneType;
use App\Form\FactureType;
use App\Form\PdfwithdateType;
use App\Repository\FactureRepository;
use App\Service\PdfExtractorService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Swift_Image;
use Swift_Message;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FactureService;


#[Route('/facture')]
class FactureController extends AbstractController
{

    private $factureService;

    public function __construct(FactureService $factureService)
    {
        $this->factureService = $factureService;
    }

    #[Route('/verify-factures', name: 'verify_factures')]
    public function verifierfacture(): Response
    {
        $this->factureService->verifierFacturesNonPayees();

        $this->addFlash('info', 'La vérification des factures a été effectuée.');
        return $this->redirectToRoute('app_facture_index');
    }


    #[Route('/new', name: 'app_facture_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        $result = $this->factureService->createFacture();

        if ($result instanceof Facture) {
            return $this->redirectToRoute('app_facture_addline', ['id' => $result->getId()]);
        }

        return $this->render('facture/new.html.twig', [
            'facture' => $result['facture'],
            'form' => $result['form']->createView(),
        ]);
    }


    #[Route('/', name: 'app_facture_index', methods: ['GET', 'POST'])]
    public function index(Request $request,FactureRepository $factureRepository): Response
    {
        $emailTemplate = new EmailTemplate();
        $formemail = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $formemail->handleRequest($request);
        $this->factureService->EmailTemplateForm($formemail);

        $form = $this->createForm(PdfwithdateType::class);
        $form->handleRequest($request);
        $factureData = $this->factureService->FactureForm($form);

        if ($factureData['redirect']) {
            return $this->redirectToRoute($factureData['route'], $factureData['params']);
        }

        return $this->render('facture/index.html.twig', [
            'factures' => $factureData['factures'],
            'form' => $form->createView(),
            'formemail' => $formemail->createView(),
            'startDate' => $factureData['startDate'],
            'endDate' => $factureData['endDate'],
            'facturespayee' => $factureRepository->findPayeeFactures(),
            'facturesimpayee' => $factureRepository->findNonPayeeFactures(),
        ]);
    }



    #[Route('/get-facture-details/{id}', name: 'get_facture_details', methods: ['GET'])]
    public function getFactureDetails(Facture $facture): JsonResponse
    {
        $response = $this->factureService->getFactureDetails($facture);
        return new JsonResponse($response);
    }

    #[Route('/{id}', name: 'app_facture_show', methods: ['GET'])]
    public function showfacture(Facture $facture): Response
    {
        return $this->render('facture/show.html.twig', [
            'facture' => $facture,
        ]);
    }


    #[Route('/Setmanuelp/{id}', name: 'set_facture_payée', methods: ['GET'])]
    public function setfacturepayee(Facture $facture): Response
    {
        $this->factureService->setFacturePayee($facture);
        
        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/Setmanuelnp/{id}', name: 'set_facture_nonpayée', methods: ['GET'])]
    public function setfacturenonpayee(Facture $facture): Response
    {
        $this->factureService->setFactureNonPayee($facture);
        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
    }

 

    #[Route('/{id}', name: 'app_facture_delete', methods: ['POST'])]
    public function delete(Request $request, Facture $facture): Response
    {
        if ($this->isCsrfTokenValid('delete' . $facture->getId(), $request->request->get('_token'))) {
            $this->factureService->deleteFacture($facture);
        }

        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
    }



    #[Route('/sendemail/{id}', name: 'send_facture_email', methods: ['GET', 'POST'])]
    public function sendemail(Facture $facture, \Swift_Mailer $mailer, EntityManagerInterface $entityManager)
    {
        $emailTemplateRepository = $entityManager->getRepository(EmailTemplate::class);

        // Obtenez l'emailTemplate en fonction de son type
        $emailPremierEnvoie = $emailTemplateRepository->findById(1);
        $emailAutre = $emailTemplateRepository->findById(3);
        $emailRelance = $emailTemplateRepository->findById(2);

        if ($facture->getEtat() == 'ouverte') {
            $emailaenvoyer = $emailPremierEnvoie;
        } else if ($facture->getEtat() == 'envoyée') {
            $emailaenvoyer = $emailRelance;
        } else {
            $emailaenvoyer = $emailAutre;
        }

        // Configuration de Dompdf
        $options = new Options();
        $options->set('isPhpEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        // Génération du PDF
        $html = $this->renderView('facture/efacture.html.twig', ['facture' => $facture]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        $pdfAttachment = new \Swift_Attachment($pdfContent, 'facture.pdf', 'application/pdf');

        // Préparation du message
        $message = (new \Swift_Message())
            ->setSubject($emailaenvoyer->getSubject())
            ->setFrom('cherifmouhamed9242@yahoo.fr')
            ->setTo('cherifmouhamed123@gmail.com')
            ->setBody($emailaenvoyer->getBody(), 'text/html')
            ->attach($pdfAttachment);

        // Envoi de l'email
        try {
            $mailer->send($message);
            $this->addFlash('success', 'La facture a été envoyée par e-mail avec succès.');
            $facture->setEtat("envoyée");
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Une erreur s\'est produite lors de l\'envoi de la facture par e-mail.');
        }

        // Mise à jour de la facture et affichage du message de succès
        $entityManager->persist($facture);
        $entityManager->flush();

        $this->addFlash('success', 'La facture ' . $facture->getNumfacture() . ' est envoyée avec succès à ' . $facture->getClient()->getNom());

        return $this->redirectToRoute('app_facture_index');
    }

    // #[Route('/email_template', name: 'get_email_template' , methods: ['POST'])]
    // public function getEmailTemplate(Request $request)
    // {

    //     $templateName = $request->request->get('templateName');
    //     $montant = $request->request->get('montant');

    //     $templateMap = [
    //         'relaunch' => 'emailtemplate/email_relance.html.twig',
    //         'first_send' => 'emailtemplate/email_premierenvoie.html.twig',

    //     ];

    //     if (!array_key_exists($templateName, $templateMap)) {
    //         throw new NotFoundHttpException("Template not found");
    //     }

    //     $html = $this->renderView($templateMap[$templateName], ['montant' => $montant]);

    //     return new JsonResponse(['html' => $html]);
    // }

//facture-X

    #[Route('/x/{id}', name: 'app_efacturx_show', methods: ['GET'])]
    public function showfacturx(Facture $facture): Response
    {
        $xmlContent = $this->generateXmlContent($facture);
        $svgContent = $this->generateSvgContent($xmlContent);

        // Afficher la facture avec le contenu SVG
        return $this->render('facture/efactur-x.html.twig', [
            'svgContent' => $svgContent,
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
        '<TotalTaxe>' . htmlspecialchars($facture->getTotalTaxe()) . '</TotalTaxe>' . "\n";

        // les lignes de facture
        foreach ($facture->getLignesFacture() as $ligneFacture) {
            $xmlContent .= '<LigneFacture>' . "\n";
            $xmlContent .= '<Description>' . htmlspecialchars($ligneFacture->getDescription()) . '</Description>' . "\n";
            $xmlContent .= '<NbJours>' . htmlspecialchars($ligneFacture->getNbJours()) . '</NbJours>';
            $xmlContent .= '<PrixUnitaire>' . htmlspecialchars($ligneFacture->getPrixUnitaire()) . '</PrixUnitaire>';
            $xmlContent .= '<TotalHT>' . htmlspecialchars($ligneFacture->getmontantTotalHT()) . '</TotalHT>' . "\n";
            $xmlContent .= '</LigneFacture>' . "\n";
        }

        $xmlContent .= '</Facturexml>';

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

        $svgContent = '<svg width="600" height="1000" xmlns="http://www.w3.org/2000/svg">' . "\n" .
            '<text x="10" y="20">' . $textContent . '</text>' . "\n" .
            '</svg>';

        return $svgContent;
    }

    #[Route('/sendfacturx/{id}', name: 'send_facturx_email', methods: ['GET', 'POST'])]
    public function sendemailxfactur($id, Request $request, Facture $facture, \Swift_Mailer $mailer, EntityManagerInterface $entityManager): Response
    {
        $xmlContent = $this->generateXmlContent($facture);
        $svgContent = $this->generateSvgContent($xmlContent);

        $options = new Options();
        $options->set('isPhpEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->renderView('facture/efactur-x.html.twig', ['facture' => $facture, 'svgContent' => $svgContent]);
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
        <br>
        <p>Veuillez trouver ci-joint les factures liées à nos prestations, pour la période indiquée dans l\'objet de ce mail.</p>
        <p>En attendant, nous restons à votre disposition pour tout complément d\'information.</p>
        <br>
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
            $entityManager->persist($facture);
            $entityManager->flush();
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Une erreur s\'est produite lors de l\'envoi de la facture par e-mail.');
        }

        return $this->redirectToRoute('app_facture_show', ['id' => $facture->getId()]);
    }

}
