<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class LogoutListener extends BasicListener
{
    /**
     * Handle the event.
     *
     * @param  Logout  $event
     * @return void
     */
    public function handle(Logout $event)
    {
        $this->statut->setVisitorStatut();
    }
}
