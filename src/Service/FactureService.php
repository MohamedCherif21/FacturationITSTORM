<?php

namespace App\Service;

use App\Entity\EmailTemplate;
use App\Entity\Facture;
use App\Form\FactureType;
use App\Repository\FactureRepository;
use App\Service\VerifPaiementService;
use Doctrine\ORM\EntityManagerInterface;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Environment;

class FactureService
{
    private $factureRepository;
    private $entityManager;
    private $mailer;
    private $twig;
    private $pdfExtractor;
    private $requestStack;
    private $pdfDirectory;
    private $formFactory;

    public function __construct(
        FactureRepository $factureRepository,
        VerifPaiementService $pdfExtractor,
        EntityManagerInterface $entityManager,
        Swift_Mailer $mailer,
        RequestStack $requestStack,
        string $pdfDirectory,
        Environment $twig,
        FormFactoryInterface $formFactory,
    ) {
        $this->factureRepository = $factureRepository;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->pdfDirectory = $pdfDirectory;
        $this->pdfExtractor = $pdfExtractor;
        $this->formFactory = $formFactory;
    }

    public function createFacture()
    {
        $facture = new Facture();

        $lastFactureNumber = $this->entityManager->getRepository(Facture::class)->getLastFactureNumber();
        $newFactureNumber = $lastFactureNumber + 1;

        $facture->setNumFacture('FAC/' . date('Y') . '/' . str_pad($newFactureNumber, 6, '0', STR_PAD_LEFT));
        $facture->setEtat("ouverte");

        $request = $this->requestStack->getCurrentRequest();
        $form = $this->formFactory->create(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($facture);
            $this->entityManager->flush();

            return $facture;
        }

        return [
            'facture' => $facture,
            'form' => $form,
        ];
    }


    public function verifierFacturesNonPayees(): void
    {
        $facturesNonPayees = [];
        $factures = $this->factureRepository->findAll();
        $dateNow = new \DateTime();

        foreach ($factures as $facture) {
            if ($facture->getDateEcheance() < $dateNow && $facture->getEtat() === 'envoyée') {
                $facture->setEtat('non-payée');
                $facturesNonPayees[] = $facture;
                $this->entityManager->persist($facture);
            }
        }
        $this->entityManager->flush();

        if (!empty($facturesNonPayees)) {
            $messageBody = $this->twig->render('emailtemplate/factures_non_payees.html.twig', [
                'factures' => $facturesNonPayees,
            ]);

            // Envoi de l'e-mail
            $message = (new Swift_Message())
                ->setSubject('Factures non payées')
                ->setFrom('cherifmouhamed9242@yahoo.fr')
                ->setTo('cherifmouhamed123@gmail.com')
                ->setBody($messageBody, 'text/html');

            try {
                $this->mailer->send($message);
            } catch (TransportExceptionInterface $e) {
            }
        }
    }

    public function EmailTemplateForm($form)
    {
        if ($form->isSubmitted() && $form->isValid()) {
            $emailTemplate = $form->getData();
            $this->entityManager->persist($emailTemplate);
            $this->entityManager->flush();
        }
    }

    public function FactureForm($form)
    {
        $request = $this->requestStack->getCurrentRequest();

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
                $pdfFilesCom = $data['pdfFilesCom'];

                $extractedText = $this->processPdfFiles($pdfFiles);
                if (!empty($extractedText)) {
                    return $this->prepareRedirect('process_extracted_Quonto', $extractedText, $startDate, $endDate);
                }

                $extractedText = $this->processPdfFiles($pdfFilesCom);
                if (!empty($extractedText)) {
                    return $this->prepareRedirect('process_extracted_LCL', $extractedText, $startDate, $endDate);
                }
            } elseif ($request->request->has('filtrer')) {
                $factures = $this->filterFacturesByDate($startDate, $endDate);
            }
        } else {
            $factures = $this->factureRepository->findAll();
        }

        return [
            'redirect' => false,
            'factures' => $factures,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    private function processPdfFiles($pdfFiles)
    {
        $extractedText = '';

        foreach ($pdfFiles as $pdfFile) {
            /** @var UploadedFile $pdfFile */
            if ($pdfFile->getClientOriginalExtension() !== 'pdf') {
                throw new FileException('Le fichier téléchargé doit être un fichier PDF.');
            }

            try {
                $fileName = uniqid() . '.' . $pdfFile->guessExtension();
                $pdfFile->move($this->pdfDirectory, $fileName);

                // Extraction du texte du PDF
                $pdfFilePath = $this->pdfDirectory . '/' . $fileName;
                $extractedText .= $this->pdfExtractor->extractText($pdfFilePath) . PHP_EOL;

                // Suppression du fichier temporaire
                unlink($pdfFilePath);
            } catch (FileException $e) {
                throw new FileException('Une erreur s\'est produite lors du téléchargement du fichier.');
            }
        }

        return $extractedText;
    }

    private function prepareRedirect($route, $extractedText, $startDate, $endDate)
    {
        return [
            'redirect' => true,
            'route' => $route,
            'params' => [
                'extractedText' => $extractedText,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ];
    }

    private function filterFacturesByDate($startDate, $endDate)
    {
        if ($startDate && $endDate) {
            return $this->factureRepository->findByDateRange($startDate, $endDate);
        }

        return $this->factureRepository->findAll();
    }

 

    public function getFactureDetails(Facture $facture): array
    {
        $dateFacturation = $facture->getDateFacturation()->format('F Y');
        $clientNom = $facture->getClient()->getNom();
        $etatfacture = $facture->getEtat();
        $numfacture = $facture->getNumfacture();

        $totalTTC = $facture->getTotalTTC();
        $formattedTotalTTC = number_format($totalTTC, 2, ',', ' ');

        $emailTemplateRepository = $this->entityManager->getRepository(EmailTemplate::class);

        $emailPremierEnvoie = $emailTemplateRepository->find(1);
        $emailRelance = $emailTemplateRepository->find(2);
        $emailAutre = $emailTemplateRepository->find(3);

        if ($etatfacture == 'ouverte') {
            $emailaenvoyer = $emailPremierEnvoie;
        } elseif ($etatfacture == 'envoyée') {
            $emailaenvoyer = $emailRelance;
        } else {
            $emailaenvoyer = $emailAutre;
        }

        return [
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
    }

    public function setFacturePayee(Facture $facture): void
    {
        $facture->setEtat('payée');
        $this->entityManager->persist($facture);
        $this->entityManager->flush();
    }

    public function setFactureNonPayee(Facture $facture): void
    {
        $facture->setEtat('non-payée');
        $this->entityManager->persist($facture);
        $this->entityManager->flush();
    }

    public function deleteFacture(Facture $facture): void
    {
        $facture->setIsDeleted(true);
        $this->entityManager->persist($facture);
        $this->entityManager->flush();
    }


    
}
