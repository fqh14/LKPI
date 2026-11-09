<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MPacking extends BaseTransactionModel
{
    protected $table              = 'packingheader';
    protected $primaryKey         = 'docnum';
    public $incrementing          = false;
    protected $keyType            = 'string';

    protected string $numberPrefix      = 'PK';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'packingdetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [
        null, 'a.docnum', 'a.docdate', 'b.cardname', 'a.address', 'a.remark', 'a.status', null
    ];
    protected array $column_search = [
        'a.docnum', 'a.docdate', 'b.cardname', 'a.address', 'a.remark', 'a.status'
    ];
    protected array $order = [
        'a.docdate' => 'DESC', 'a.docnum' => 'DESC'
    ];

    protected $fillable = [
        'docnum', 'docdate', 'cardcode', 'address', 'fromwhscode', 'towhscode', 'status', 'remark', 'createddate', 'createdby', 'updateddate', 'updatedby', 'approveddate', 'approvedby', 'canceleddate', 'canceledby', 'closeddate', 'closedby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select('a.docnum, a.docdate, b.cardname, a.address, a.remark, a.status')
              ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode');
        
        parent::_get_datatables_query($query);
    }

    public function get_details(string $txnNumber): array
    {
        return DB::table($this->detailTable)
            ->select('packingdetail.*', 'itemmaster.itemname')
            ->leftJoin('itemmaster', 'itemmaster.itemcode', '=', 'packingdetail.itemcode')
            ->where($this->detailForeignKey, $txnNumber)
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }

    public function getAlreadyPackedQty(string $doNum, int $baseLine, ?string $excludeDocNum = null): float
    {
        $builder = DB::table($this->detailTable . ' as d')
            ->join('packingheader as h', 'h.docnum', '=', 'd.docnum')
            ->where('h.status', 1)
            ->where('d.baseentry', $doNum)
            ->where('d.baseline', $baseLine);

        if (!empty($excludeDocNum)) {
            $builder->where('d.docnum', '!=', $excludeDocNum);
        }

        $row = $builder->selectSum('d.qty')->first();
        return (float)($row->qty ?? 0);
    }
}