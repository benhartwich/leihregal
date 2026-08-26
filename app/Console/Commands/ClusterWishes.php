<?php

namespace App\Console\Commands;

use App\Services\CurationService;
use Illuminate\Console\Command;

class ClusterWishes extends Command
{
    protected $signature   = 'leihregal:cluster-wishes';
    protected $description = 'Group similar wishes into clusters using embedding similarity';

    public function handle(CurationService $curation): void
    {
        $this->info('Clustering wishes…');
        $count = $curation->clusterWishes();
        $this->info("Clustered {$count} wishes.");
    }
}
