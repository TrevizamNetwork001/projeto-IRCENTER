<?php

namespace App\Services;

use App\Models\Prefix;
use App\Models\RpkiRoa;
use App\Models\RpkiValidation;
use App\Support\PrefixMath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RpkiValidationService
{
    public function validate(Prefix $prefix): RpkiValidation
    {
        $prefix->loadMissing('autonomousSystem');

        $parsedPrefix = PrefixMath::parse($prefix->prefix);

        if ($parsedPrefix === null) {
            return $this->record(
                prefix: $prefix,
                status: RpkiValidation::STATUS_ERROR,
                reason: 'invalid_prefix',
                details: 'O prefixo cadastrado não possui formato CIDR válido.'
            );
        }

        if ($prefix->autonomousSystem === null) {
            return $this->record(
                prefix: $prefix,
                status: RpkiValidation::STATUS_ERROR,
                reason: 'missing_origin_asn',
                details: 'O prefixo não possui ASN de origem vinculado.'
            );
        }

        $originAsn = $prefix->autonomousSystem->asn;

        /** @var Collection<int, RpkiRoa> $coveringRoas */
        $coveringRoas = RpkiRoa::query()
            ->where('active', true)
            ->where('status', 'active')
            ->where('ip_version', $prefix->ip_version)
            ->get()
            ->filter(
                fn (RpkiRoa $roa): bool => PrefixMath::contains(
                    $roa->prefix,
                    $prefix->prefix
                )
            )
            ->values();

        if ($coveringRoas->isEmpty()) {
            return $this->record(
                prefix: $prefix,
                status: RpkiValidation::STATUS_NOT_FOUND,
                reason: 'no_covering_roa',
                originAsn: $originAsn
            );
        }

        $validRoa = $coveringRoas->first(function (
            RpkiRoa $roa
        ) use ($originAsn, $parsedPrefix): bool {
            return $roa->asn === $originAsn
                && $parsedPrefix['length'] <= $roa->max_length;
        });

        if ($validRoa instanceof RpkiRoa) {
            return $this->record(
                prefix: $prefix,
                status: RpkiValidation::STATUS_VALID,
                reason: 'matching_roa',
                originAsn: $originAsn,
                roa: $validRoa,
                matchingRoasCount: $coveringRoas->count(),
                matchedMaxLength: $validRoa->max_length
            );
        }

        $sameOriginRoa = $coveringRoas->first(
            fn (RpkiRoa $roa): bool => $roa->asn === $originAsn
        );

        $reason = $sameOriginRoa instanceof RpkiRoa
            ? 'max_length_exceeded'
            : 'origin_asn_mismatch';

        return $this->record(
            prefix: $prefix,
            status: RpkiValidation::STATUS_INVALID,
            reason: $reason,
            originAsn: $originAsn,
            roa: $sameOriginRoa ?? $coveringRoas->first(),
            matchingRoasCount: $coveringRoas->count(),
            matchedMaxLength: $sameOriginRoa?->max_length
        );
    }

    private function record(
        Prefix $prefix,
        string $status,
        string $reason,
        ?int $originAsn = null,
        ?RpkiRoa $roa = null,
        int $matchingRoasCount = 0,
        ?int $matchedMaxLength = null,
        ?string $details = null
    ): RpkiValidation {
        return DB::transaction(
            fn (): RpkiValidation => RpkiValidation::query()->create([
                'prefix_id' => $prefix->id,
                'rpki_roa_id' => $roa?->id,
                'status' => $status,
                'reason' => $reason,
                'validated_prefix' => $prefix->prefix,
                'ip_version' => $prefix->ip_version,
                'validated_asn' => $originAsn,
                'matched_max_length' => $matchedMaxLength,
                'matching_roas_count' => $matchingRoasCount,
                'source' => 'LOCAL',
                'details' => $details,
                'metadata' => [
                    'client_id' => $prefix->client_id,
                    'autonomous_system_id' => $prefix->autonomous_system_id,
                ],
                'checked_at' => now(),
            ])
        );
    }
}
