<?php

namespace App\Controller;

use App\Entity\EmailTemplate;
use App\Form\EmailTemplateType;
use App\Form\PdfType;
use App\Form\PdfwithdateType;
use App\Repository\FactureRepository;
use App\Service\PdfExtractorService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PdfController extends AbstractController
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
                'message' => 'Le template de l\'email a été mis à jour.'
            ]);
        }
        return $this->json([
            'status' => 'error',
            'message' => 'Le formulaire est invalide.',
            'errors' => $this->getFormErrors($formemail)
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
public function uploadPdf(Request $request, PdfExtractorService $pdfExtractor, int $id): Response
{
    $form = $this->createForm(PdfType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $pdfFiles = $form->get('pdfFiles')->getData();

        // Vérifiez si un fichier a été téléchargé
        if (count($pdfFiles) > 0) {
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

                    // Extract du texte du PDF
                    $pdfFilePath = $this->getParameter('pdf_directory') . '/' . $fileName;
                    $extractedText = $pdfExtractor->extractText($pdfFilePath);

                    // Suppr du fichier temporaire
                    unlink($pdfFilePath);

                    return $this->redirectToRoute('process_extracted_text', ['extractedText' => $extractedText, 'id' => $id]);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur s\'est produite lors du téléchargement du fichier.');
                }
            }
        } else {
            $this->addFlash('error', 'Aucun fichier PDF n\'a été téléchargé.');
        }
    }

    return $this->render('pdf/upload.html.twig', [
        'form' => $form->createView(),
    ]);
}


#[Route('/process-extracted-text/{id}', name: 'process_extracted_text')]
public function processExtractedText(Request $request, FactureRepository $factureRepository, int $id, EntityManagerInterface $entityManager): Response
{
    $extractedText = $request->query->get('extractedText');
    $facture = $factureRepository->find($id);

    $clientName = $facture->getClient()->getNom();
    $totalPaid = $facture->getTotalTTC();

    // Vérifiez si le texte extrait est fourni
    if ($extractedText !== null) {
        // Vérifiez si le nom du client est présent dans le texte extrait
        $clientPosition = strpos($extractedText, $clientName);
        if ($clientPosition !== false) {
            // Définissez l'index de départ pour rechercher le montant
            $startIndex = $clientPosition + strlen($clientName);

            // Trouvez le premier "+" après le nom du client et extrayez le montant suivant
            $pattern = '/\+\s*(\d+(?:[,.]\d+)?)\s*EUR/';
            if (preg_match($pattern, substr($extractedText, $startIndex), $matches)) {
                // Convertissez le montant extrait en float
                $extractedAmount = (float) str_replace([','], ['.'], $matches[1]);

                $tolerance = $totalPaid * 0.3;

                if (abs($extractedAmount - $totalPaid) <= $tolerance) {
                    if ($extractedAmount === $totalPaid) {
                        $facture->setEtat('payée');
                    } else {
                        $facture->setEtat('à_vérifier');
                    }
                } else {
                    $facture->setEtat('non-payée');
                }
            } else {
                $facture->setEtat('non-payée');
            }
        } else {
            $facture->setEtat('non-payée');
        }
    } else {
        // Aucun texte extrait fourni, donc la facture est considérée comme non payée
        $this->addFlash('success', 'Les états des factures ont été mis à jour avec succès.');
        return $this->redirectToRoute('app_facture_index');
    }
    $entityManager->persist($facture);
    $entityManager->flush();

    if ($facture->getEtat() === 'payée') {
        $this->addFlash('success', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' est payée.');
        return $this->redirectToRoute('app_facture_index');
    } elseif ($facture->getEtat() === 'à_vérifier') {
        $this->addFlash('warning', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' nécessite une vérification.');
        return $this->redirectToRoute('app_facture_index');
    } elseif ($facture->getEtat() === 'non-payée') {
        $this->addFlash('error', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' n\'est pas encore payée.');
        return $this->redirectToRoute('app_facture_index');
    } else {
        $this->addFlash('error', 'Erreur veuillez réessayer.');
        return $this->redirectToRoute('app_facture_index');
    }
}


#[Route('/process-extracted-text-all', name: 'process_extracted_textall')]
public function processExtractedTextAllFacture(Request $request, FactureRepository $factureRepository, EntityManagerInterface $entityManager): Response
{
    $startDate = new DateTime($request->query->get('start_date'));
    $endDate = new DateTime($request->query->get('end_date'));

    $extractedText = $request->query->get('extractedText');

    // Vérifiez si le texte extrait est fourni
    if ($extractedText !== null) {
        // Récupérez toutes les factures entre les dates données
        $factures = $factureRepository->findByDateRange($startDate, $endDate);

        // Parcourez toutes les factures
        foreach ($factures as $facture) {
            // Vérifiez si la facture est non payée
            $clientName = $facture->getClient()->getNom();
            $totalPaid = $facture->getTotalTTC();
            $clientPosition = strpos($extractedText, $clientName);
            if ($clientPosition !== false) {
                // Définissez l'index de départ pour rechercher le montant
                $startIndex = $clientPosition + strlen($clientName);

                // Trouvez le premier "+" après le nom du client et extrayez le montant suivant
                $pattern = '/\+\s*(\d+(?:[,.]\d+)?)\s*EUR/';
                if (preg_match($pattern, substr($extractedText, $startIndex), $matches)) {
                    // Convertissez le montant extrait en float
                    $extractedAmount = (float) str_replace([','], ['.'], $matches[1]);

                    // Définissez une marge de tolérance de 30% pour la comparaison des montants
                    $tolerance = $totalPaid * 0.3;

                    // Vérifiez si le montant extrait est à l'intérieur de la plage de tolérance
                    if (abs($extractedAmount - $totalPaid) <= $tolerance) {
                        // Le montant extrait est dans la plage de tolérance, donc la facture est considérée comme payée ou à vérifier
                        if ($extractedAmount === $totalPaid) {
                            $facture->setEtat('payée');
                        } else {
                            $facture->setEtat('à_vérifier');
                        }
                    } else {
                        $facture->setEtat('non-payée');
                    }
                } else {
                    $facture->setEtat('non-payée');
                }
            } else {
                $facture->setEtat('non-payée');
            }
        }
    } else {
        $this->addFlash('error', 'Problème avec le document fourni !');
        return $this->redirectToRoute('app_facture_index');
    }
    
    $entityManager->flush();

    $this->addFlash('success', 'Les états des factures ont été mis à jour avec succès.');
    return $this->redirectToRoute('app_facture_index');
}


   

}
