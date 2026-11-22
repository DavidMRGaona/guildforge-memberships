@php
    use Modules\Memberships\Application\DTOs\Import\MemberImportRowReportDTO;
    use Modules\Memberships\Application\Services\MemberImportServiceInterface;
    use Modules\Memberships\Domain\Enums\MemberImportColumn;
    use Modules\Memberships\Domain\Enums\MemberImportRowStatus;

    /** @var \Modules\Memberships\Application\DTOs\Import\MemberImportPreviewDTO|null $preview */
    /** @var string|null $error */
    $listed = $preview === null ? [] : array_values(array_filter(
        $preview->rows,
        static fn (MemberImportRowReportDTO $row): bool => $row->status !== MemberImportRowStatus::New
            || $row->linkedUserId !== null
            || $row->linkSkipReason !== null,
    ));
    $columnLabel = static fn (string $code): string => MemberImportColumn::tryFrom($code)?->label() ?? $code;
    $cell = 'padding: 0.5rem 0.75rem; text-align: start; vertical-align: top; overflow-wrap: anywhere;';
@endphp

<div class="gf-member-import-preview" style="display: grid; gap: 1rem; font-size: 0.875rem;">
    {{-- The panel theme does not compile module classes: the error colour follows the scheme here --}}
    <style>
        .gf-member-import-preview .gf-member-import-error { color: var(--danger-600); }
        .dark .gf-member-import-preview .gf-member-import-error { color: var(--danger-400); }
    </style>

    @if ($error !== null)
        <p class="gf-member-import-error">{{ $error }}</p>
    @elseif ($preview === null)
        <p>{{ __('memberships::memberships.import.preview.no_file') }}</p>
    @else
        <ul style="display: grid; gap: 0.25rem; margin: 0; padding: 0; list-style: none;">
            <li>
                <x-filament::badge color="success" style="display: inline-flex;">
                    {{ trans_choice('memberships::memberships.import.preview.new', $preview->newCount(), ['count' => $preview->newCount()]) }}
                </x-filament::badge>
            </li>
            @if ($preview->duplicateCount() > 0)
                <li>
                    <x-filament::badge color="gray" style="display: inline-flex;">
                        {{ trans_choice('memberships::memberships.import.preview.duplicate', $preview->duplicateCount(), ['count' => $preview->duplicateCount()]) }}
                    </x-filament::badge>
                </li>
            @endif
            @if ($preview->invalidCount() > 0)
                <li>
                    <x-filament::badge color="danger" style="display: inline-flex;">
                        {{ trans_choice('memberships::memberships.import.preview.invalid', $preview->invalidCount(), ['count' => $preview->invalidCount()]) }}
                    </x-filament::badge>
                </li>
            @endif
            @if ($preview->linkedCount() > 0)
                <li>
                    <x-filament::badge color="info" style="display: inline-flex;">
                        {{ trans_choice('memberships::memberships.import.preview.linked', $preview->linkedCount(), ['count' => $preview->linkedCount()]) }}
                    </x-filament::badge>
                </li>
            @endif
        </ul>

        @if ($preview->rowLimitExceeded)
            <p style="font-weight: 600;">{{ __('memberships::memberships.import.preview.too_many_rows', ['max' => MemberImportServiceInterface::MAX_ROWS]) }}</p>
        @elseif ($preview->repeatedColumns !== [])
            <p style="font-weight: 600;">{{ __('memberships::memberships.import.preview.repeated_columns', ['columns' => implode(', ', $preview->repeatedColumns)]) }}</p>
        @elseif ($preview->invalidCount() > 0)
            <p style="font-weight: 600;">{{ __('memberships::memberships.import.preview.blocked_by_errors') }}</p>
        @elseif ($preview->newCount() === 0)
            <p style="font-weight: 600;">{{ __('memberships::memberships.import.preview.nothing_new') }}</p>
        @else
            <p>{{ __('memberships::memberships.import.preview.ready') }}</p>
        @endif

        @if ($preview->unknownColumns !== [])
            <p>{{ __('memberships::memberships.import.preview.unknown_columns', ['columns' => implode(', ', $preview->unknownColumns)]) }}</p>
        @endif

        @if ($listed !== [])
            <div>
                <p style="font-weight: 600; margin-bottom: 0.5rem;">{{ __('memberships::memberships.import.preview.table_heading') }}</p>
                <table style="width: 100%; border-collapse: collapse; table-layout: auto;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgb(127 127 127 / 0.3);">
                            <th scope="col" style="{{ $cell }} padding-inline-start: 0; font-weight: 600; width: 1%; white-space: nowrap;">{{ __('memberships::memberships.import.preview.columns.line') }}</th>
                            <th scope="col" style="{{ $cell }} font-weight: 600;">{{ __('memberships::memberships.import.preview.columns.member') }}</th>
                            <th scope="col" style="{{ $cell }} padding-inline-end: 0; font-weight: 600;">{{ __('memberships::memberships.import.preview.columns.outcome') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listed as $row)
                            <tr style="border-bottom: 1px solid rgb(127 127 127 / 0.2);">
                                <th scope="row" style="{{ $cell }} padding-inline-start: 0; font-weight: 400; font-variant-numeric: tabular-nums; white-space: nowrap;">{{ $row->line }}</th>
                                <td style="{{ $cell }}">
                                    <span>{{ $row->fullName ?? __('memberships::memberships.import.preview.no_name') }}</span>
                                    @if ($row->memberNumber !== null)
                                        <span style="display: block; opacity: 0.75; font-variant-numeric: tabular-nums;">{{ $row->memberNumber }}</span>
                                    @endif
                                </td>
                                <td style="{{ $cell }} padding-inline-end: 0;">
                                    @switch ($row->status)
                                        @case (MemberImportRowStatus::Invalid)
                                            <x-filament::badge color="danger" style="display: inline-flex;">{{ __('memberships::memberships.import.preview.status.invalid') }}</x-filament::badge>
                                            <ul style="margin: 0.25rem 0 0; padding-inline-start: 1rem; list-style: disc;">
                                                @foreach ($row->errors as $column => $messages)
                                                    @foreach ($messages as $message)
                                                        <li>{{ $columnLabel((string) $column) }}: {{ $message }}</li>
                                                    @endforeach
                                                @endforeach
                                            </ul>
                                            @break
                                        @case (MemberImportRowStatus::Duplicate)
                                            <x-filament::badge color="gray" style="display: inline-flex;">{{ __('memberships::memberships.import.preview.status.duplicate') }}</x-filament::badge>
                                            @if ($row->duplicateReason !== null)
                                                <p style="margin-top: 0.25rem;">{{ __('memberships::memberships.import.preview.duplicate_reason.'.$row->duplicateReason->value) }}</p>
                                            @endif
                                            @break
                                        @default
                                            <x-filament::badge color="success" style="display: inline-flex;">{{ __('memberships::memberships.import.preview.status.new') }}</x-filament::badge>
                                            @if ($row->linkedUserId !== null)
                                                <p style="margin-top: 0.25rem;">{{ __('memberships::memberships.import.preview.links_to', ['account' => $row->linkedUserName ?? '']) }}</p>
                                            @elseif ($row->linkSkipReason !== null)
                                                <p style="margin-top: 0.25rem;">{{ $row->linkSkipReason->message() }}</p>
                                            @endif
                                    @endswitch
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
