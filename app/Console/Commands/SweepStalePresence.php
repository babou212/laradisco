<?php

namespace App\Console\Commands;

use App\Enums\UserStatusType;
use App\Events\UserPresenceUpdated;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SweepStalePresence extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'presence:sweep';

    /**
     * The console command description.
     */
    protected $description = 'Recompute heartbeat-based presence and broadcast idle/offline transitions';

    public function __construct(
        private PresenceService $presenceService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lock = Cache::lock('presence:sweep-lock', 30);

        if (! $lock->get()) {
            $this->components->info('Another pod is already running the sweep.');

            return self::SUCCESS;
        }

        try {
            $transitions = $this->presenceService->sweep();

            foreach ($transitions as $entry) {
                $user = User::find($entry['id']);

                if ($user) {
                    event(new UserPresenceUpdated(
                        $user,
                        UserStatusType::from($entry['status']),
                        $entry['custom_status'] ?? null,
                    ));
                }
            }

            if (count($transitions) > 0) {
                $this->components->info('Broadcast '.count($transitions).' presence transitions.');
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
