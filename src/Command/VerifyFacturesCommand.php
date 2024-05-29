<?php

namespace App\Command;

use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:verify-factures',
    description: 'Vérifie les factures et envoie des e-mails pour les factures non payées.',
)]
class VerifyFacturesCommand extends Command
{
    private $factureRepository;
    private $entityManager;
    private $mailer;

    public function __construct(FactureRepository $factureRepository, EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        $this->factureRepository = $factureRepository;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $facturesNonPayees = [];
        $factures = $this->factureRepository->findAll();
        $dateNow = new \DateTime();

        foreach ($factures as $facture) {
            if ($facture->getDateEcheance() < $dateNow && $facture->getEtat() === 'envoyée') {
                $facture->setEtat('non-payée');
                $facturesNonPayees[] = $facture; // Ajout à la liste des factures non payées
                $this->entityManager->persist($facture);
            }
        }
        $this->entityManager->flush();

        if (!empty($facturesNonPayees)) {
            $messageBody = $this->$this->renderView('emailtemplate/factures_non_payees.html.twig', [
                'factures' => $facturesNonPayees,
            ]);

            // envoie de l'e-mail
            $email = (new Email())
                ->subject('Factures non payées')
                ->from('cherifmouhamed9242@yahoo.fr')
                ->to('cherifmouhamed123@gmail.com')
                ->html($messageBody);

            try {
                $this->mailer->send($email);
                $output->writeln('Les factures non payées ont été envoyées par e-mail avec succès.');
            } catch (\Exception $e) {
                $output->writeln('Une erreur s\'est produite lors de l\'envoi des factures non payées par e-mail: ' . $e->getMessage());
            }
        } else {
            $output->writeln('Aucune facture non payée à notifier.');
        }

        return Command::SUCCESS;
    }
}
