<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MItemmaster extends BaseMasterModel
{
    protected $table           = 'itemmaster';
    protected $primaryKey      = 'itemcode';
    public $incrementing       = false;
    protected $keyType         = 'string';

    protected $fillable        = ['itemcode', 'itemname', 'itemgroupcode', 'uomcode', 'itembatch', 'isstock', 'minstock', 'monthED', 'remark', 'status', 'createddate', 'updateddate', 'createdby', 'updateby'];
    
    protected string $tableAlias   = 'a';
    protected array|string $column_select = ['a.itemcode', 'a.itemname', 'b.itemgroupname', 'c.uomname', 'a.itembatch', 'a.monthED', 'a.status'];
    protected array $column_order   = [null, 'a.itemcode', 'a.itemname', 'b.itemgroupname', 'c.uomname', 'a.itembatch', 'a.monthED', 'a.status', null];
    protected array $column_search  = ['a.itemcode', 'a.itemname', 'b.itemgroupname', 'c.uomname', 'a.itembatch', 'a.monthED', 'a.status'];
    protected array $order          = ['a.itemcode' => 'asc'];

    protected function queryDatatable($query)
    {
        $query->leftJoin('itemgroup as b', 'b.itemgroupcode', '=', 'a.itemgroupcode')
              ->leftJoin('uom as c', 'c.uomcode', '=', 'a.uomcode');
    }

    public function get_by_id_array($id)
    {
        return self::where($this->primaryKey, $id)->first()?->toArray();
    }

    public function get_by_id($id)
    {
        return DB::table($this->table . ' as a')
            ->select('a.*', 'b.itemgroupname', 'c.uomname')
            ->leftJoin('itemgroup as b', 'b.itemgroupcode', '=', 'a.itemgroupcode')
            ->leftJoin('uom as c', 'c.uomcode', '=', 'a.uomcode')
            ->where('a.itemcode', $id)
            ->first();
    }
}