<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\FeeStructureResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Concerns\AnnouncesTheMovedDefault;

class EditFeeStructure extends EditRecord
{
    use AnnouncesTheMovedDefault;
    use HasKnowledgeGuideAction;

    protected static string $resource = FeeStructureResource::class;

    public function getTitle(): string
    {
        return __('memberships::memberships.pages.edit_fee_structure');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            DeleteAction::make(),
            $this->knowledgeGuideAction('memberships.panel.fee-structures'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->rememberTheReplacedDefault($data, (string) $this->getRecord()->getKey());

        return $data;
    }

    protected function afterSave(): void
    {
        $this->announceTheReplacedDefault();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
