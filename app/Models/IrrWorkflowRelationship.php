<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'irr_workflow_id',
    'remote_asn',
    'relationship_type',
    'import_ipv4',
    'import_ipv6',
    'export_ipv4',
    'export_ipv6',
    'import_policy',
    'export_policy',
    'member_of',
    'description',
    'active',
])]
class IrrWorkflowRelationship extends Model
{
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            IrrWorkflow::class,
            'irr_workflow_id'
        );
    }

    public function formattedRemoteAsn(): string
    {
        return 'AS'.$this->remote_asn;
    }

    protected function casts(): array
    {
        return [
            'remote_asn' => 'integer',
            'import_ipv4' => 'boolean',
            'import_ipv6' => 'boolean',
            'export_ipv4' => 'boolean',
            'export_ipv6' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
