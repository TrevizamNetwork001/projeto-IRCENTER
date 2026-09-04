<?php

namespace App\Http\Requests;

use App\Models\IrrMaintainer;
use App\Support\PrefixNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class IrrRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = PrefixNormalizer::normalize((string) $this->input('prefix', ''));

        if ($normalized !== null) {
            $this->merge([
                'prefix' => $normalized['prefix'],
                'version' => $normalized['version'],
            ]);
        }

        // É natural digitar "AS64500" no campo de ASN — aceita e
        // normaliza antes da regra "integer" validar.
        if ($this->filled('origin_asn')) {
            $digits = preg_replace('/^AS/i', '', trim((string) $this->input('origin_asn')));

            if ($digits !== null && $digits !== '' && ctype_digit($digits)) {
                $this->merge(['origin_asn' => (int) $digits]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function routeRules(?int $ignoreRouteId = null): array
    {
        $uniqueRule = Rule::unique('irr_routes')
            ->where(fn ($query) => $query
                ->where('origin_asn', $this->input('origin_asn'))
                ->where('source', 'TC'));

        if ($ignoreRouteId !== null) {
            $uniqueRule->ignore($ignoreRouteId);
        }

        return [
            'irr_maintainer_id' => ['required', 'integer', Rule::exists('irr_maintainers', 'id')],
            'prefix' => ['required', 'string', 'max:64', $uniqueRule],
            'version' => ['required', 'integer', Rule::in([4, 6])],
            'origin_asn' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'descr' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $prefix = (string) $this->input('prefix', '');

            if (PrefixNormalizer::normalize($prefix) === null) {
                $validator->errors()->add('prefix', 'Prefixo inválido — use notação CIDR (ex.: 192.0.2.0/24).');

                return;
            }

            if (! $this->filled('irr_maintainer_id')) {
                return;
            }

            $maintainer = IrrMaintainer::query()->find($this->input('irr_maintainer_id'));

            if ($maintainer === null) {
                return;
            }

            // O TC não aceita objetos proxy: a origem tem que ser o mesmo
            // AS do maintainer usado para publicar o objeto.
            if ((int) $this->input('origin_asn') !== $maintainer->asn) {
                $validator->errors()->add(
                    'origin_asn',
                    'O AS de origem deve ser igual ao ASN do maintainer selecionado (o TC não aceita objetos proxy).'
                );
            }
        });
    }
}
