<?php

namespace App\Models\EDM;

use App\Presenters\PresentableTrait;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    // use LogsActivity;
    use PresentableTrait;

    protected $table      = 'organization';
    protected $connection = 'crm';
    // protected static $logOnlyDirty  = true;
    protected static $logAttributes = [
        'name',
        'department',
        'title',
        'vat_no',
        'industry_type',
        'country',
        'area',
        'address',
        'phone',
        'ext',
        'fax',
        'codename',
    ];

    protected $guarded = [];

    public function members()
    {
        return $this->morphedByMany('App\Models\CRM\Member', 'organizationable', 'has_organization', 'organization_id', 'organizationable_id', 'id', 'id');
    }

    public function event_relations()
    {
        return $this->hasMany('App\Models\CRM\EventRelation', 'organization_id', 'id');
    }

    public function has_groups()
    {
        return $this->hasMany('App\Models\CRM\HasGroup', 'organization_id', 'id');
    }
}
