<?php

namespace App\Models;

class MCardmaster extends BaseMasterModel
{
    protected $table            = 'cardmaster';
    protected $primaryKey       = 'cardcode';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected $fillable         = ['cardcode', 'cardname', 'cardgroupcode', 'address', 'email', 'phone', 'subdistrict', 'city', 'termcode', 'discount', 'creditlimit', 'debpay-acct', 'dpmclear-acct', 'status', 'createddate', 'updateddate', 'createdby', 'updateby'];
    
    protected array|string $column_select = ['cardcode', 'cardname', 'address', 'phone', 'status'];
    protected array $column_order   = [null, 'cardcode', 'cardname', 'address', 'phone', 'status', null];
    protected array $column_search  = ['cardcode', 'cardname', 'address', 'phone', 'status'];
    protected array $order          = ['cardcode' => 'asc']; 
}