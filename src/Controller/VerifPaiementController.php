<?php

namespace App\Controller;

use App\Entity\EmailTemplate;
use App\Entity\Facture;
use App\Form\EmailTemplateType;
use App\Form\PdfType;
use App\Repository\FactureRepository;
use App\Service\VerifPaiementService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VerifPaiementController extends AbstractController
{

    #[Route('/email/edit/{id}', name: 'email_template_edit')]
    public function edit(Request $request, EmailTemplate $emailTemplate, EntityManagerInterface $entityManager): Response
    {
        $formemail = $this->createForm(EmailTemplateType::class, $emailTemplate);
        $formemail->handleRequest($request);

        if ($formemail->isSubmitted() && $formemail->isValid()) {
            $entityManager->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Le template de l\'email a été mis à jour.',
            ]);
        }
        return $this->json([
            'status' => 'error',
            'message' => 'Le formulaire est invalide.',
            'errors' => $this->getFormErrors($formemail),
        ], Response::HTTP_BAD_REQUEST);
    }

    // Fonction utilitaire pour obtenir les erreurs de formulaire
    private function getFormErrors($form)
    {
        $errors = [];
        foreach ($form->getErrors(true, true) as $error) {
            $errors[$error->getOrigin()->getName()] = $error->getMessage();
        }
        return $errors;
    }

    #[Route('/upload-pdf/{id}', name: 'upload_pdf')]
    public function uploadPdf(Request $request, VerifPaiementService $verifPaiementService, FactureRepository $factureRepository, int $id): Response
    {

        $form = $this->createForm(PdfType::class);
        $form->handleRequest($request);
        $facture = $factureRepository->find($id);

        if (!$facture) {
            $this->addFlash('error', 'Facture non trouvée.');
            return $this->redirectToRoute('app_facture_index');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $pdfFiles = $form->get('pdfFiles')->getData();
            $pdfFilesCom = $form->get('pdfFilesCom')->getData();

            if (count($pdfFiles) > 0) {
                $result = $verifPaiementService->handlePdfUpload($pdfFiles, $facture, 'process_extracted_text');
            } elseif (count($pdfFilesCom) > 0) {
                $result = $verifPaiementService->handlePdfUpload($pdfFilesCom, $facture, 'process_extracted_textlcl');
            } else {
                $this->addFlash('error', 'Aucun fichier PDF n\'a été téléchargé.');
                return $this->redirectToRoute('app_facture_index');
            }

            if (isset($result['error'])) {
                $this->addFlash('error', $result['error']);
                return $this->redirectToRoute('app_facture_index');
            }

            return $this->redirectToRoute($result['route'], [
                'extractedText' => implode("\n", $result['texts']),
                'id' => $id,
            ]);
        }

        return $this->render('pdf/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/process-extracted-textlcl/{id}', name: 'process_extracted_textlcl')]
    public function processExtractedTextLCL(Request $request, FactureRepository $factureRepository, int $id, VerifPaiementService $verifPaiementService): Response
    {
        $extractedText = $request->query->get('extractedText');
        $facture = $factureRepository->find($id);

        if (!$facture) {
            $this->addFlash('error', 'Facture non trouvée.');
            return $this->redirectToRoute('app_facture_index');
        }

        if ($extractedText !== null) {
            $verifPaiementService->processExtractedTextOneLCL($extractedText, $facture);

            // Affichage du message correspondant à l'état de la facture
            if ($facture->getEtat() === 'payée') {
                $this->addFlash('success', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' est payée.');
            } elseif ($facture->getEtat() === 'à_vérifier') {
                $this->addFlash('warning', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' nécessite une vérification.');
            } elseif ($facture->getEtat() === 'non-payée') {
                $this->addFlash('error', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' n\'est pas encore payée.');
            } else {
                $this->addFlash('error', 'Erreur, veuillez réessayer.');
            }
        } else {
            $this->addFlash('success', 'Les états des factures ont été mis à jour avec succès.');
            return $this->redirectToRoute('app_facture_index');
        }

        return $this->redirectToRoute('app_facture_index');
    }


    #[Route('/process-extracted-text/{id}', name: 'process_extracted_text')]
    public function processExtractedTextQuonto(Request $request, VerifPaiementService $verifPaiementService,EntityManagerInterface $entityManager, int $id): Response
    {
        $extractedText = $request->query->get('extractedText');
        $facture =$entityManager->getRepository(Facture::class)->find($id);

        if (!$facture) {
            throw $this->createNotFoundException('La facture avec l\'id ' . $id . ' n\'a pas été trouvée.');
        }

        $result = $verifPaiementService->processExtractedTextOneQuonto($extractedText, $facture);

        if (isset($result['success'])) {
            $this->addFlash('success', $result['success']);
        } elseif (isset($result['warning'])) {
            $this->addFlash('warning', $result['warning']);
        } elseif (isset($result['error'])) {
            $this->addFlash('error', $result['error']);
        } else {
            $this->addFlash('error', 'Une erreur s\'est produite. Veuillez réessayer.');
        }

        return $this->redirectToRoute('app_facture_index');
    }

    #[Route('/process-extracted-all-Quonto', name: 'process_extracted_Quonto')]
    public function processExtractedQuonto(Request $request, VerifPaiementService $verifPaiementService): Response
    {
        $startDate = new DateTime($request->query->get('start_date'));
        $endDate = new DateTime($request->query->get('end_date'));
        $extractedText = $request->query->get('extractedText');

        // Appeler la méthode du service pour traiter le texte extrait
        $verifPaiementService->processExtractedTextManyQuonto($extractedText, $startDate, $endDate);

        $this->addFlash('success', 'Les états des factures ont été mis à jour avec succès.');
        return $this->redirectToRoute('app_facture_index');
    }

    #[Route('/process-extracted-all-LCL', name: 'process_extracted_LCL')]
    public function processExtractedTextAllLCL(Request $request, VerifPaiementService $verifPaiementService): Response
    {
        $startDate = new DateTime($request->query->get('start_date'));
        $endDate = new DateTime($request->query->get('end_date'));
        $extractedText = $request->query->get('extractedText');

        // Appeler la méthode du service pour traiter le texte extrait
        $verifPaiementService->processExtractedManyLCL($extractedText, $startDate, $endDate);

        $this->addFlash('success', 'Les états des factures ont été mis à jour avec succès.');
        return $this->redirectToRoute('app_facture_index');
    }

}
