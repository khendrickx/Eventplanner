<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EventCollaborator extends Pivot
{
    protected $table = 'event_collaborators';

    protected $fillable = ['role'];
}
