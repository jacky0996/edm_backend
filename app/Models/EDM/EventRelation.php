<?php

namespace App\Models\EDM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRelation extends Model
{
    use HasFactory;
    protected $table      = 'event_relation';
    protected $connection = 'crm';
    protected $guarded    = [];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    public function mobile()
    {
        return $this->belongsTo(Mobiles::class, 'mobile_id', 'id');
    }

    public function email()
    {
        return $this->belongsTo(Emails::class, 'email_id', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }
}
