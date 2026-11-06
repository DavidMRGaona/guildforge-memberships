<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\FeeStructureResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Modules\Memberships\Filament\Resources\FeeStructureResource;

class CreateFeeStructure extends CreateRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = FeeStructureResource::class;

    public function getTitle(): string
    {
        return __('memberships::memberships.pages.create_fee_structure');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction('memberships.panel.fee-structures'),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
