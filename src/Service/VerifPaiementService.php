<?php

namespace App\Service;

use App\Entity\Facture;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VerifPaiementService
{
    private $entityManager;
    private $pdfDirectory;

    public function __construct(EntityManagerInterface $entityManager, string $pdfDirectory)
    {
        $this->entityManager = $entityManager;
        $this->pdfDirectory = $pdfDirectory;
    }

    public function extractText(string $pdfFilePath): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfFilePath);

        $text = '';
        foreach ($pdf->getPages() as $page) {
            $text .= $page->getText();
        }

        return $text;
    }

    public function handlePdfUpload(array $pdfFiles, Facture $facture, string $routePrefix): array
    {
        $extractedTexts = [];
        foreach ($pdfFiles as $pdfFile) {
            /** @var UploadedFile $pdfFile */
            if ($pdfFile->getClientOriginalExtension() !== 'pdf') {
                return ['error' => 'Le fichier téléchargé doit être un fichier PDF.'];
            }

            try {
                $fileName = uniqid() . '.' . $pdfFile->guessExtension();
                $pdfFile->move($this->pdfDirectory, $fileName);

                // Extract du texte du PDF
                $pdfFilePath = $this->pdfDirectory . '/' . $fileName;
                $extractedText = $this->extractText($pdfFilePath);

                // Suppr du fichier temporaire
                unlink($pdfFilePath);

                $extractedTexts[] = $extractedText;
            } catch (FileException $e) {
                return ['error' => 'Une erreur s\'est produite lors du téléchargement du fichier.'];
            }
        }

        return ['success' => true, 'route' => $routePrefix, 'texts' => $extractedTexts];
    }

    

    public function processExtractedTextOneQuonto(string $extractedText, Facture $facture): array
    {
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
            return ['error' => 'Aucun texte extrait fourni.'];
        }

        $this->entityManager->persist($facture);
        $this->entityManager->flush();

        // Construire la réponse en fonction de l'état de la facture
        if ($facture->getEtat() === 'payée') {
            return ['success' => 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' est payée.'];
        } elseif ($facture->getEtat() === 'à_vérifier') {
            return ['warning' => 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' nécessite une vérification.'];
        } elseif ($facture->getEtat() === 'non-payée') {
            return ['error' => 'La facture ' . $facture->getNumfacture() . ' du client ' . $facture->getClient()->getNom() . ' n\'est pas encore payée.'];
        } else {
            return ['error' => 'Erreur veuillez réessayer.'];
        }
    }

    public function processExtractedTextManyQuonto(string $extractedText, DateTime $startDate, DateTime $endDate): void
    {
        $factureRepository = $this->entityManager->getRepository(Facture::class);

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

        $this->entityManager->flush();
    }

    public function processExtractedTextOneLCL(string $extractedText, Facture $facture): void
    {
        $clientRef = 'REF.CLIENT:' . $facture->getClient()->getReferencebancaire();
        $totalPaid = $facture->getTotalTTC();

        // Vérifiez si la référence du client est présente dans le texte extrait
        $clientPosition = strpos($extractedText, $clientRef);
        if ($clientPosition !== false) {
            // Définissez l'index de départ pour rechercher le montant
            $startIndex = $clientPosition + strlen($clientRef);
            $remainingText = substr($extractedText, $startIndex);

            // Expression régulière pour extraire tous les montants après la référence client
            $pattern = '/\b(\d{1,3}(?: \d{3})*,\d{2})\b/';
            preg_match_all($pattern, $remainingText, $matches);

            // Conversion des montants extraits en float
            $extractedAmounts = array_map(function ($amount) {
                // Remplacer les espaces par des rien et les virgules par des points
                return (float) str_replace(',', '.', str_replace(' ', '', $amount));
            }, $matches[1]);

            // Filtrer les montants qui correspondent exactement au total payé
            $matchingAmounts = array_filter($extractedAmounts, function ($amount) use ($totalPaid) {
                return $amount === $totalPaid;
            });

            // Mettre à jour l'état de la facture en fonction du nombre de montants correspondants trouvés
            if (count($matchingAmounts) === 1) {
                $facture->setEtat('payée');
            } elseif (count($matchingAmounts) > 1) {
                $facture->setEtat('à_vérifier');
            } else {
                $facture->setEtat('non-payée');
            }
        } else {
            $facture->setEtat('non-payée');
        }

        $this->entityManager->persist($facture);
        $this->entityManager->flush();
    }

    public function processExtractedManyLCL(string $extractedText, DateTime $startDate, DateTime $endDate): void
    {
        $factureRepository = $this->entityManager->getRepository(Facture::class);

        // Récupérez toutes les factures entre les dates données
        $factures = $factureRepository->findByDateRange($startDate, $endDate);

        // Parcourez toutes les factures
        foreach ($factures as $facture) {
            // Vérifiez si la facture est non payée
            $clientRef = 'REF.CLIENT:' . $facture->getClient()->getReferencebancaire();
            $totalPaid = $facture->getTotalTTC();
            $clientPosition = strpos($extractedText, $clientRef);
            if ($clientPosition !== false) {
                // Définissez l'index de départ pour rechercher le montant
                $startIndex = $clientPosition + strlen($clientRef);
                $remainingText = substr($extractedText, $startIndex);

                // Trouvez le premier "+" après le nom du client et extrayez le montant suivant
                $pattern = '/\b(\d{1,3}(?: \d{3})*,\d{2})\b/';
                preg_match_all($pattern, $remainingText, $matches);
                // Conversion des montants extraits en float
                $extractedAmounts = array_map(function ($amount) {
                    // Remplacer les espaces par des rien et les virgules par des points
                    return (float) str_replace(',', '.', str_replace(' ', '', $amount));
                }, $matches[1]);

                // Filtrer les montants qui correspondent exactement au total payé
                $matchingAmounts = array_filter($extractedAmounts, function ($amount) use ($totalPaid) {
                    return $amount === $totalPaid;
                });

                // Mettre à jour l'état de la facture en fonction du nombre de montants correspondants trouvés
                if (count($matchingAmounts) === 1) {
                    $facture->setEtat('payée');
                } elseif (count($matchingAmounts) > 1) {
                    $facture->setEtat('à_vérifier');
                } else {
                    $facture->setEtat('non-payée');
                }
            } else {
                $facture->setEtat('non-payée');
            }

            // Persister les modifications de la facture
            $this->entityManager->persist($facture);
        }

        // Enregistrer toutes les modifications en une seule transaction
        $this->entityManager->flush();
    }
}
