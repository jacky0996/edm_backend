<?php

namespace App\Models\EDM;

use App\Models\CRM\Common\CRMUser;
use App\Presenters\PresentableTrait;
use Illuminate\Database\Eloquent\Model;

// use Spatie\Activitylog\Traits\LogsActivity;

class Group extends Model
{
    // use LogsActivity;
    use PresentableTrait;

    protected $table                = 'group';
    protected $connection           = 'crm';
    protected $presenter            = 'App\Presenters\CRM\GroupPresenter';
    protected static $logOnlyDirty  = true;
    protected static $logAttributes = [
        'name',
        'note',
        'creator_id',
        'status',
    ];

    protected $guarded = [];

    public function creator()
    {
        return $this->belongsTo(CRMUser::class, 'creator_id', 'id');
    }

    public function members()
    {
        return $this->morphedByMany(Member::class, 'groupable', 'has_group', 'group_id', 'groupable_id', 'id', 'id');
    }

    public function events()
    {
        return $this->morphedByMany(Event::class, 'groupable', 'has_group', 'group_id', 'groupable_id', 'id', 'id');
    }

    public function track()
    {
        return $this->hasOne('App\Models\CRM\HasTrack', 'trackable_id', 'id')->where('trackable_type', 8);
    }

    public function has_members()
    {
        return $this->hasMany('App\Models\CRM\HasGroup', 'group_id', 'id')->where('groupable_type', 'App\Models\CRM\Member')->whereHas('member')->with('member');
    }

    public function has_groups()
    {
        return $this->hasMany('App\Models\CRM\HasGroup', 'group_id', 'id');
    }

    public function send_mail()
    {
        return $this->morphToMany('App\Models\CRM\HasGroup', 'sendmailable', 'has_send_mail', 'sendmailable_id', 'sendmail_id', 'id', 'id');
    }

    public function send_sms()
    {
        return $this->morphToMany('App\Models\CRM\SendSMS', 'sendsmsable', 'has_send_sms', 'sendsmsable_id', 'sendsms_id', 'id', 'id');
    }

    public function tags()
    {
        return $this->morphToMany('App\Models\CRM\Tag', 'tagable', 'has_tag', 'tagable_id', 'tag_id', 'id', 'id');
    }
}
