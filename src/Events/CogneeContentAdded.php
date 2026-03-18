<?php

namespace AgenticMorf\LaravelAICognee\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CogneeContentAdded
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $metadata  Optional metadata (e.g. ['document_id' => 123] for app to update Document)
     */
    public function __construct(
        public string $cogneeId,
        public array $metadata = []
    ) {}
}
