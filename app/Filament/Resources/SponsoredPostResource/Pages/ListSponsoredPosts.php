<?php

namespace App\Filament\Resources\SponsoredPostResource\Pages;

use App\Filament\Resources\SponsoredPostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSponsoredPosts extends ListRecords
{
    protected static string $resource = SponsoredPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
