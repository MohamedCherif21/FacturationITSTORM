<?php

namespace App\MessageHandler;

use App\Message\EcheanceMessage;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Mime\Email;

final class EcheanceMessageHandler implements MessageHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private FactureRepository $factureRepository;
    private MailerInterface $mailer;

    public function __construct(EntityManagerInterface $entityManager, FactureRepository $factureRepository, MailerInterface $mailer)
    {
        $this->entityManager = $entityManager;
        $this->factureRepository = $factureRepository;
        $this->mailer = $mailer;
    }

    public function __invoke(EcheanceMessage $message)
    {
         // Fetch all invoices
         $factures = $this->factureRepository->findAll();

         // Get current date
         $dateNow = new \DateTime();
 
         // List to store unpaid invoices for email notification
         $facturesNonPayees = [];

         // Iterate over invoices and update status if necessary
         foreach ($factures as $facture) {
             if ($facture->getDateEcheance() < $dateNow) {
                 if ($facture->getEtat() === 'envoyée') {
                     $facture->setEtat('non-payée');
                     $this->entityManager->persist($facture); // Persist changes
                     $facturesNonPayees[] = $facture; // Add to the list of unpaid invoices
                 }
             }
         }

        // Flush changes to the database
        $this->entityManager->flush();

        // Send email if there are unpaid invoices
        if (!empty($facturesNonPayees)) {
            // Email body
            $messageBody = '<p>Les factures suivantes ne sont pas encore payées :</p><ul>';

            foreach ($facturesNonPayees as $facture) {
                $messageBody .= '<li><strong>Facture ' . $facture->getNumfacture() . '</strong> du client <strong>' . $facture->getClient()->getNom() . '</strong>, échéance <strong>' . $facture->getDateEcheance()->format('Y-m-d') . '</strong> (retard de <strong>' . $facture->getNbJoursRetard() . '</strong> jours).</li>';
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
            } catch (TransportExceptionInterface $e) {
                // Handle email sending error
            }
        }
    }
}
