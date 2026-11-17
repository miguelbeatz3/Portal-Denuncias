<?php
namespace App\Filament;

use App\Models\Municipality;

class MunicipalityResource extends CatalogueResource
{
    protected static ?string $model = Municipality::class;
    protected static ?string $modelLabel = 'Município';
    protected static ?string $pluralModelLabel = 'Municípios';
    protected static ?string $slug = 'municipios';
    protected static string | \UnitEnum | null $navigationGroup = 'Dados do formulário';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 2;

    public static function getPages(): array
    {
        return ['index' => ManageMunicipality::route('/')];
    }
}
