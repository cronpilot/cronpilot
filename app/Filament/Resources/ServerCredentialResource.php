<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerCredentialResource\Pages\CreateServerCredential;
use App\Filament\Resources\ServerCredentialResource\Pages\EditServerCredential;
use App\Filament\Resources\ServerCredentialResource\Pages\ListServerCredentials;
use App\Models\ServerCredential;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Route;

class ServerCredentialResource extends Resource
{
    public const ICON = 'tabler-id-badge-2';

    protected static ?string $model = ServerCredential::class;

    protected static ?string $navigationIcon = self::ICON;

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        $schema = [
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            TextInput::make('username')
                ->required()
                ->maxLength(255),
        ];

        if ($form->getOperation() === 'create') {
            $schema[] = TextArea::make('ssh_private_key')
                ->required();
            $schema[] = TextInput::make('passphrase')
                ->password();
        }

        return $form
            ->schema($schema);
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
                EditAction::make(),
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
            'edit' => EditServerCredential::route('/{record}/edit'),
        ];
    }
}
