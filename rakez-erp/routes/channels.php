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

// Private channel for ADMIN notifications - only admin users
Broadcast::channel('admin-notifications', function (User $user) {
    return $user->type === 'admin';
});

// Private channel for USER notifications - only the specific user
Broadcast::channel('user-notifications.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Public channel - no authorization needed (handled automatically)
