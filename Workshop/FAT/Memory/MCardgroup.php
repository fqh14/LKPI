<?php

namespace App\Models;

class MCardgroup extends BaseMasterModel
{
    protected $table            = 'cardgroup';
    protected $primaryKey       = 'cardgroupcode';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected $fillable         = ['cardgroupcode', 'cardgroupname', 'grouptype', 'createddate', 'updateddate', 'createdby', 'updateby'];
    
    protected array|string $column_select = ['cardgroupcode', 'cardgroupname', 'grouptype'];
    protected array $column_order   = [null, 'cardgroupcode', 'cardgroupname', 'grouptype', null];
    protected array $column_search  = ['cardgroupcode', 'cardgroupname'];
    protected array $order          = ['cardgroupcode' => 'asc']; 
}