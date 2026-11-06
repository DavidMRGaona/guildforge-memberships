<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\FeeStructureResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Memberships\Filament\Resources\FeeStructureResource;

class ListFeeStructures extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = FeeStructureResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('memberships::memberships.pages.create_fee_structure')),
            $this->knowledgeGuideAction('memberships.panel.fee-structures'),
        ]);
    }
}
