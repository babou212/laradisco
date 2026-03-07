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
    protected $description = 'Remove stale users from the presence registry and broadcast offline events';

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
            $staleUsers = $this->presenceService->sweepStale();

            foreach ($staleUsers as $userData) {
                $user = User::find($userData['id']);

                if ($user) {
                    event(new UserPresenceUpdated(
                        $user,
                        UserStatusType::Offline,
                    ));
                }
            }

            if (count($staleUsers) > 0) {
                $this->components->info('Swept '.count($staleUsers).' stale presence entries.');
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
