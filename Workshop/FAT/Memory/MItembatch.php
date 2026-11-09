<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MItembatch extends Model
{
    protected $table            = 'itembatch';
    protected $primaryKey       = 'id';
    public $incrementing        = true;
    public $timestamps          = false;

    protected $fillable    = [
        'itemcode', 'whscode', 'areacode', 'batchnum', 'expdate', 'unitcost', 'onhand'
    ];

    public function getStockByFEFO(string $itemCode, string $whsCode, ?string $areaCode = null): array
    {
        $query = self::where('itemcode', $itemCode)
                     ->where('whscode', $whsCode)
                     ->where('onhand', '>', 0);

        if (!empty($areaCode)) {
            $query->where('areacode', $areaCode);
        }

        return $query->orderByRaw('expdate IS NULL ASC')
                     ->orderBy('expdate', 'ASC')
                     ->get()
                     ->toArray();
    }

    public function addBatchStock(string $itemCode, string $whsCode, ?string $areaCode, string $batchNum, ?string $expDate, float $unitCost, float $qty): void
    {
        $query = self::where('itemcode', $itemCode)
                     ->where('whscode', $whsCode)
                     ->where('batchnum', $batchNum);

        if (!empty($areaCode)) {
            $query->where('areacode', $areaCode);
        } else {
            $query->where(function($q) {
                $q->where('areacode', '')->orWhereNull('areacode');
            });
        }

        $existing = $query->first();

        if ($existing) {
            $existing->update([
                'onhand'   => (float)$existing->onhand + $qty,
                'unitcost' => $unitCost > 0 ? $unitCost : $existing->unitcost,
                'expdate'  => !empty($expDate) ? $expDate : $existing->expdate
            ]);
        } else {
            self::create([
                'itemcode' => $itemCode,
                'whscode'  => $whsCode,
                'areacode' => $areaCode,
                'batchnum' => $batchNum,
                'expdate'  => $expDate,
                'unitcost' => $unitCost,
                'onhand'   => $qty
            ]);
        }
    }

    public function reduceBatchStock(string $itemCode, string $whsCode, ?string $areaCode, string $batchNum, float $qty): void
    {
        $query = self::where('itemcode', $itemCode)
                     ->where('whscode', $whsCode)
                     ->where('batchnum', $batchNum);

        if (!empty($areaCode)) {
            $query->where('areacode', $areaCode);
        } else {
            $query->where(function($q) {
                $q->where('areacode', '')->orWhereNull('areacode');
            });
        }

        $existing = $query->first();

        if ($existing) {
            $newOnhand = max(0, (float)$existing->onhand - $qty);
            $existing->update(['onhand' => $newOnhand]);
        }
    }

    public function getBatchInfo(string $itemCode, string $whsCode, string $batchNum): ?array
    {
        $result = self::where('itemcode', $itemCode)
                      ->where('whscode', $whsCode)
                      ->where('batchnum', $batchNum)
                      ->first();

        return $result ? $result->toArray() : null;
    }

    public function getOpenBatchList(string $whscode): array
    {
        return DB::table('itembatch as a')
            ->select('a.id', 'a.batchnum', 'a.itemcode', 'b.itemname', 'a.expdate', 'a.unitcost', 'a.onhand') 
            ->leftJoin('itemmaster as b', 'b.itemcode', '=', 'a.itemcode')
            ->where('a.whscode', $whscode)
            ->where('a.onhand', '>', 0)
            ->orderBy('b.itemname', 'ASC')
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }

    public function getBatchesByIds(array $ids): array
    {
        if (empty($ids)) return [];

        return DB::table('itembatch as a')
            ->select('a.*', 'b.itemname', 'b.uomcode')                        
            ->leftJoin('itemmaster as b', 'b.itemcode', '=', 'a.itemcode')
            ->whereIn('a.id', $ids) 
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }
}