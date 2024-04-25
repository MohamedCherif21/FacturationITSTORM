<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\PdfType;
use App\Form\PdfwithdateType;
use App\Service\PdfExtractorService; 
use App\Repository\FactureRepository;
use Symfony\Component\VarDumper\VarDumper;
use DateTime;   
class PdfController extends AbstractController
{
  
        // #[Route('/upload-pdf/{id}', name: 'upload_pdf')]
        // public function uploadPdf(Request $request, PdfExtractorService $pdfExtractor, int $id): Response
        // {
        //     $form = $this->createForm(PdfType::class);
        //     $form->handleRequest($request);
        
        //     if ($form->isSubmitted() && $form->isValid()) {
        //         $pdfFile = $form->get('pdfFile')->getData();
        //         $allFactures = $request->request->get('allFactures');
        
                
        
        //         try {
        //             // Générez un nom de fichier unique
        //             $fileName = uniqid().'.'.$pdfFile->guessExtension();
        
        //             // Déplacez le fichier vers le répertoire temporaire
        //             $pdfFile->move(
        //                 $this->getParameter('pdf_directory'),
        //                 $fileName
        //             );
    
    //             // Extraction du texte du PDF
    //             $pdfFilePath = $this->getParameter('pdf_directory').'/'.$fileName;
    //             $text = $pdfExtractor->extractText($pdfFilePath);

    //             // Vérifiez si l'option "Traiter toutes les factures" est cochée
    //             if ($allFactures == 1) {
    //                 // Redirigez vers la route pour traiter toutes les factures non-payées et envoyées
    //                 return $this->redirectToRoute('process_extracted_textall', ['extractedText' => $text]);
    //             }
    
    //             // Redirigez vers la route pour traiter une seule facture avec un ID spécifique
    //             return $this->redirectToRoute('process_extracted_text', ['extractedText' => $text, 'id' => $id]);
    //         } catch (FileException $e) {
    //             $this->addFlash('error', 'Une erreur s\'est produite lors du téléchargement du fichier.');
    //         }
    //     }
    
    //     // Si le formulaire n'est pas soumis ou n'est pas valide, ou s'il y a une erreur lors du traitement du fichier, affichez le formulaire de téléchargement
    //     return $this->render('pdf/upload.html.twig', [
    //         'form' => $form->createView(),
    //     ]);
    // }

    #[Route('/upload-pdf', name: 'upload_pdf_dates')]
public function uploadPdfWithDates(Request $request, PdfExtractorService $pdfExtractor): Response
{
    // Récupérer les valeurs des dates depuis la requête
    $startDate = $request->query->get('start_date');
    $endDate = $request->query->get('end_date');

    // Créer le formulaire et initialiser les champs de dates avec les valeurs récupérées
    $form = $this->createForm(PdfwithdateType::class, null, [
        'start_date' => new \DateTime($startDate),
        'end_date' => new \DateTime($endDate),
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $pdfFile = $form->get('pdfFile')->getData();

        // Vérifier si un fichier a été téléchargé
        if ($pdfFile) {
            // Vérifier si le fichier est un PDF
            if ($pdfFile->getClientOriginalExtension() !== 'pdf') {
                // Afficher un message d'erreur si le fichier n'est pas un PDF
                $this->addFlash('error', 'Le fichier téléchargé doit être un fichier PDF.');
                return $this->redirectToRoute('upload_pdf_dates');
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
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]); 
                
            } catch (FileException $e) {
                $this->addFlash('error', 'Une erreur s\'est produite lors du téléchargement du fichier.');
            }
        }
    }

    return $this->render('pdf/upload_with_dates.html.twig', [
        'form' => $form->createView(),
    ]);
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
                // Vérifiez si le fichier est un PDF
                if ($pdfFile->getClientOriginalExtension() !== 'pdf') {
                    // Affichez un message d'erreur si le fichier n'est pas un PDF
                    $this->addFlash('error', 'Le fichier téléchargé doit être un fichier PDF.');
                    return $this->redirectToRoute('upload_pdf', ['id' => $id]);
                }
    
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

