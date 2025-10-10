<?php
namespace Database\Seeders\Prospect;

use App\Models\Prospect\CnaeCatalog;
use Illuminate\Database\Seeder;
use League\Csv\Reader;

class CnaeCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = base_path('resources/prospect/cnaes.csv');
        if (!file_exists($csvPath)) {
            $this->command?->warn("CSV não encontrado: {$csvPath}");
            return;
        }

        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setHeaderOffset(0);

        foreach ($csv->getRecords() as $r) {
            $codigo = substr(preg_replace('/\D/','', $r['codigo'] ?? ''), 0, 7);
            if (!$codigo) continue;
            CnaeCatalog::on('sqlite_prospect')->updateOrCreate(
                ['codigo' => $codigo],
                ['descricao' => trim((string)($r['descricao'] ?? ''))]
            );
        }

        $this->command?->info('cnae_catalog povoada.');
    }
}
