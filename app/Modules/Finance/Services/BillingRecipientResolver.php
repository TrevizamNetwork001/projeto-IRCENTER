<?php

namespace App\Modules\Finance\Services;

use App\Models\ClientContact;
use App\Modules\Finance\Models\BillingContract;

final class BillingRecipientResolver
{
    public function resolve(
        BillingContract $contract,
    ): ?string {
        $override = trim(
            (string) $contract->billing_email_override
        );

        if (
            $override !== ''
            && filter_var(
                $override,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return $override;
        }

        $financial = $this->contactEmail(
            $contract->core_client_id,
            ClientContact::TYPE_FINANCIAL,
        );

        if ($financial !== null) {
            return $financial;
        }

        if (
            ! config(
                'finance_fiscal.finance.'
                .'allow_general_email_fallback',
                false
            )
        ) {
            return null;
        }

        return $this->contactEmail(
            $contract->core_client_id,
            ClientContact::TYPE_GENERAL,
        );
    }

    private function contactEmail(
        int $clientId,
        string $type,
    ): ?string {
        $contacts = ClientContact::query()
            ->where('client_id', $clientId)
            ->where('type', $type)
            ->where('active', true)
            ->whereNotNull('email')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        foreach ($contacts as $contact) {
            $email = trim(
                (string) $contact->email
            );

            if (
                filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                return $email;
            }
        }

        return null;
    }
}
