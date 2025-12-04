<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotasEnviadasAOficinaBulk
{
    use Dispatchable, SerializesModels;

    /**
     * @var int[]
     */
    public array $noteIds;

    public ?User $comercial;

    /**
     * @param int[] $noteIds
     */
    public function __construct(array $noteIds, ?User $comercial)
    {
        $this->noteIds = $noteIds;
        $this->comercial = $comercial;
    }
}
