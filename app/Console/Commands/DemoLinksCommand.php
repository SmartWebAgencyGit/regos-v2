<?php

namespace App\Console\Commands;

use App\Hapi\FakeHapi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('regos:demo-links {--reset : Forget registrations made against the demo bookings}')]
#[Description('Print sign-in links for the demo bookings served when HAPI_FAKE=true')]
class DemoLinksCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('regos.hapi.fake')) {
            $this->error('Set HAPI_FAKE=true in .env to use the demo bookings.');

            return self::FAILURE;
        }

        if ($this->option('reset')) {
            Cache::forget('regos:fake-hapi:state');
            $this->info('Demo registrations cleared.');
        }

        $labels = [1001 => 'Australia (Appin)', 1002 => 'United Kingdom (Liverpool)'];

        foreach (FakeHapi::demoKeys() as $bookingId => $key) {
            $this->line(sprintf('  %-28s %s', $labels[$bookingId] ?? $bookingId, url('/?k='.$key)));
        }

        return self::SUCCESS;
    }
}