                    unlink($pdfFilePath);
    
                
                    return $this->redirectToRoute('process_extracted_text', ['extractedText' => $text, 'id' => $id]);
                    //return $this->redirectToRoute('process_extracted_textall', ['extractedText' => $text]);
                } catch (FileException $e) {
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
        $extractedText = $request->query->get('extractedText');
        $facture = $factureRepository->find($id);
    
        $clientName = $facture->getClient()->getNom();
        $totalPaid = $facture->getTotalTTC();
    
        // Vérifiez si le texte extrait est fourni
        if ($extractedText !== null) {
            // Vérifiez si le nom du client existe dans le texte extrait
            $clientPosition = strpos($extractedText, $clientName);
            if ($clientPosition !== false) {
                // Définissez l'index de départ pour rechercher le montant
                $startIndex = $clientPosition + strlen($clientName);
    
                // Recherchez tous les montants dans le texte extrait à partir de l'index du nom du client
                preg_match_all('/\+\s*\d+(?:[,.]\d+)?\s*EUR/', $extractedText, $matches, 0, $startIndex);
    
                // Parcourez tous les montants trouvés
                foreach ($matches[0] as $match) {
                    // Retirez le "+" et "EUR" et les espaces, puis convertissez en float
                    $extractedAmount = (float) str_replace(['+', 'EUR', ',', ' '], ['', '', '', ''], $match);
    
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
                        // Sortez de la boucle dès qu'un montant est trouvé
                        break;
                    }
                }
    
                if ($facture->getEtat() === ('envoyée') ) {
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
    
        // Enregistrez les modifications dans la base de données
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($facture);
        $entityManager->flush();
    
        // Redirigez l'utilisateur en fonction de l'état de la facture après la mise à jour
        if ($facture->getEtat() === 'payée') {
            $this->addFlash('success', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' est payée.');
            return $this->redirectToRoute('app_facturepayee_index');
        } elseif ($facture->getEtat() === 'à_vérifier') {
            $this->addFlash('warning', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' nécessite une vérification.');
            return $this->redirectToRoute('app_facture_index');
        } elseif ($facture->getEtat() === 'non-payée')  {
            $this->addFlash('error', 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' n\'est pas encore payée.');
            return $this->redirectToRoute('app_factureimpayee_index');
        }else{
            $this->addFlash('error', 'Erreur veuillez réessayer.');
            return $this->redirectToRoute('app_facture_index');
        }
    }
    

#[Route('/process-extracted-text-all', name: 'process_extracted_textall')]
public function processExtractedTextAllFacture(Request $request, FactureRepository $factureRepository): Response
{
    // Obtenez les dates de début et de fin à partir de la requête
    $startDate = new DateTime($request->query->get('start_date'));
    $endDate = new DateTime($request->query->get('end_date'));

    // Obtenez le texte extrait du formulaire
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
    
                // Recherchez tous les montants dans le texte extrait à partir de l'index du nom du client
                preg_match_all('/\+\s*\d+(?:[,.]\d+)?\s*EUR/', $extractedText, $matches, 0, $startIndex);
    
                // Parcourez tous les montants trouvés
                foreach ($matches[0] as $match) {
                    // Retirez le "+" et "EUR" et les espaces, puis convertissez en float
                    $extractedAmount = (float) str_replace(['+', 'EUR', ',', ' '], ['', '', '', ''], $match);
    
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
                        // Sortez de la boucle dès qu'un montant est trouvé
                        break;
                    }
                }
    
                if ($facture->getEtat() === ('envoyée') ) {
                    $facture->setEtat('non-payée');  
         
                }
            } else {
                $facture->setEtat('non-payée');            
            }
        }
    } else {
            // Aucun texte extrait fourni, donc la facture est considérée comme non payée
            $this->addFlash('error', 'Problème avec le document fourni !');
            return $this->redirectToRoute('app_facture_index');
    }   
        // Enregistrez les modifications dans la base de données
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->flush();

        // Redirigez l'utilisateur après avoir mis à jour toutes les factures
        $this->addFlash('success', 'Les états des factures ont été mis à jour avec succès.');
        return $this->redirectToRoute('app_facture_index');
 
}

}