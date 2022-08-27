<?php

namespace App\Models;

use App\TraitClass\SearchScopeTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class CommReward extends BaseModel
{
    protected $table = 'community_reward';

    public function bbs(){
        return $this->hasOne(CommBbs::class,'id','bbs_id');
    }

}
