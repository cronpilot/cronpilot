<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerCredentialResource\Pages\CreateServerCredential;
use App\Filament\Resources\ServerCredentialResource\Pages\ListServerCredentials;
use App\Models\ServerCredential;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ServerCredentialResource extends Resource
{
    public const ICON = 'tabler-id-badge-2';

    protected static ?string $model = ServerCredential::class;

    protected static ?string $navigationIcon = self::ICON;

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->required()
                    ->maxLength(255),
                TextArea::make('ssh_private_key')
                    ->required()
                    ->hiddenOn(['view']),
                TextInput::make('passphrase')
                    ->password()
                    ->hiddenOn(['view']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('username')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServerCredentials::route('/'),
            'create' => CreateServerCredential::route('/create'),
        ];
    }
}
