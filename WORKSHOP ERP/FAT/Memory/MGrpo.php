<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MGrpo extends BaseTransactionModel
{
    protected $table            = 'grpoheader';
    protected $primaryKey       = 'docnum';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected string $numberPrefix      = 'GO';
    protected string $txnNumberField   = 'docnum';
    protected string $detailTable      = 'grpodetail';
    public string $detailForeignKey    = 'docnum';

    protected array $column_order  = [null, 'a.docnum', 'a.docdate', 'a.baseentry', 'b.cardname', 'a.subtotal', 'a.discountamount', 'a.taxamount', 'a.grandtotal', 'a.status', null];
    protected array $column_search = ['a.docnum', 'a.docdate', 'a.baseentry', 'b.cardname', 'a.subtotal', 'a.discountamount', 'a.taxamount', 'a.grandtotal', 'a.status'];
    protected array $order         = ['a.docdate' => 'DESC', 'a.docnum' => 'DESC'];

    protected $fillable = [
        'docnum', 'docdate', 'cardcode', 'whscode', 'currcode', 'termcode', 'remark', 'subtotal', 'discountamount', 'dppamount', 'taxclude', 'taxcode', 'taxamount', 'grandtotal', 'status', 'createddate', 'createdby', 'updateddate', 'updatedby', 'approveddate', 'approvedby', 'closeddate', 'closedby', 'canceleddate', 'canceledby', 'baseentry', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select('a.docnum, a.docdate, a.baseentry, b.cardname, a.subtotal, a.discountamount, a.taxamount, a.grandtotal, a.status')
              ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
              ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode');
        
        parent::_get_datatables_query($query);
    }

    public function getWithDetails(string $docnum): ?array
    {
        $header = DB::table('grpoheader as a')
            ->select('a.*', 'b.cardname', 'c.whsname', 'd.termname', 'e.currname')           
            ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
            ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode')
            ->leftJoin('termin as d', 'd.termcode', '=', 'a.termcode')
            ->leftJoin('currency as e', 'e.currcode', '=', 'a.currcode')
            ->where('a.docnum', $docnum)
            ->first();

        if (!$header) {
            return null;
        }

        $details = DB::table('grpodetail as a')
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

    public function getOpen(?string $cardcode = null): array
    {
        $builder = DB::table('grpoheader as a')
                    ->select('a.docnum, a.docdate, b.cardname, a.grandtotal, a.remark')
                    ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
                    ->where('a.status', 1);

        if (!empty($cardcode)) {
            $builder->where('a.cardcode', $cardcode);
        }

        return $builder->orderBy('a.docnum', 'DESC')
                       ->get()
                       ->map(fn($item) => (array)$item)
                       ->toArray();
    }

    public function getOpenAll(): array
    {
        return DB::table('grpoheader as a')
                    ->select('a.docnum, a.docdate, b.cardname, a.grandtotal, a.remark')
                    ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
                    ->where('a.status', 1)
                    ->orderBy('a.docnum', 'DESC')
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();
    }
}