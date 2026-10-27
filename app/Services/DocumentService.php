<?php

namespace App\Services;

use App\Models\InformationItem;
use App\Models\InformationVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    public const DISK = 'local';

    public const FOLDER = 'information';

    /**
     * Simpan file versi baru.
     */
    public function storeVersion(
        InformationItem $item,
        UploadedFile $file,
        string $version,
        ?string $changeNotes = null,
    ): InformationVersion {
        $path = $file->store(self::FOLDER . '/' . date('Y'), self::DISK);

        return InformationVersion::create([
            'information_id' => $item->id,
            'version' => $version,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'change_notes' => $changeNotes,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Cek apakah file versi tersedia di storage.
     */
    public function exists(InformationVersion $version): bool
    {
        return $version->file_path && Storage::disk(self::DISK)->exists($version->file_path);
    }

    /**
     * Stream file ke browser (inline untuk viewer, attachment untuk download).
     */
    public function stream(InformationVersion $version, bool $download = false): StreamedResponse
    {
        abort_unless($this->exists($version), 404, 'Dokumen tidak tersedia.');

        $filename = $version->information?->document_file_name ?? $version->file_name;

        return Storage::disk(self::DISK)->response(
            $version->file_path,
            $filename,
            [],
            $download ? 'attachment' : 'inline'
        );
    }
}
