<?php

namespace App\Filament\Resources\SalesQuotationResource\Pages;

use App\Filament\Resources\SalesQuotationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesQuotation extends EditRecord
{
    protected static string $resource = SalesQuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
