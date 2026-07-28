<?php

namespace App\Filament\Resources\SponsoredPostResource\Pages;

use App\Filament\Resources\SponsoredPostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSponsoredPost extends EditRecord
{
    protected static string $resource = SponsoredPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
