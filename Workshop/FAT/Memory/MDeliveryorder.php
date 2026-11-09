<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MDeliveryorder extends BaseTransactionModel
{
    protected $table            = 'doheader';
    protected $primaryKey       = 'docnum';
    public $incrementing        = false;
    protected $keyType          = 'string';

    protected string $numberPrefix      = 'DO';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'dodetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [null, 'a.docnum', 'a.docdate', 'a.baseentry', 'c.sellname', 'b.cardname', 'a.subtotal', 'a.discountamount', 'a.taxamount', 'a.grandtotal', 'a.status', null];
    protected array $column_search = ['a.docnum', 'a.docdate', 'a.baseentry', 'c.sellname', 'b.cardname', 'a.subtotal', 'a.discountamount', 'a.taxamount', 'a.grandtotal', 'a.status'];
    protected array $order         = ['a.docnum' => 'DESC', 'a.docdate' => 'DESC'];

    protected $fillable = [
        'docnum', 'docdate', 'cardcode', 'sellcode', 'whscode', 'currcode', 'termcode', 'remark', 'subtotal', 'discountamount', 'dppamount', 'taxclude', 'taxcode', 'taxamount', 'grandtotal', 'status', 'createddate', 'createdby', 'updateddate', 'updatedby', 'approveddate', 'approvedby', 'closeddate', 'closedby', 'canceleddate', 'canceledby', 'baseentry', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select('a.docnum, a.docdate, a.baseentry, c.sellname, b.cardname, a.subtotal, a.discountamount, a.taxamount, a.grandtotal, a.status')
              ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
              ->leftJoin('selltype as c', 'c.sellcode', '=', 'a.sellcode');
        
        parent::_get_datatables_query($query);
    }

    public function getDetailByLine(string $docnum, int $linenum): ?array
    {
        $result = DB::table('dodetail')
            ->where('docnum', $docnum)
            ->where('linenum', $linenum)
            ->first();

        return $result ? (array)$result : null;
    }

    public function getWithDetails(string $docnum): ?array
    {
        $header = DB::table('doheader as a')
            ->select('a.*', 'b.cardname', 'b.address', 'c.whsname', 'd.currname', 'e.termname', 'f.sellname')
            ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
            ->leftJoin('whsmaster as c', 'c.whscode', '=', 'a.whscode')
            ->leftJoin('currency as d', 'd.currcode', '=', 'a.currcode')
            ->leftJoin('termin as e', 'e.termcode', '=', 'a.termcode')
            ->leftJoin('selltype as f', 'f.sellcode', '=', 'a.sellcode')
            ->where('a.docnum', $docnum)
            ->first();

        if (!$header) {
            return null;
        }

        $details = DB::table('dodetail as a')
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
        $builder = DB::table('doheader as a')
                    ->select('a.docnum', 'a.docdate', 'c.sellname', 'b.cardname', 'a.grandtotal', 'a.remark')
                    ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
                    ->leftJoin('selltype as c', 'c.sellcode', '=', 'a.sellcode')
                    ->where('a.status', 1);

        if (!empty($cardcode)) {
            $builder->where('a.cardcode', $cardcode);
        }

        $doList = $builder->orderBy('a.docnum', 'DESC')->get()->map(fn($item) => (array)$item)->toArray();
        $openDoList = [];

        foreach ($doList as $do) {
            $docnum = $do['docnum'];
            $doDetails = DB::table('dodetail')->where('docnum', $docnum)->get()->map(fn($item) => (array)$item)->toArray();
            
            $hasRemaining = false;
            foreach ($doDetails as $row) {
                $baseLine = $row['linenum'] ?? $row['baseline'] ?? 1;
                $reqQty   = (float)($row['qty'] ?? 0);
                
                $packedRow = DB::table('packingdetail as d')
                    ->selectSum('d.qty', 'total_packed')
                    ->join('packingheader as h', 'h.docnum', '=', 'd.docnum')
                    ->where('h.status', 1)
                    ->where('d.baseentry', $docnum)
                    ->where('d.baseline', $baseLine)
                    ->first();
                
                $packedQty = (float)($packedRow->total_packed ?? 0);
                
                if (($reqQty - $packedQty) > 0.001) {
                    $hasRemaining = true;
                    break;
                }
            }

            if ($hasRemaining) {
                $openDoList[] = $do;
            }
        }

        return $openDoList;
    }

    public function getOpenForInvoice(?string $cardcode = null): array
    {
        $builder = DB::table('doheader as a')
                    ->select('a.docnum', 'a.docdate', 'c.sellname', 'b.cardname', 'a.grandtotal', 'a.remark')
                    ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
                    ->leftJoin('selltype as c', 'c.sellcode', '=', 'a.sellcode')
                    ->where('a.status', 1); 

        if (!empty($cardcode)) {
            $builder->where('a.cardcode', $cardcode);
        }

        return $builder->orderBy('a.docnum', 'DESC')->get()->map(fn($item) => (array)$item)->toArray();
    }

    public function getOpenAll(): array
    {
        return DB::table('doheader as a')
                    ->select('a.docnum', 'a.docdate', 'c.sellname', 'b.cardname', 'a.grandtotal', 'a.remark')
                    ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
                    ->leftJoin('selltype as c', 'c.sellcode', '=', 'a.sellcode')
                    ->where('a.status', 1)
                    ->orderBy('a.docnum', 'DESC')
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();
    }
}