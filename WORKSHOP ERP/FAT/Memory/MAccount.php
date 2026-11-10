<?php

namespace App\Models;

class MAccount extends BaseMasterModel
{
    protected $table            = 'account';
    protected $primaryKey       = 'acctcode';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected $fillable         = ['acctcode', 'acctname', 'status', 'createddate', 'updateddate', 'createdby', 'updateby'];
    
    protected array|string $column_select = ['acctcode', 'acctname', 'status'];
    protected array $column_order   = [null, 'acctcode', 'acctname', 'status', null];
    protected array $column_search  = ['acctcode', 'acctname'];
    protected array $order          = ['acctcode' => 'asc']; 
}