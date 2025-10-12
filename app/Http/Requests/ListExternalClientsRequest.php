<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListExternalClientsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'bounds'      => ['required'],
            'bounds.n'    => ['required','numeric'],
            'bounds.s'    => ['required','numeric'],
            'bounds.e'    => ['required','numeric'],
            'bounds.w'    => ['required','numeric'],

            'center'      => ['nullable','array'],
            'center.lat'  => ['nullable','numeric'],
            'center.lng'  => ['nullable','numeric'],

            'cidade'      => ['nullable','string','max:80'],
            'uf'          => ['nullable','string','size:2'],
            'cnaes'       => ['nullable','array','max:50'],
            'cnaes.*'     => ['string','max:20'],

            'page'        => ['nullable','integer','min:1'],
            'per_page'    => ['nullable','integer','min:10','max:200'],
            'sort'        => ['nullable','string','in:enriched_at,-enriched_at,nome,-nome,distance,-distance'],
        ];
    }
}
