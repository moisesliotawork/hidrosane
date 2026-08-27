<?php

namespace App\Services;

use App\Models\CustomerAutoMergeRun;

class CustomerAutoMergeService
{
    public function __construct(
        protected CustomerMergeService $mergeService,
    ) {}

    /**
     * Fusiona automáticamente todos los pares de 2 clientes con mismo nombre y teléfono compartido.
     */
    public function run(?int $mergedByUserId = null, string $trigger = 'scheduled'): CustomerAutoMergeRun
    {
        $pairs = CustomerDuplicateSearchService::findAutoMergePairsOfTwo();

        $pairsToMerge = array_map(
            fn (array $pair): array => [
                'keeper_id' => (int) $pair['keeper_id'],
                'to_delete_id' => (int) $pair['to_delete_id'],
            ],
            $pairs,
        );

        $result = $this->mergeService->mergePairs($pairsToMerge, $mergedByUserId);

        return CustomerAutoMergeRun::create([
            'merged_count' => $result['merged'],
            'failed_count' => count($result['failed']),
            'trigger' => $trigger,
            'failures' => $result['failed'] !== [] ? $result['failed'] : null,
            'ran_at' => now(),
        ]);
    }
}
