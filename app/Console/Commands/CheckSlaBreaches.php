<?php

namespace App\Console\Commands;

use App\Enums\SlaTarget;
use App\Jobs\EscalateTicket;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:check-sla')]
#[Description('Find tickets past an SLA deadline and queue an escalation for each new breach')]
class CheckSlaBreaches extends Command
{
    /**
     * Tenant queries fail closed without a current organization, so check
     * each organization in turn. Stamping the breach column first means a
     * breach is escalated once, however often the command runs.
     */
    public function handle(): int
    {
        $escalations = 0;

        try {
            foreach (Organization::all() as $organization) {
                $organization->makeCurrent();

                foreach (SlaTarget::cases() as $target) {
                    foreach (Ticket::slaBreached($target)->get() as $ticket) {
                        $ticket->forceFill([$target->breachedColumn() => now()])->save();

                        EscalateTicket::dispatch($ticket, $target);
                        $escalations++;
                    }
                }
            }
        } finally {
            Organization::forgetCurrent();
        }

        $this->info("Escalated {$escalations} SLA breach(es).");

        return self::SUCCESS;
    }
}
