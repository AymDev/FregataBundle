<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Task;

use Fregata\FregataBundle\Messenger\Command\Task\RunTask;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class RunTaskTest extends AbstractMessengerTestCase
{
    /**
     * Entity ID is kept in the message
     */
    public function testMessageHasEntityId()
    {
        $message = new RunTask(42);
        self::assertSame(42, $message->getTaskId());
    }
}
