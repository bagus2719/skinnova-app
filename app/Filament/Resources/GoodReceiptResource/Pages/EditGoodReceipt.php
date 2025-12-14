<?php

namespace App\Filament\Resources\GoodReceiptResource\Pages;

use App\Filament\Resources\GoodReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoodReceipt extends EditRecord
{
    protected static string $resource = GoodReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
