<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Entity\LigneFacture;
use App\Form\FactureLigneType;
use App\Form\FactureType;
use App\Form\PdfwithdateType;
use App\Repository\FactureRepository;
use App\Service\PdfExtractorService;
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

#[Route('/facture')]
class FactureController extends AbstractController
{
    #[Route('/', name: 'app_facture_index', methods: ['GET', 'POST'])]
    public function index(Request $request, FactureRepository $factureRepository, PdfExtractorService $pdfExtractor): Response
    {
        $form = $this->createForm(PdfwithdateType::class);
        $form->handleRequest($request);
    
        $startDate = null;
        $endDate = null;
        $factures = [];
    
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $startDate = $data['startDate'];
            $endDate = $data['endDate'];
    
            if ($request->request->has('verifier')) {
                $pdfFile = $form->get('pdfFile')->getData();
    
                // Vérifier si un fichier a été téléchargé
                if ($pdfFile) {
                    // Vérifier si le fichier est un PDF
                    if ($pdfFile->getClientOriginalExtension() !== 'pdf') {
                        // Afficher un message d'erreur si le fichier n'est pas un PDF
                        $this->addFlash('error', 'Le fichier téléchargé doit être un fichier PDF.');
                        return $this->redirectToRoute('app_facture_index');
                    }
    
                    try {
                        // Générer un nom de fichier unique
                        $fileName = uniqid().'.'.$pdfFile->guessExtension();
    
                        // Déplacer le fichier vers le répertoire temporaire
                        $pdfFile->move(
                            $this->getParameter('pdf_directory'),
                            $fileName
                        );
    
                        // Extraction du texte du PDF
                        $pdfFilePath = $this->getParameter('pdf_directory').'/'.$fileName;
                        $text = $pdfExtractor->extractText($pdfFilePath);
    
                        unlink($pdfFilePath);
    
                        // Rediriger vers la route pour traiter toutes les factures entre les dates fournies
                        return $this->redirectToRoute('process_extracted_textall', [
                            'extractedText' => $text,
                            'start_date' => $startDate->format('Y-m-d'),
                            'end_date' => $endDate->format('Y-m-d'),
                        ]);
    
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Une erreur s\'est produite lors du téléchargement du fichier.');
                    }
                }
            } elseif ($request->request->has('filtrer')) {
                if ($startDate && $endDate) {
                    $startDateObj = new \DateTime($startDate->format('Y-m-d'));
                    $endDateObj = new \DateTime($endDate->format('Y-m-d'));
                    $factures = $factureRepository->findByDateRange($startDateObj, $endDateObj);
                } else {
                    // Si les dates ne sont pas fournies, afficher toutes les factures
                    $factures = $factureRepository->findAll();
                }
            }
        } else {
            // Si le formulaire n'est pas soumis ou n'est pas valide, afficher toutes les factures
            $factures = $factureRepository->findAll();
        }
    
        return $this->render('facture/index.html.twig', [
            'factures' => $factures,
            'form' => $form->createView(),
            'startDate' => $startDate,
            'endDate' => $endDate,
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
    public function new (Request $request, FactureRepository $factureRepository): Response
    {
        $facture = new Facture();

        $lastFactureNumber = $this->getDoctrine()->getRepository(Facture::class)->getLastFactureNumber();
        $newFactureNumber = $lastFactureNumber + 1;

        $facture->setNumFacture('FAC/' . date('Y') . '/' . str_pad($newFactureNumber, 6, '0', STR_PAD_LEFT));
        //  $facture->setDateFacturation(new \DateTime());
        $facture->setEtat("ouverte");
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
            $ligneFacture->setmontantTotalHT($ligneFacture->getPrixUnitaire() * $ligneFacture->getNbJours());
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
                    // 'prixUnitaire' =>  $ligneFacture->getService()->getPrixUnitaireHT(),
                    'taxeTVA' => $ligneFacture->getTaxeTVA(),
                    'montantTotalHT' => $ligneFacture->getMontantTotalHT(),
                ];

                return $this->json($response);
            }
        }

        return $this->render('ligne_facture/addfactureline.html.twig', [
            'facture' => $editfacture->createView(),
            'form' => $form->createView(),
            'lfacture' => $facture,

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

    #[Route('/Setmanuelp/{id}', name: 'set_facture_payée', methods: ['GET'])]
    public function setfacturepayee(Facture $facture): Response
    {
        $facture->SetEtat('payée');
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($facture);
        $entityManager->flush();
        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/Setmanuelnp/{id}', name: 'set_facture_nonpayée', methods: ['GET'])]
    public function setfacturenonpayee(Facture $facture): Response
    {
        $facture->SetEtat('non-payée');
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($facture);
        $entityManager->flush();
        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
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
        '<TotalTaxe>' . htmlspecialchars($facture->getTotalTaxe()) . '</TotalTaxe>' . "\n";

        // Ajouter les lignes de facture
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

    #[Route('/{id}', name: 'app_facture_delete', methods: ['POST'])]
    public function delete(Request $request, Facture $facture, FactureRepository $factureRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $facture->getId(), $request->request->get('_token'))) {
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
        $this->addFlash('success', 'La facture ' . $facture->getNumfacture() . ' est envoyée avec succés à ' . $facture->getClient()->getNom());

        return $this->redirectToRoute('app_facture_index');
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

    #[Route('/verify-factures', name: 'verify_factures')]
    public function verifierfacture(FactureRepository $factureRepository): Response
    {
        $factures = $factureRepository->findAll();
        $dateNow = new \DateTime();
        foreach ($factures as $facture) {
            if ($facture->getDateEcheance() < $dateNow) {
                if ($facture->getEtat() !== 'payée') {
                    $facture->setEtat('non-payée');
                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->persist($facture);
                }
            }
        }
        $entityManager->flush();

        $this->addFlash('success', 'La vérification des factures a été effectuée avec succès.');
        return $this->redirectToRoute('app_facture_index');
    }

}
