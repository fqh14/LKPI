<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MCurrency extends BaseMasterModel
{
    protected $table            = 'currency';
    protected $primaryKey       = 'currcode';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected $fillable         = ['currcode', 'currname', 'status', 'createddate', 'updateddate', 'createdby', 'updateby'];
    
    protected array|string $column_select = ['currcode', 'currname', 'status'];
    protected array $column_order   = [null, 'currcode', 'currname', 'status', null];
    protected array $column_search  = ['currcode', 'currname'];
    protected array $order          = ['currcode' => 'asc']; 

    public function getActiveCurrencies(): array
    {
        return DB::table($this->table)
                    ->where('currcode', '!=', 'IDR')
                    ->where('status', 1)
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();
    }
}