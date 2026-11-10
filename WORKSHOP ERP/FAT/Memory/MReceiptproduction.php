<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MReceiptproduction extends BaseTransactionModel
{
    protected $table              = 'grheader';
    protected $primaryKey         = 'docnum';
    public $incrementing          = false;
    protected $keyType            = 'string';

    protected string $numberPrefix      = 'RP';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'grdetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [
        null, 'a.docnum', 'a.docdate', 'a.baseentry', 'b.whsname', 'a.remark', 'a.status', null
    ];
    protected array $column_search = [
        'a.docnum', 'a.docdate', 'a.baseentry', 'b.whsname', 'a.remark', 'a.status'
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
              ->select(['a.docnum', 'a.docdate', 'a.baseentry', 'b.whsname', 'a.remark', 'a.status'])
              ->leftJoin('whsmaster as b', 'b.whscode', '=', 'a.whscode');
        
        parent::_get_datatables_query($query);
    }

    public function getWithDetails(string $docnum): ?array
    {
        $header = DB::table('grheader as a')
            ->select('a.*', 'b.remark as wocatat', 'c.whsname')           
            ->leftJoin('woheader as b', 'b.docnum', '=', 'a.baseentry')
            ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode')
            ->where('a.docnum', $docnum)
            ->first();

        if (!$header) {
            return null;
        }

        $details = DB::table('grdetail as a')
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
        return DB::table('grheader as a')
            ->select('a.docnum', 'a.docdate', 'a.baseentry', 'b.whsname', 'a.remark') 
            ->leftJoin('whsmaster as b', 'b.whscode', '=', 'a.whscode')
            ->where('a.status', 1)
            ->orderBy('a.docnum', 'DESC')
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }

    public function getOpenAll(): array
    {
        return DB::table('grheader as a')
            ->select('a.docnum', 'a.docdate', 'a.baseentry', 'b.whsname', 'a.remark')  
            ->leftJoin('whsmaster as b', 'b.whscode', '=', 'a.whscode')
            ->orderBy('a.docnum', 'DESC')
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }

    public function generateBatchNumber(string $itemCode, string $docDate): string
    {
        $year = date('y', strtotime($docDate)); 
        
        $itemMaster = DB::table('itemmaster')->where('itemcode', $itemCode)->first();
        $itemBatch  = str_pad($itemMaster->itembatch ?? '000', 3, '0', STR_PAD_LEFT);
        
        $sequence = DB::table('itembatch')
            ->where('itemcode', $itemCode)
            ->where('whscode', 'WIP') 
            ->where(function($query) {
                $query->where('areacode', '')
                      ->orWhereNull('areacode');
            })
            ->where('batchnum', 'like', $year . $itemBatch . '%')
            ->count() + 1;
        
        $seqFormatted = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        
        return $year . $itemBatch . $seqFormatted;
    }
}