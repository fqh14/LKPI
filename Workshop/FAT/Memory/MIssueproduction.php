<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MIssueproduction extends BaseTransactionModel
{
    protected $table              = 'giheader';
    protected $primaryKey         = 'docnum';
    public $incrementing          = false;
    protected $keyType            = 'string';

    protected string $numberPrefix      = 'FP';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'gidetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [
        null, 'a.docnum', 'a.docdate', 'a.baseentry', 'c.whsname', 'a.remark', 'a.status', null
    ];
    protected array $column_search = [
        'a.docnum', 'a.docdate', 'a.baseentry', 'c.whsname', 'a.remark', 'a.status'
    ];
    protected array $order = [
        'a.docdate' => 'DESC', 'a.docnum' => 'DESC'
    ];

    protected $fillable = [
        'docnum', 'docdate', 'baseentry', 'whscode', 'remark', 'status', 'createddate', 'createdby', 'updateddate', 'updatedby', 'approveddate', 'approvedby', 'canceleddate', 'canceledby', 'closeddate', 'closedby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select(['a.docnum', 'a.docdate', 'a.baseentry', 'c.whsname', 'a.remark', 'a.status'])
              ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode');
        
        parent::_get_datatables_query($query);
    }

    public function getWithDetails(string $docnum): ?array
    {
        $header = DB::table('giheader as a')
            ->select('a.*', 'b.remark as wocatat', 'c.whsname') 
            ->leftJoin('woheader as b', 'b.docnum', '=', 'a.baseentry')
            ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode')
            ->where('a.docnum', $docnum)
            ->first();

        if (!$header) {
            return null;
        }

        $baseEntry = $header->baseentry ?? '';

        $details = DB::table('gidetail as a')
            ->select('a.*', 'b.itemname', 'c.uomname', 'd.qty as qty_wo')
            ->leftJoin('itemmaster as b', 'b.itemcode', '=', 'a.itemcode')
            ->leftJoin('uom as c', 'c.uomcode', '=', 'a.uomcode')
            ->leftJoin('wodetail as d', function($join) use ($baseEntry) {
                $join->on('d.docnum', '=', DB::raw("'" . $baseEntry . "'"))
                     ->on('d.linenum', '=', 'a.baseline');
            })
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
        return DB::table('giheader as a')
                    ->select('a.docnum', 'a.docdate', 'a.baseentry', 'c.whsname', 'a.remark')  
                    ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode')
                    ->where('a.status', 1)
                    ->orderBy('a.docnum', 'DESC')
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();
    }

    public function getOpenAll(): array
    {
        return DB::table('giheader as a')
                    ->select('a.docnum', 'a.docdate', 'a.baseentry', 'c.whsname', 'a.remark')  
                    ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode')
                    ->orderBy('a.docnum', 'DESC')
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();
    }

    public function getBatchByWorkOrder(string $docnum): ?array
    {
        $result = DB::table('gidetail as a')
            ->select('a.batchnum', 'a.expdate', 'a.unitcost')
            ->join('giheader as b', 'b.docnum', '=', 'a.docnum')
            ->join('itemmaster as c', 'c.itemcode', '=', 'a.itemcode')
            ->where('b.baseentry', $docnum)
            ->where('b.status', 1)
            ->where('c.itemgroupcode', 'BDP')
            ->first();

        return $result ? (array)$result : null;
    }
}