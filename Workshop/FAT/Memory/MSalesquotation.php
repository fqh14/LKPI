<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MSalesquotation extends BaseTransactionModel
{
    protected $table            = 'sqheader';
    protected $primaryKey       = 'docnum';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected string $numberPrefix      = 'SQ';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'sqdetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [null, 'a.docnum', 'a.docdate', 'a.docduedate', 'c.sellname', 'b.cardname', 'a.discountamount', 'a.grandtotal', 'a.status', null];
    protected array $column_search = ['a.docnum', 'a.docdate', 'a.docduedate', 'c.sellname', 'b.cardname', 'a.discountamount', 'a.grandtotal', 'a.status'];
    protected array $order         = ['a.docnum' => 'DESC', 'a.docdate' => 'DESC'];

    protected $fillable = [
        'docnum', 'docdate', 'docduedate', 'cardcode', 'whscode', 'remark', 'sellcode', 'status', 'subtotal', 'discountamount', 'dppamount', 'taxclude', 'taxcode', 'taxamount', 'grandtotal', 'createddate', 'createdby', 'updateddate', 'updatedby', 'approveddate', 'approvedby', 'closeddate', 'closedby', 'canceleddate', 'canceledby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select(['a.docnum', 'a.docdate', 'a.docduedate', 'c.sellname', 'b.cardname', 'a.discountamount', 'a.grandtotal', 'a.status'])
              ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
              ->leftJoin('selltype as c', 'c.sellcode', '=', 'a.sellcode');
        
        parent::_get_datatables_query($query);
    }

    public function getWithDetails(string $docnum): ?array
    {
        $header = DB::table('sqheader as a')
            ->select('a.*', 'b.cardname', 'c.whsname')
            ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
            ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode')
            ->leftJoin('selltype as d', 'd.sellcode', '=', 'a.sellcode')
            ->where('a.docnum', $docnum)
            ->first();

        if (!$header) {
            return null;
        }

        $details = DB::table('sqdetail as a')
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
        $builder = DB::table('sqheader as a')
                    ->select('a.docnum, a.docdate, c.sellname, b.cardname, a.grandtotal, a.remark')
                    ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
                    ->leftJoin('selltype as c', 'c.sellcode', '=', 'a.sellcode')
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