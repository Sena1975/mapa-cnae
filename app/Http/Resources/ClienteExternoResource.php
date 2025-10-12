<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClienteExternoResource extends JsonResource
{
    public function toArray($req)
    {
        $center = $req->input('center');
        $distanceKm = null;
        if (is_array($center) && isset($center['lat'],$center['lng']) && $this->latitude !== null && $this->longitude !== null) {
            $distanceKm = $this->haversineKm((float)$center['lat'], (float)$center['lng'], (float)$this->latitude, (float)$this->longitude);
        }

        return [
            'cnpj'           => $this->cnpj,
            'razao_social'   => $this->razao_social,
            'nome_fantasia'  => $this->nome_fantasia,
            'codigo_cnae'    => $this->codigo_cnae,
            'descricao_cnae' => $this->descricao_cnae,
            'cidade'         => $this->cidade,
            'uf'             => $this->uf,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'enriched_at'    => optional($this->enriched_at)->toIso8601String(),
            'distance_km'    => $distanceKm,
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371.0; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return round($R * $c, 3);
    }
}
