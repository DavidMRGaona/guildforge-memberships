<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\MemberResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Memberships\Filament\RelationManagers\MembershipsRelationManager;
use Modules\Memberships\Filament\Resources\MemberResource;

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
            $this->knowledgeGuideAction('memberships.panel.memberships'),
        ]);
    }

    public function getRelationManagers(): array
    {
        return [
            MembershipsRelationManager::class,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
