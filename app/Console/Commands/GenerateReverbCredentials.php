<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateReverbCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:credentials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Reverb app credentials for .env file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = Str::random(32);
        $secret = Str::random(32);

        $this->components->info('Add these to your .env file:');
        $this->newLine();
        $this->line("REVERB_APP_KEY={$key}");
        $this->line("REVERB_APP_SECRET={$secret}");
        $this->newLine();

        return Command::SUCCESS;
    }
}
