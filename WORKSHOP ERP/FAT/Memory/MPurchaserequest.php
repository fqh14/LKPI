<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MPurchaserequest extends BaseTransactionModel
{
    protected $table            = 'prheader';
    protected $primaryKey       = 'docnum';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected string $numberPrefix      = 'PR';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'prdetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [null, 'a.docnum', 'a.docdate', 'a.requireddate', 'b.whsname', 'a.remark', 'a.status', null];
    protected array $column_search = ['a.docnum', 'a.docdate', 'a.requireddate', 'b.whsname', 'a.remark', 'a.status'];
    protected array $order         = ['a.docnum' => 'DESC'];

    protected $fillable = [
        'docnum', 'docdate', 'whscode', 'requireddate', 'remark', 'status', 'createddate', 'createdby', 'updateddate', 'updatedby', 'approveddate', 'approvedby', 'closeddate', 'closedby', 'canceleddate', 'canceledby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select('a.docnum, a.docdate, a.requireddate, b.whsname, a.remark, a.status')
              ->leftJoin('whsmaster as b', 'b.whscode', '=', 'a.whscode');
        
        parent::_get_datatables_query($query);
    }

    public function getWithDetails(string $docnum): ?array
    {
        $header = DB::table('prheader as a')
            ->select('a.*', 'b.whsname')
            ->leftJoin('whsmaster as b', 'b.whscode', '=', 'a.whscode')
            ->where('a.docnum', $docnum)
            ->first();

        if (!$header) {
            return null;
        }

        $details = DB::table('prdetail as a')
            ->select('a.*', 'b.itemname', 'c.uomname')
            ->leftJoin('itemmaster as b', 'b.itemcode', '=', 'a.itemcode')
            ->leftJoin('uom as c', 'c.uomcode', '=', 'a.uomcode')
            ->where('a.docnum', $docnum)
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();

        return [
            'header'  => (array)$header,
            'details' => $details
        ];
    }

    public function getOpen(): array
    {
        $builder = DB::table('prheader as a')
                    ->select('a.docnum, a.docdate, b.whsname, a.remark')
                    ->leftJoin('whsmaster as b', 'b.whscode', '=', 'a.whscode')
                    ->where('a.status', 1);

        return $builder->orderBy('a.docnum', 'DESC')
                       ->get()
                       ->map(fn($item) => (array)$item)
                       ->toArray();
    }
}