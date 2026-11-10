<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MPricelist extends BaseTransactionModel
{
    protected $table              = 'pricelistheader';
    protected $primaryKey         = 'docnum';
    public $incrementing          = false;
    protected $keyType            = 'string';

    protected string $numberPrefix      = 'PL';    
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'pricelistdetail';
    public string $detailForeignKey     = 'docnum';
    
    protected array $column_order  = [null, 'docnum', 'docdate', 'status', null];
    protected array $column_search = ['docnum', 'docdate', 'status'];
    protected array $order         = ['docnum' => 'desc'];

    protected $fillable = [
        'docnum', 'docdate', 'status', 'createddate', 'createdby', 'approveddate', 'approvedby', 'updateddate', 'updatedby', 'canceleddate', 'canceledby', 'closeddate', 'closedby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    public function getPricelistActive(string $itemCode, string $docDate, string $sellCode): ?array
    {
        $result = DB::table('pricelistdetail as d')
            ->select('d.pricevalue', 'd.startdate', 'd.enddate')
            ->join('pricelistheader as h', 'h.docnum', '=', 'd.docnum')
            ->where('h.status', 1)
            ->where('d.itemcode', $itemCode)
            ->where('d.sellcode', $sellCode) 
            ->where('d.startdate', '<=', $docDate) 
            ->where('d.enddate', '>=', $docDate)   
            ->first();

        return $result ? (array)$result : null;
    }
}