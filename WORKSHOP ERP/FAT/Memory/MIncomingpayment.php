<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MIncomingpayment extends BaseTransactionModel
{
    protected $table            = 'incomingpaymentheader';
    protected $primaryKey       = 'docnum';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected string $numberPrefix      = 'IP';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'incomingpaymentdetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [null, 'a.docnum', 'a.docdate', 'b.cardname', 'c.paymentname', 'a.totalamount', 'a.status', null];
    protected array $column_search = ['a.docnum', 'a.docdate', 'b.cardname', 'c.paymentname', 'a.totalamount', 'a.status'];
    protected array $order         = ['a.docnum' => 'DESC'];

    protected $fillable = [
        'docnum', 'docdate', 'cardcode', 'currcode', 'cashbankaccount', 'paymentcode', 'totalamount', 'remark', 'status', 'createddate', 'createdby', 'approveddate', 'approvedby', 'canceleddate', 'canceledby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select('a.docnum, a.docdate, b.cardname, c.paymentname, a.totalamount, a.status')
              ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
              ->leftJoin('paymenttype as c', 'c.paymentcode', '=', 'a.paymentcode');
        
        parent::_get_datatables_query($query);
    }

    public function get_details(string $txnNumber): array
    {
        return DB::table('incomingpaymentdetail as a')
            ->select('a.*', 'a.amountapplied as paymentamount', 'b.grandtotal as grandtotal', 'b.paidamount as paidamount_old')
            ->leftJoin('arinvoiceheader as b', 'b.docnum', '=', 'a.baseentry')
            ->where('a.docnum', $txnNumber)
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }
}