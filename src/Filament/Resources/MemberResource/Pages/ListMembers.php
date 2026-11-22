<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\MemberResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;
use Modules\Memberships\Filament\Actions\ImportMembersAction;
use Modules\Memberships\Filament\Resources\MemberResource;

class ListMembers extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = MemberResource::class;

    /**
     * A confirmation of «Importar socios» refused by validation forgets its upload, so the
     * file does not stay behind until the dialog is tried again.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function callMountedAction(array $arguments = []): mixed
    {
        try {
            return parent::callMountedAction($arguments);
        } catch (ValidationException $exception) {
            ImportMembersAction::forgetAfterFailedConfirmation($this);

            throw $exception;
        }
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('memberships::memberships.pages.create_member')),
            ImportMembersAction::make(),
            $this->knowledgeGuideAction('memberships.panel.overview'),
        ]);
    }
}
