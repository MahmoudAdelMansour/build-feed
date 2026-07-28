<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Jobs\FanoutPostJob;
use App\Models\Post;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function afterCreate(): void
    {
        // Ensure FanoutPostJob is dispatched even if observer doesn't fire
        // This can happen due to Filament's database transaction handling
        FanoutPostJob::dispatch($this->record);
    }
}
