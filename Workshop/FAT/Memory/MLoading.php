<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MLoading extends BaseTransactionModel
{
    protected $table              = 'loadingheader';
    protected $primaryKey         = 'docnum';
    public $incrementing          = false;
    protected $keyType            = 'string';

    protected string $numberPrefix      = 'LD';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'loadingdetail';
    public string $detailForeignKey     = 'docnum';

    protected array $column_order  = [
        null, 'a.docnum', 'a.docdate', 'b.cardname', 'a.expedition_type', 'a.remark', 'a.status', null
    ];
    protected array $column_search = [
        'a.docnum', 'a.docdate', 'b.cardname', 'a.expedition_type', 'a.remark', 'a.status'
    ];
    protected array $order = [
        'a.docdate' => 'DESC', 'a.docnum' => 'DESC'
    ];

    protected $fillable = [
        'docnum', 'docdate', 'expedition_type', 'cardcode', 'awbnum', 'platnum', 'drivername', 'remark', 'status', 'createddate', 'createdby', 'updateddate', 'updatedby', 'approveddate', 'approvedby', 'closeddate', 'closedby', 'canceleddate', 'canceledby', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select('a.docnum, a.docdate, b.cardname, a.expedition_type, a.remark, a.status')
              ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode');
        
        parent::_get_datatables_query($query);
    }

    public function get_details(string $txnNumber): array
    {
        return DB::table($this->detailTable)
            ->select('loadingdetail.*', 'itemmaster.itemname')
            ->leftJoin('itemmaster', 'itemmaster.itemcode', '=', 'loadingdetail.itemcode')
            ->where($this->detailForeignKey, $txnNumber)
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }

    public function getAlreadyLoadedQty(string $packNum, int $baseLine, ?string $excludeDocNum = null): float
    {
        $builder = DB::table($this->detailTable . ' as d')
            ->join('loadingheader as h', 'h.docnum', '=', 'd.docnum')
            ->where('h.status', 1)
            ->where('d.baseentry', $packNum)
            ->where('d.baseline', $baseLine);

        if (!empty($excludeDocNum)) {
            $builder->where('d.docnum', '!=', $excludeDocNum);
        }

        $row = $builder->selectSum('d.qty')->first();
        return (float)($row->qty ?? 0);
    }

    public function getOpenPackingList(): array
    {
        $subquery = DB::table('packingdetail as pd')
            ->select('1')
            ->whereColumn('pd.docnum', '=', 'a.docnum')
            ->whereRaw('pd.qty > (
                SELECT COALESCE(SUM(ld.qty), 0) 
                FROM loadingdetail as ld 
                JOIN loadingheader as lh ON lh.docnum = ld.docnum 
                WHERE lh.status = 1 AND ld.baseentry = pd.docnum AND ld.baseline = pd.linenum
            )');

        return DB::table('packingheader as a')
            ->select('a.docnum', 'a.docdate', 'b.cardname', 'a.address', 'a.remark')
            ->leftJoin('cardmaster as b', 'b.cardcode', '=', 'a.cardcode')
            ->where('a.status', 1)
            ->whereExists($subquery)
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }
}