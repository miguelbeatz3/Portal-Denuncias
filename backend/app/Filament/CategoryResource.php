<?php
namespace App\Filament;

use App\Models\Category;

class CategoryResource extends CatalogueResource
{
    protected static ?string $model = Category::class;
    protected static ?string $modelLabel = 'Categoria';
    protected static ?string $pluralModelLabel = 'Categorias';
    protected static ?string $slug = 'categorias';
    protected static string | \UnitEnum | null $navigationGroup = 'Dados do formulário';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 1;

    public static function getPages(): array
    {
        return ['index' => ManageCategory::route('/')];
    }
}
