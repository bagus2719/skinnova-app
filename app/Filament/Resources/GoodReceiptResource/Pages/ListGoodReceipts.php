<?php

namespace App\Filament\Resources\GoodReceiptResource\Pages;

use App\Filament\Resources\GoodReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoodReceipts extends ListRecords
{
    protected static string $resource = GoodReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
