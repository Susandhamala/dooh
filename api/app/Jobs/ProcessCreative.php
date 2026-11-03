<?php

namespace App\Jobs;

use App\Services\CreativeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Check and re-encode an uploaded ad off the request path (video encoding can take minutes). */
class ProcessCreative implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(public int $creativeId) {}

    public function handle(CreativeService $creatives): void
    {
        $creatives->process($this->creativeId);
    }
}
