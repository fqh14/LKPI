<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MPurchaseorder extends BaseTransactionModel
{
    protected $table            = 'poheader';
    protected $primaryKey       = 'docnum';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected string $numberPrefix      = 'PO';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'podetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [null, 'a.docnum', 'a.docdate', 'b.cardname', 'a.subtotal', 'a.discountamount', 'a.taxamount', 'a.grandtotal', 'a.status', null];
    protected array $column_search = ['a.docnum', 'a.docdate', 'b.cardname', 'a.subtotal', 'a.discountamount', 'a.taxamount', 'a.grandtotal', 'a.status'];
    protected array $order         = ['a.docnum' => 'DESC', 'a.docdate' => 'DESC'];

    protected $fillable = [
        'docnum', 'docdate', 'cardcode', 'whscode', 'currcode', 'delivereddate', 'termcode', 'subtotal', 'discountamount', 'dppamount', 'taxclude', 'taxcode', 'taxamount', 'grandtotal', 'remark', 'status', 'createddate', 'createdby', 'updateddate', 'updatedby', 'approveddate', 'approvedby', 'closeddate', 'closedby', 'canceleddate', 'canceledby', 'baseentry', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select('a.docnum, a.docdate, b.cardname, a.subtotal, a.discountamount, a.taxamount, a.grandtotal, a.status')
              ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode');
        
        parent::_get_datatables_query($query);
    }

    public function getWithDetails(string $docnum): ?array
    {
        $header = DB::table('poheader as a')
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

        $details = DB::table('podetail as a')
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
        $builder = DB::table('poheader as a')
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
}