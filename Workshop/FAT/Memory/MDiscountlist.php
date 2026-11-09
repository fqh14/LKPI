<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MDiscountlist extends BaseTransactionModel
{
    protected $table              = 'discountheader';
    protected $primaryKey         = 'docnum';
    public $incrementing          = false;
    protected $keyType            = 'string';

    protected string $numberPrefix      = 'DL';    
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'discountdetail';
    public string $detailForeignKey     = 'docnum';
    
    protected array $column_order  = [null, 'docnum', 'docdate', 'status', null];
    protected array $column_search = ['docnum', 'docdate', 'status'];
    protected array $order         = ['docnum' => 'desc'];

    protected $fillable = [
        'docnum', 'docdate', 'status', 'createddate', 'createdby', 'approveddate', 'approvedby', 'updateddate', 'updatedby', 'canceleddate', 'canceledby', 'closeddate', 'closedby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    public function getMaxDiscountActive(string $cardCode, string $sellCode, string $itemCode, string $docDate): float
    {
        $result = DB::table('discountdetail as d')
                    ->select('d.maxdiscount') 
                    ->join('discountheader as h', 'h.docnum', '=', 'd.docnum')
                    ->where('h.status', 1)
                    ->where('d.cardcode', $cardCode)
                    ->where('d.sellcode', $sellCode)
                    ->where('d.itemcode', $itemCode)
                    ->where('d.startdate', '<=', $docDate)
                    ->where('d.enddate', '>=', $docDate)
                    ->first();

        return $result ? (float) $result->maxdiscount : 0.0;
    }
}