<?php
// src/Scheduler/VerifEcheance.php

namespace App\Scheduler;

use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\EcheanceMessage;
use App\Repository\FactureRepository;
use Doctrine\ORM\EntityManagerInterface;

class VerifEcheance implements ScheduleProviderInterface
{
    private MessageBusInterface $messageBus;
    private FactureRepository $factureRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(MessageBusInterface $messageBus, FactureRepository $factureRepository, EntityManagerInterface $entityManager)
    {
        $this->messageBus = $messageBus;
        $this->factureRepository = $factureRepository;
        $this->entityManager = $entityManager;
    }

    // public function getSchedule(): Schedule
    // {
    //     // Configure the schedule for running the verification task daily at midnight
    //     return new Schedule(
    //         new \DateTimeImmutable('midnight'),
    //         null,
    //         '1d' // Repeat interval: every 1 day
    //     );
    // }

    public function getSchedule(): Schedule
    {
        // Configure the schedule for running the verification task daily at noon
        return new Schedule(
            new \DateTimeImmutable('noon'),
            null,
            '1d' // Repeat interval: every 1 day
        );
    }
    


    public function __invoke()
    {
        // Fetch all invoices
        $factures = $this->factureRepository->findAll();

        // Get current date
        $dateNow = new \DateTime();

        // Iterate over invoices and update status if necessary
        foreach ($factures as $facture) {
            if ($facture->getDateEcheance() < $dateNow) {
                if ($facture->getEtat() === 'envoyée') {
                    $facture->setEtat('non-payée');
                    $this->entityManager->persist($facture); // Persist changes
                }
            }
        }

        // Flush changes to the database
        $this->entityManager->flush();

        // Dispatch a message indicating that the verification task has been completed
        $this->messageBus->dispatch(new EcheanceMessage());
    }
}
