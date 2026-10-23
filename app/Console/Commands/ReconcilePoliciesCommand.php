<?php

namespace App\Console\Commands;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use App\Services\Policy\PolicyRequestService;
use Illuminate\Console\Command;

class ReconcilePoliciesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'policy:reconcile {--id= : Specific policy request ID to reconcile}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile ambiguous or pending policies (NEEDS_RECONCILIATION, PENDING_CONFIRMATION) against external provider';

    /**
     * Execute the console command.
     */
    public function handle(PolicyRequestService $service): int
    {
        $specificId = $this->option('id');

        $query = PolicyRequest::query();
        if ($specificId) {
            $query->where('id', $specificId);
        } else {
            $query->whereIn('status', [
                PolicyStatus::NEEDS_RECONCILIATION->value,
                PolicyStatus::PENDING_CONFIRMATION->value,
            ]);
        }

        $policies = $query->get();

        if ($policies->isEmpty()) {
            $this->info('No policies requiring reconciliation.');
            return Command::SUCCESS;
        }

        $this->info("Found {$policies->count()} policy/policies requiring reconciliation.");

        foreach ($policies as $policy) {
            $this->line("Reconciling Policy ID [{$policy->id}], Client Request ID [{$policy->client_request_id}], Current Status: [{$policy->status}]...");

            $result = $service->reconcile($policy);

            $this->info(" -> Result: Status [{$result['status']}] - {$result['message']}");
        }

        $this->newLine();
        $this->info('Reconciliation completed.');

        return Command::SUCCESS;
    }
}
