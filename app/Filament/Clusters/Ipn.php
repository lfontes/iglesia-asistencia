<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class Ipn extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'IPN';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'IPN';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
