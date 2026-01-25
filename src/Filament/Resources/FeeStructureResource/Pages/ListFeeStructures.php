<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\FeeStructureResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Memberships\Filament\Resources\FeeStructureResource;

class ListFeeStructures extends ListRecords
{
    protected static string $resource = FeeStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('memberships::memberships.pages.create_fee_structure')),
        ];
    }
}
