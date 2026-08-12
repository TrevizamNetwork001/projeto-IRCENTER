<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Models\FiscalArtifact;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FiscalArtifactStore
{
    public function __construct(private readonly DomainAudit $audit) {}

    public function store(FiscalDocument $document, string $type, string $contents, string $mimeType): FiscalArtifact
    {
        $disk = (string) config('finance_fiscal.fiscal.artifact_disk', 'local');
        if ($disk === 'public' || config("filesystems.disks.{$disk}.visibility") === 'public') {
            throw new RuntimeException('Artefatos fiscais exigem storage privado.');
        }
        $hash = hash('sha256', $contents);
        $path = "fiscal/{$document->public_id}/{$type}-{$hash}";
        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('Não foi possível armazenar o artefato fiscal.');
        }
        $artifact = FiscalArtifact::query()->create(['fiscal_document_id' => $document->id, 'type' => $type, 'storage_disk' => $disk, 'storage_path' => $path, 'mime_type' => $mimeType, 'sha256' => $hash, 'size' => strlen($contents)]);
        $this->audit->record('fiscal', 'artifact.stored', null, FiscalArtifact::class, $artifact->id, ['document_public_id' => $document->public_id, 'type' => $type, 'sha256' => $hash]);
        return $artifact;
    }
}
