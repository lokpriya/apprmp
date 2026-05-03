<?php

namespace App\Console\Commands;

use App\Models\LocalGovernment;
use Illuminate\Console\Command;

class GenerateLicense extends Command
{
    protected $signature = 'license:generate {lg_id} {days=365}';
    protected $description = 'Generate a signed license key for a local government';

    public function handle(): int
    {
        $lgId   = (int) $this->argument('lg_id');
        $days   = (int) $this->argument('days');
        $expiry = now()->addDays($days)->format('Y-m-d');

        $lg = LocalGovernment::find($lgId);
        if (!$lg) {
            $this->error("Local government #{$lgId} not found.");
            return 1;
        }

        $payload = "{$lgId}|{$expiry}";
        $hmac    = hash_hmac('sha256', $payload, config('app.key'));
        $key     = "{$payload}|{$hmac}";

        $this->info("License key for: {$lg->name_np} ({$lg->name_en})");
        $this->info("Valid until    : {$expiry} ({$days} days)");
        $this->newLine();
        $this->line("<options=bold>{$key}</>");
        $this->newLine();
        $this->comment('Paste this key into Settings → लाइसेन्स → Activate.');

        return 0;
    }
}
