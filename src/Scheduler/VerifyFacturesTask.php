<?php
// src/Scheduler/VerifyFacturesTask.php

// src/Scheduler/VerifyFacturesTask.php

namespace App\Scheduler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;

class VerifyFacturesTask extends Command
{
    protected static $defaultName = 'app:verify-factures';

    private $mailer;
    private $factureRepository;
    private $entityManager;

    public function __construct(MailerInterface $mailer, FactureRepository $factureRepository, EntityManagerInterface $entityManager)
    {
        $this->mailer = $mailer;
        $this->factureRepository = $factureRepository;
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Vérifie les factures non payées.')
            ->setHelp('Cette commande vérifie les factures non payées et envoie des emails de notification si nécessaire.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $facturesNonPayees = [];
        $factures = $this->factureRepository->findAll();
        $dateNow = new \DateTime();

        foreach ($factures as $facture) {
            if ($facture->getDateEcheance() < $dateNow && $facture->getEtat() === 'envoyée') {
                $facture->setEtat('non-payée');
                $facturesNonPayees[] = $facture;
            }
        }

        // Flush changes to the database
        $this->entityManager->flush();

        if (!empty($facturesNonPayees)) {
            // Email body
            $messageBody = '<p>Les factures suivantes ne sont pas encore payées :</p><ul>';

            foreach ($facturesNonPayees as $facture) {
                $messageBody .= '<li><strong>Facture ' . $facture->getNumfacture() . '</strong> du client <strong>' . $facture->getClient()->getNom() . '</strong>, échéance <strong>' . $facture->getDateEcheance()->format('Y-m-d') . '</strong></li>';
            }

            $messageBody .= '</ul>';

            // Send email
            $email = (new Email())
                ->from('cherifmouhamed9242@yahoo.fr')
                ->to('cherifmouhamed123@gmail.com')
                ->subject('Factures non payées')
                ->html($messageBody);

            try {
                $this->mailer->send($email);
                $output->writeln('Les factures non payées ont été envoyées par e-mail avec succès.');
            } catch (\Exception $e) {
                $output->writeln('Une erreur s\'est produite lors de l\'envoi des factures non payées par e-mail.');
            }
        } else {
            $output->writeln('Aucune facture non payée à notifier.');
        }

        return Command::SUCCESS;
    }
}

