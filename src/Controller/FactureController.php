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
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/facture')]
class FactureController extends AbstractController
{

    #[Route('/verify-factures', name: 'verify_factures')]
    public function verifierfacture(FactureRepository $factureRepository, \Swift_Mailer $mailer, EntityManagerInterface $entityManager): Response
    {
        $facturesNonPayees = [];
        $factures = $factureRepository->findAll();
        $dateNow = new \DateTime();

        foreach ($factures as $facture) {
            if ($facture->getDateEcheance() < $dateNow && $facture->getEtat() === 'envoyée') {
                $facture->setEtat('non-payée');
                $facturesNonPayees[] = $facture; // Ajout à la liste des factures non payées
                $entityManager->persist($facture);
            }
        }
        $entityManager->flush();

        if (!empty($facturesNonPayees)) {
            $messageBody = $this->renderView('emailtemplate/factures_non_payees.html.twig', [
                'factures' => $facturesNonPayees,
            ]);

            // envoie de l'e-mail
            $message = (new Swift_Message())
                ->setSubject('Factures non payées')
                ->setFrom('cherifmouhamed9242@yahoo.fr')
                ->setTo('cherifmouhamed123@gmail.com')
                ->setBody($messageBody, 'text/html');

            try {
                $mailer->send($message);
                $this->addFlash('success', 'Les factures non payées ont été envoyées par e-mail avec succès.');
            } catch (TransportExceptionInterface $e) {
                $this->addFlash('error', 'Une erreur s\'est produite lors de l\'envoi des factures non payées par e-mail.');
            }
        } else {
            $this->addFlash('info', 'Aucune facture non payée à notifier.');
        }

        return $this->redirectToRoute('app_facture_index');
    }

