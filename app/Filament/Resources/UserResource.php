<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Hash;

class UserResource extends Resource
{
    public const ICON = 'tabler-user';

    protected static ?string $tenantOwnershipRelationshipName = 'tenants';

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = self::ICON;

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(User::getForm());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(fn(User $record): string => 'https://ui-avatars.com/api/?background=0D8ABC&color=fff&name=' . urlencode($record->name)
                    ),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->icon('tabler-mail')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(self::getInfolistForm());
    }

    private static function getInfolistForm(): array
    {
        return [
            Section::make('User Information')
                ->columns(3)
                ->description('View user information')
                ->schema([
                    ImageEntry::make('avatar_url')
                        ->label('Avatar')
                        ->circular()
                        ->defaultImageUrl(
                            fn(User $record): string => 'https://ui-avatars.com/api/?background=0D8ABC&color=fff&name=' . urlencode($record->name)
                        ),
                    Group::make()
                        ->schema([
                            TextEntry::make('name'),
                            TextEntry::make('email')
                                ->icon('tabler-mail'),
                        ]),
                    Group::make()
                        ->schema([
                            TextEntry::make('email_verified_at')
                                ->dateTime(),
                            TextEntry::make('deleted_at')
                                ->hidden(fn(User $record): bool => ! $record->deleted_at)
                                ->dateTime(),
                            TextEntry::make('created_at')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->dateTime(),
                        ]),
                ]),
        ];
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
