<?php

namespace App\Filament\Resources\AppVersions\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AppVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version_code')
                    ->label('Version code')
                    ->helperText('Must match the Android Bundle Version Code in Unity, and must be HIGHER than the previous release. This number is what decides whether a player is out of date.')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->unique(ignoreRecord: true),

                TextInput::make('version_name')
                    ->label('Version name')
                    ->helperText('Shown to players, e.g. 1.0.4')
                    ->required()
                    ->maxLength(20),

                FileUpload::make('apk_file')
                    ->label('APK file')
                    ->helperText('Upload the .apk here, OR leave this empty and paste a download link below. On hosting with a temporary filesystem, uploaded files can be lost when the server restarts — a link is safer.')
                    ->disk('public')
                    ->directory('apk')
                    ->acceptedFileTypes(['application/vnd.android.package-archive', 'application/octet-stream'])
                    ->maxSize(204800)   // 200 MB
                    ->downloadable(),

                TextInput::make('download_url')
                    ->label('Or download link')
                    ->helperText('An external link to the APK (Google Drive, S3, your own server). If filled in, this is used instead of the uploaded file.')
                    ->url()
                    ->maxLength(500),

                Textarea::make('changelog')
                    ->label("What's new")
                    ->helperText('Shown to players in the update popup.')
                    ->rows(4)
                    ->columnSpanFull(),

                Toggle::make('is_mandatory')
                    ->label('Force update')
                    ->helperText('WARNING: when ON, every player on an older build is BLOCKED from playing until they install this one.')
                    ->default(false),

                Toggle::make('is_active')
                    ->label('Published')
                    ->helperText('Only published releases are offered to players. Turn off to pull a release back.')
                    ->default(true),
            ]);
    }
}
