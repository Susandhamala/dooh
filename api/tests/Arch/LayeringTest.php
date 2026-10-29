<?php

// Route → Middleware → Controller → Form Request → Service → Repository → Model → Resource

arch('no debugging calls are left in')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('controllers do not touch models or the query builder directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(['App\Models', 'Illuminate\Support\Facades\DB', 'App\Repositories']);

arch('services do not depend on HTTP')
    ->expect('App\Services')
    ->not->toUse(['Illuminate\Http\Request', 'App\Http\Controllers']);

arch('repositories are only used by services')
    ->expect('App\Repositories')
    ->toOnlyBeUsedIn(['App\Services', 'App\Providers', 'App\Repositories']);

arch('models do not reach up into services or HTTP')
    ->expect('App\Models')
    ->not->toUse(['App\Services', 'App\Http']);
