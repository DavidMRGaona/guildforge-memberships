<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\FeeStructureResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Memberships\Filament\Resources\FeeStructureResource;

class CreateFeeStructure extends CreateRecord
{
    protected static string $resource = FeeStructureResource::class;

    public function getTitle(): string
    {
        return __('memberships::memberships.pages.create_fee_structure');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
