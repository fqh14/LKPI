<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MExchangerate extends BaseTransactionModel
{
    protected $table            = 'exchangerateheader';
    protected $primaryKey       = 'docnum';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected $fillable = [
        'docnum', 'docdate', 'status', 'createddate', 'createdby', 'approveddate', 'approvedby', 'updateddate', 'updatedby', 'canceleddate', 'canceledby', 'closeddate', 'closedby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected string $numberPrefix      = 'ER';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'exchangeratedetail';
    public string $detailForeignKey     = 'docnum';
    
    protected array $column_order  = [null, 'docnum', 'docdate', 'status', null ];
    protected array $column_search = ['docnum', 'docdate', 'status'];
    protected array $order         = ['docnum' => 'desc'];

    public function getConflictingDocNum(string $currCode, string $startDate, string $endDate, ?string $currentDocNum = null): ?string
    {
        $query = DB::table($this->detailTable . ' as d')
            ->join($this->table . ' as h', 'h.docnum', '=', 'd.docnum')
            ->where('h.status', '!=', 0) 
            ->where('d.currcode', $currCode)
            ->where('d.startdate', '<=', $endDate)
            ->where('d.enddate', '>=', $startDate);

        if (!empty($currentDocNum)) {
            $query->where('h.docnum', '!=', $currentDocNum); 
        }

        $conflict = $query->select('h.docnum')->first();

        return $conflict ? $conflict->docnum : null;
    }

    public function getCurrencyName(string $currCode): string
    {
        $currency = DB::table('currency')->where('currcode', $currCode)->first();
        return $currency ? $currency->currname : $currCode;
    }
}