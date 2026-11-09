<?php

namespace App\Models;

class MUom extends BaseMasterModel
{
    protected $table            = 'uom';
    protected $primaryKey       = 'uomcode';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected $fillable         = ['uomcode', 'uomname', 'status', 'createddate', 'updateddate', 'createdby', 'updateby'];
    
    protected array|string $column_select = ['uomcode', 'uomname', 'status'];
    protected array $column_order   = [null, 'uomcode', 'uomname', 'status', null];
    protected array $column_search  = ['uomcode', 'uomname'];
    protected array $order          = ['uomcode' => 'asc']; 
}