    #[Route('/', name: 'app_facture_index', methods: ['GET', 'POST'])]
    public function index(Request $request, FactureRepository $factureRepository, PdfExtractorService $pdfExtractor, EntityManagerInterface $entityManager): Response
    {
        $emailTemplate = new EmailTemplate();
        $formemail = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $formemail->handleRequest($request);

        if ($formemail->isSubmitted() && $formemail->isValid()) {
            $entityManager->persist($emailTemplate);
            $entityManager->flush();
        }

        $form = $this->createForm(PdfwithdateType::class);
        $form->handleRequest($request);

        // Initialisation
        $startDate = new \DateTime(date('Y-01-01')); // Début de l'année en cours
        $endDate = new \DateTime(date('Y-m-t')); // Fin du mois en cours
        $factures = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $startDate = $data['startDate'];
            $endDate = $data['endDate'];

            if ($request->request->has('verifier')) {
                $pdfFiles = $data['pdfFiles'];
                $extractedText = '';

                foreach ($pdfFiles as $pdfFile) {
                    /** @var UploadedFile $pdfFile */
                    if ($pdfFile->getClientOriginalExtension() !== 'pdf') {
                        $this->addFlash('error', 'Le fichier téléchargé doit être un fichier PDF.');
                        return $this->redirectToRoute('app_facture_index');
                    }

                    try {
                        $fileName = uniqid() . '.' . $pdfFile->guessExtension();
                        $pdfFile->move(
                            $this->getParameter('pdf_directory'),
                            $fileName
                        );

                        // Extraction du texte du PDF
                        $pdfFilePath = $this->getParameter('pdf_directory') . '/' . $fileName;
                        $extractedText .= $pdfExtractor->extractText($pdfFilePath) . PHP_EOL;

                        // Suppr du fichier temporaire
                        unlink($pdfFilePath);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Une erreur s\'est produite lors du téléchargement du fichier.');
                        return $this->redirectToRoute('app_facture_index');
                    }
                }

                // Redirection vers traitement de toutes les factures entre les dates fournies
                return $this->redirectToRoute('process_extracted_textall', [
                    'extractedText' => $extractedText,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]);
            } elseif ($request->request->has('filtrer')) {
                if ($startDate && $endDate) {
                    $factures = $factureRepository->findByDateRange($startDate, $endDate);
                } else {
                    $factures = $factureRepository->findAll();
                }
            }
        } else {
            $factures = $factureRepository->findAll();
        }

        return $this->render('facture/index.html.twig', [
            'factures' => $factures,
            'form' => $form->createView(),
            'formemail' => $formemail->createView(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'facturespayee' => $factureRepository->findPayeeFactures(),
            'facturesimpayee' => $factureRepository->findNonPayeeFactures(),
            // 'PremierEnvoie' => isset($emailTemplate) ? $emailTemplate : null,
        ]);
    }

    #[Route('/new', name: 'app_facture_new', methods: ['GET', 'POST'])]
    public function new (Request $request, EntityManagerInterface $entityManager): Response
    {
        $facture = new Facture();

        $lastFactureNumber = $entityManager->getRepository(Facture::class)->getLastFactureNumber();
        $newFactureNumber = $lastFactureNumber + 1;

        $facture->setNumFacture('FAC/' . date('Y') . '/' . str_pad($newFactureNumber, 6, '0', STR_PAD_LEFT));
        //  $facture->setDateFacturation(new \DateTime());
        $facture->setEtat("ouverte");
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($facture->getDelaiPaiement() === 'other') {
                $facture->setDelaiPaiement($form->get('customDelaiPaiement')->getData());
            }

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
    public function addFactureLine(Request $request, Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $ligneFacture = new LigneFacture();
        $form = $this->createForm(FactureLigneType::class, $ligneFacture);
        $form->handleRequest($request);
        $editfacture = $this->createForm(FactureType::class, $facture);
        $editfacture->handleRequest($request);

        $emailTemplate = new EmailTemplate();
        $formemail = $this->createForm(EmailTemplateType::class, $emailTemplate);
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
            $entityManager->persist($facture);
            $entityManager->flush();

            // Si la requête est une requête AJAX, renvoyer une réponse JSON
            if ($request->isXmlHttpRequest()) {
                $ligneFactureId = $ligneFacture->getId();
                $response = [
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
                ];

                return $this->json($response);
            }
        }

        return $this->render('ligne_facture/addfactureline.html.twig', [
            'facture' => $editfacture->createView(),
            'form' => $form->createView(),
            'lfacture' => $facture,
            'formemail' => $formemail->createView(),
        ]);
    }

    #[Route('/get-facture-details/{id}', name: 'get_facture_details', methods: ['GET'])]
    public function getFactureDetails(Facture $facture, EntityManagerInterface $entityManager): JsonResponse
    {
        //détails de la facture
        $dateFacturation = $facture->getDateFacturation()->format('F Y');
        $clientNom = $facture->getClient()->getNom();
        $etatfacture = $facture->getEtat();
        $numfacture = $facture->getNumfacture();

        $totalTTC = $facture->getTotalTTC();
        $formattedTotalTTC = number_format($totalTTC, 2, ',', ' ');

        $emailTemplateRepository = $entityManager->getRepository(EmailTemplate::class);

        $emailPremierEnvoie = $emailTemplateRepository->findById(1);
        $emailRelance = $emailTemplateRepository->findById(2);
        $emailAutre = $emailTemplateRepository->findById(3);

        if ($etatfacture == 'ouverte') {
            $emailaenvoyer = $emailPremierEnvoie;

        } else if ($etatfacture == 'envoyée') {
            $emailaenvoyer = $emailRelance;
        } else {
            $emailaenvoyer = $emailAutre;
        }

        $response = [
            'dateFacturation' => $dateFacturation,
            'clientNom' => $clientNom,
            'montant' => $formattedTotalTTC,
            'etatfacture' => $etatfacture,
            'numfacture' => $numfacture,
            'emailid' => $emailaenvoyer->getId(),
            'emailsubject' => $emailaenvoyer->getSubject(),
            'emailbody' => $emailaenvoyer->getBody(),
            'emailtype' => $emailaenvoyer->getType(),

        ];

        return new JsonResponse($response);
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
    public function setfacturepayee(Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $facture->SetEtat('payée');
        $entityManager->persist($facture);
        $entityManager->flush();
        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/Setmanuelnp/{id}', name: 'set_facture_nonpayée', methods: ['GET'])]
    public function setfacturenonpayee(Facture $facture, EntityManagerInterface $entityManager): Response
    {
        $facture->SetEtat('non-payée');
        $entityManager->persist($facture);
        $entityManager->flush();
        return $this->redirectToRoute('app_facture_index', [], Response::HTTP_SEE_OTHER);
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
    public function deleteLigneFacture(int $id, EntityManagerInterface $entityManager): JsonResponse
    {

        $ligneFacture = $entityManager->find(LigneFacture::class, $id);

        if ($ligneFacture) {
            $entityManager->remove($ligneFacture);
            $entityManager->flush();

            return new JsonResponse(['message' => 'La ligne de facture a été supprimée avec succès'], JsonResponse::HTTP_OK);
        }

        return new JsonResponse(['error' => 'La ligne de facture n\'a pas été trouvée'], JsonResponse::HTTP_NOT_FOUND);
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
