<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\PdfType;
use App\Service\PdfExtractorService; 
use App\Repository\FactureRepository;

class PdfController extends AbstractController
{
    #[Route('/extract-pdf', name: 'extract_pdf')]
    public function extractPdf(Request $request, PdfExtractorService $pdfExtractor): Response 
    {
        // Utiliser le formulaire pour obtenir le chemin du fichier PDF téléchargé
        $pdfFilePath = $this->getParameter('pdf_directory').'/'.$request->get('pdfFileName');

        // Extraction du texte du PDF
        $text = $pdfExtractor->extractText($pdfFilePath);

        // Affichage du texte extrait
        return new Response($text);
    }


    #[Route('/upload-pdf/{id}', name: 'upload_pdf')]
    public function uploadPdf(Request $request, PdfExtractorService $pdfExtractor, int $id): Response
    {
        $form = $this->createForm(PdfType::class);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $pdfFile = $form->get('pdfFile')->getData();
    
            // Vérifiez si un fichier a été téléchargé
            if ($pdfFile) {
                try {
                    // Générez un nom de fichier unique
                    $fileName = uniqid().'.'.$pdfFile->guessExtension();
    
                    // Déplacez le fichier vers le répertoire temporaire
                    $pdfFile->move(
                        $this->getParameter('pdf_directory'),
                        $fileName
                    );
    
                    // Extraction du texte du PDF
                    $pdfFilePath = $this->getParameter('pdf_directory').'/'.$fileName;
                    $text = $pdfExtractor->extractText($pdfFilePath);
    
                    // Redirigez l'utilisateur vers la méthode de traitement du texte avec le texte extrait et l'ID de la facture
                    return $this->redirectToRoute('process_extracted_text', ['extractedText' => $text, 'id' => $id]);
                } catch (FileException $e) {
                    // Gestion des erreurs lors du déplacement du fichier
                    $this->addFlash('error', 'Une erreur s\'est produite lors du téléchargement du fichier.');
                }
            }
        }
    
        return $this->render('pdf/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    

    #[Route('/process-extracted-text/{id}', name: 'process_extracted_text')]
    public function processExtractedText(Request $request, FactureRepository $factureRepository, int $id): Response
    {
        // Obtenez le texte extrait du formulaire
        $extractedText = $request->query->get('extractedText');

        // Vérifiez si le texte extrait est fourni
        if ($extractedText !== null) {
            // Récupérez la facture à partir de son identifiant
            $facture = $factureRepository->find($id);

            // Obtenez le nom du client et le montant total TTC de la facture
            $clientName = $facture->getClient()->getNom();
            $totalPaid = $facture->getTotalTTC();

            // Vérifiez si le nom du client et le montant total TTC existent dans le texte extrait
            if (strpos($extractedText, $clientName) !== false && strpos($extractedText, $totalPaid) !== false) {
                // Mise à jour de l'état de la facture
                $facture->setEtat('payée');
            } else {
                // Mise à jour de l'état de la facture
                $facture->setEtat('non-payée');
            }

            // Enregistrez les modifications dans la base de données
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($facture);
            $entityManager->flush();

            // Redirigez l'utilisateur en fonction de l'état de la facture après la mise à jour
            if ($facture->getEtat() === 'payée') {
                return $this->redirectToRoute('app_facturepayee_index');
            } else {
                return $this->redirectToRoute('app_factureimpayee_index');
            }
        } else {
            // Retournez une réponse d'erreur si le texte extrait n'est pas fourni
            return new Response('Le texte extrait n\'a pas été fourni', Response::HTTP_BAD_REQUEST);
        }
    }



}
