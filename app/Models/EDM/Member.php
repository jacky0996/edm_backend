<?php

namespace App\Models\EDM;

use App\Models\User;
use App\Presenters\PresentableTrait;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Member extends Model
{
    // use LogsActivity;
    use PresentableTrait;

    protected $table      = 'member';
    protected $connection = 'crm';
    // protected $presenter = 'App\Presenters\CRM\MemberPresenter';
    // protected static $logOnlyDirty  = true;
    protected static $logAttributes = [
        'name',
        'national_id',
    ];

    protected $guarded = [];

    public function groups()
    {
        return $this->morphToMany(Group::class, 'groupable', 'has_group', 'groupable_id', 'group_id', 'id', 'id');
    }

    public function mobiles()
    {
        return $this->morphToMany(Mobiles::class, 'mobileable', 'has_mobile', 'mobileable_id', 'mobile_id', 'id', 'id');
    }

    public function emails()
    {
        return $this->morphToMany(Emails::class, 'emailable', 'has_email', 'emailable_id', 'email_id', 'id', 'id');
    }

    public function organizations()
    {
        return $this->morphToMany(Organization::class, 'organizationable', 'has_organization', 'organizationable_id', 'organization_id', 'id', 'id');
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id', 'id');
    }

    public function tags()
    {
        return $this->morphToMany('App\Models\CRM\Tag', 'tagable', 'has_tag', 'tagable_id', 'tag_id', 'id', 'id');
    }

    public function qrcodes()
    {
        return $this->morphToMany('App\Models\Qrcode', 'qrcodeable', 'has_qrcode', 'qrcodeable_id', 'qrcode_id', 'id', 'id');
    }

    public function track()
    {
        return $this->hasOne('App\Models\CRM\HasTrack', 'trackable_id', 'id')->where('trackable_type', 9);
    }

    public function event_relations()
    {
        return $this->hasMany('App\Models\CRM\EventRelation', 'member_id', 'id');
    }

    public function events()
    {
        return $this->belongsToMany('App\Models\CRM\Event', 'event_relation', 'member_id', 'event_id');
    }
}
