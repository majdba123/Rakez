<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for admin notifications - only admin users can listen
Broadcast::channel('admin-notifications', function ($user) {
    // Check if user is admin
    if ($user && $user->type === 'admin') {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});
