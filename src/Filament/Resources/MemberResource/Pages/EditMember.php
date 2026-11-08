<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\MemberResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Memberships\Filament\RelationManagers\FeesRelationManager;
use Modules\Memberships\Filament\RelationManagers\MembershipsRelationManager;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;

class EditMember extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = MemberResource::class;

    public function getTitle(): string
    {
        return __('memberships::memberships.pages.edit_member');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return __('memberships::memberships.tabs.member_data');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            DeleteAction::make(),
            RestoreAction::make(),
            $this->knowledgeGuideAction('memberships.panel.memberships'),
        ]);
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        return $this->isDeleted() ? [] : parent::getFormActions();
    }

    /**
     * A deleted member is not saved, whatever a forged request sends.
     */
    protected function beforeSave(): void
    {
        if ($this->isDeleted()) {
            $this->halt();
        }
    }

    private function isDeleted(): bool
    {
        $member = $this->getRecord();

        return $member instanceof MemberModel && $member->trashed();
    }

    public function getRelationManagers(): array
    {
        return [
            MembershipsRelationManager::class,
            FeesRelationManager::class,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
