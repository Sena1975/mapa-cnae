<?php
namespace App\Console\Commands;


use App\Jobs\ProspectExternalClientsJob;
use Illuminate\Console\Command;


class ProspectExternalsCommand extends Command
{
protected $signature = 'prospect:externals \
{--bounds= : JSON {"n":..,"s":..,"e":..,"w":..}} \
{--cnaes= : Lista separada por vírgula} \
{--cidade=} \
{--uf=}';


protected $description = 'Dispara prospecção de clientes externos por CNAEs e área (bounds).';


public function handle(): int
{
$bounds = json_decode($this->option('bounds') ?: '{}', true);
if (!is_array($bounds) || !isset($bounds['n'],$bounds['s'],$bounds['e'],$bounds['w'])) {
$this->error('Parâmetro --bounds inválido. Ex: {"n":-12.9,"s":-13.1,"e":-38.4,"w":-38.6}');
return self::FAILURE;
}


$cnaes = array_filter(array_map('trim', explode(',', (string)$this->option('cnaes'))));
if (!$cnaes) {
$this->error('Informe --cnaes=1234567,2345678');
return self::FAILURE;
}


$cidade = $this->option('cidade');
$uf = $this->option('uf');


dispatch(new ProspectExternalClientsJob($bounds, $cnaes, $cidade, $uf));
$this->info('Job enfileirado na queue "prospect".');
return self::SUCCESS;
}
}