<?php

namespace App\Models;

class MTermin extends BaseMasterModel
{
    protected $table            = 'termin';
    protected $primaryKey       = 'termcode';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected $fillable         = ['termcode', 'termname', 'day', 'status', 'createddate', 'updateddate', 'createdby', 'updateby'];
    
    protected array|string $column_select = ['termcode', 'termname', 'day', 'status'];
    protected array $column_order   = [null, 'termcode', 'termname', 'day', 'status', null];
    protected array $column_search  = ['termcode', 'termname', 'day'];
    protected array $order          = ['day' => 'asc']; 
}