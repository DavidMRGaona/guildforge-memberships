<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\MemberResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Memberships\Filament\Resources\MemberResource;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    public function getTitle(): string
    {
        return __('memberships::memberships.pages.create_member');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
