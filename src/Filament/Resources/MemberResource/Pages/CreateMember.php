<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\MemberResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Modules\Memberships\Filament\Resources\MemberResource;

class CreateMember extends CreateRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = MemberResource::class;

    public function getTitle(): string
    {
        return __('memberships::memberships.pages.create_member');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction('memberships.panel.manage-members'),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
