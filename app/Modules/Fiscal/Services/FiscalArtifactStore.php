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

    public function store(FiscalDocument $document, string $type, string $contents, string $mimeType, ?string $originalFilename = null, ?int $actorUserId = null): FiscalArtifact
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
        $safeName = $originalFilename === null ? null : preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalFilename));
        $artifact = FiscalArtifact::query()->create(['fiscal_document_id' => $document->id, 'type' => $type, 'storage_disk' => $disk, 'storage_path' => $path, 'original_filename' => $safeName, 'mime_type' => $mimeType, 'sha256' => $hash, 'size' => strlen($contents)]);
        $this->audit->record('fiscal', 'fiscal.artifact.uploaded', $actorUserId, FiscalDocument::class, $document->id, ['artifact_id' => $artifact->id, 'type' => $type, 'sha256' => $hash]);
        return $artifact;
    }

    public function remove(FiscalArtifact $artifact, int $actorUserId): void
    {
        $artifact->loadMissing('document');
        Storage::disk($artifact->storage_disk)->delete($artifact->storage_path);
        $this->audit->record('fiscal', 'fiscal.artifact.removed', $actorUserId, FiscalDocument::class, $artifact->document->id, ['artifact_id' => $artifact->id, 'type' => $artifact->type, 'sha256' => $artifact->sha256]);
        $artifact->delete();
    }
}
