<?php
// src/Scheduler/VerifEcheance.php

namespace App\Scheduler;

use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\EcheanceMessage;

class VerifEcheance implements ScheduleProviderInterface
{
    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function getSchedule(): Schedule
    {
        // Configure the schedule for sending the message daily at midnight
        return new Schedule(
            new \DateTimeImmutable('midnight'),
            null,
            '1d' // Repeat interval: every 1 day
        );
    }

    public function __invoke()
    {
        // When the schedule is triggered, dispatch the EcheanceMessage
        $this->messageBus->dispatch(new EcheanceMessage());
    }
}
