<?php

namespace App\MessageHandler;

use App\Message\EcheanceMessage;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class EcheanceMessageHandler implements MessageHandlerInterface
{
    public function __invoke(EcheanceMessage $message)
    {
        // do something with your message
    }
}
