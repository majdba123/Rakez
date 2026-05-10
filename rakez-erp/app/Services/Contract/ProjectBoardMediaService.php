<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\ProjectMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectBoardMediaService
{
    /**
     * @param  array<int, UploadedFile>  $images
     * @return Collection<int, ProjectMedia>
     */
    public function upload(int $contractId, array $images, ?string $kind = null, ?string $note = null): Collection
    {
        $contract = Contract::query()->findOrFail($contractId);
        $uploaded = collect();

        DB::transaction(function () use ($contract, $images, $kind, $note, &$uploaded): void {
            foreach ($images as $image) {
                $storedPath = $image->store("projects/{$contract->id}/boards", 'public');

                $uploaded->push(ProjectMedia::create([
                    'contract_id' => $contract->id,
                    'type' => 'image',
                    'department' => 'boards',
                    'kind' => $kind ?? 'board_image',
                    'note' => $note,
                    'url' => Storage::disk('public')->url($storedPath),
                ]));
            }
        });

        return $uploaded;
    }
}
