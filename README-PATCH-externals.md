# Patch: Externals (distância + prefixo CNAE)

## Arquivos
- `app/Http/Requests/ListExternalClientsRequest.php`
- `app/Http/Resources/ClienteExternoResource.php`
- `database/migrations/2025_10_10_130000_idx_cnae_prefix_cliente_externo.php`

## Além disso, altere no Model `ClienteExterno` (escopo por prefixo):
```php
public function scopeFilterCnaes($q, array $cnaes) {
    $prefixes = [];
    foreach ($cnaes as $c) {
        $digits = preg_replace('/\D/','', (string) $c);
        if ($digits === '') continue;
        $prefixes[] = substr($digits, 0, 7);
    }
    $prefixes = array_values(array_unique(array_filter($prefixes)));
    if (!$prefixes) return $q;
    return $q->where(function($w) use ($prefixes) {
        foreach ($prefixes as $i => $p) {
            $like = $p.'%';
            $i===0 ? $w->where('codigo_cnae','like',$like)
                   : $w->orWhere('codigo_cnae','like',$like);
        }
    });
}
```

## Controller: método `listInBounds` (ordenação por distância)
Use o documento no painel "Adendo – Externals..." ou este trecho:
```php
$centerLat = $data['center']['lat'] ?? (($n + $s) / 2);
$centerLng = $data['center']['lng'] ?? (($e + $w) / 2);
$cosLat0   = cos(deg2rad($centerLat));

if (in_array($sort, ['distance','-distance'])) {
    $dist2Expr = '((latitude - ?) * (latitude - ?)) + (((longitude - ?) * ?) * ((longitude - ?) * ?))';
    $bindings = [$centerLat, $centerLat, $centerLng, $cosLat0, $centerLng, $cosLat0];
    $dir = $sort === 'distance' ? 'asc' : 'desc';
    $q->addSelectRaw($dist2Expr.' as dist2', $bindings)->orderByRaw('dist2 '.$dir);
}
request()->merge(['center' => ['lat' => $centerLat, 'lng' => $centerLng]]);
```
