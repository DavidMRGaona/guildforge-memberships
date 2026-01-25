<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\MemberResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Memberships\Filament\Resources\MemberResource;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('memberships::memberships.pages.create_member')),
        ];
    }
}
