<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class MItemwarehouse extends Model
{
    protected $table            = 'itemwarehouse';
    protected $primaryKey       = 'id';
    public $incrementing        = true;
    public $timestamps          = false;

    protected $fillable         = ['itemcode', 'whscode', 'onhand', 'committed', 'ordered'];

    public function addStock(string $itemCode, string $whsCode, float $qty): bool
    {
        $existing = self::where('itemcode', $itemCode)
                        ->where('whscode', $whsCode)
                        ->first();

        if ($existing) {
            return $existing->update([
                'onhand' => (float)$existing->onhand + $qty
            ]);
        } else {
            return (bool)self::create([
                'itemcode'  => $itemCode,
                'whscode'   => $whsCode,
                'onhand'    => $qty,
                'committed' => 0.0000,
                'ordered'   => 0.0000,
            ]);
        }
    }

    public function reduceStock(string $itemCode, string $whsCode, float $qty): bool
    {
        $existing = self::where('itemcode', $itemCode)
                        ->where('whscode', $whsCode)
                        ->first();

        if ($existing) {
            $newOnhand = (float)$existing->onhand - $qty;
            return $existing->update([
                'onhand' => $newOnhand < 0 ? 0.0000 : $newOnhand
            ]);
        }

        return false;
    }

    public function addOrdered(string $itemCode, string $whsCode, float $qty)
    {
        $exist = self::where('itemcode', $itemCode)->where('whscode', $whsCode)->first();

        if ($exist) {
            $currentOrdered = (float)($exist->ordered ?? 0);
            return self::where('itemcode', $itemCode)
                        ->where('whscode', $whsCode)
                        ->update(['ordered' => $currentOrdered + $qty]);
        } else {
            return (bool)self::create([
                'itemcode'  => $itemCode,
                'whscode'   => $whsCode,
                'onhand'    => 0,
                'committed' => 0,
                'ordered'   => $qty,
            ]);
        }
    }

    public function reduceOrdered(string $itemCode, string $whsCode, float $qty)
    {
        $exist = self::where('itemcode', $itemCode)->where('whscode', $whsCode)->first();

        if ($exist) {
            $currentOrdered = (float)($exist->ordered ?? 0);
            $newOrdered     = $currentOrdered - $qty;
            
            return self::where('itemcode', $itemCode)
                        ->where('whscode', $whsCode)
                        ->update(['ordered' => $newOrdered < 0 ? 0 : $newOrdered]);
        }
        return false;
    }

    public function addCommitted(string $itemCode, string $whsCode, float $qty)
    {
        $row = self::where('itemcode', $itemCode)->where('whscode', $whsCode)->first();

        if ($row) {
            $currentCommitted = (float)($row->committed ?? 0);
            return $row->update([
                'committed' => $currentCommitted + $qty
            ]);
        } else {
            return (bool)self::create([
                'itemcode'  => $itemCode,
                'whscode'   => $whsCode,
                'onhand'    => 0,
                'committed' => $qty,
                'ordered'   => 0,
            ]);
        }
    }

    public function reduceCommitted(string $itemCode, string $whsCode, float $qty)
    {
        $row = self::where('itemcode', $itemCode)->where('whscode', $whsCode)->first();

        if ($row) {
            $currentCommitted = (float)($row->committed ?? 0);
            $newCommitted     = $currentCommitted - $qty;
            
            return $row->update([
                'committed' => $newCommitted < 0 ? 0 : $newCommitted
            ]);
        }
        
        return false;
    }

    public function getStockOnhand(string $itemCode, string $whsCode): float
    {
        $result = self::selectSum('onhand')
                        ->where('itemcode', $itemCode)
                        ->where('whscode', $whsCode)
                        ->first();

        return (float)($result->onhand ?? 0.0000);
    }

    public function getStock(): array
    {
        return DB::table('grpoheader as a')
                    ->select('a.docnum', 'a.docdate', 'b.whsname', 'a.whscode', 'a.remark')
                    ->leftJoin('whsmaster as b', 'b.whscode', '=', 'a.whscode')
                    ->orderBy('a.docnum', 'DESC')
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();
    }

    protected function _get_datatables_report($query): void
    {
        $query->from($this->table . ' as a')
              ->select([
                  'a.whscode',
                  'b.whsname',
                  'a.itemcode',
                  'c.itemname',
                  'a.onhand',
                  'c.uomcode'
              ])
              ->leftJoin('whsmaster as b', 'b.whscode', '=', 'a.whscode')
              ->leftJoin('itemmaster as c', 'c.itemcode', '=', 'a.itemcode')
              ->where('a.onhand', '>', 0);

        $whscode = Request::input('whscode');
        if (!empty($whscode)) {
            $query->where('a.whscode', $whscode);
        }

        $search = Request::input('search');
        if (!empty($search['value'])) {
            $searchValue = $search['value'];
            $query->where(function($q) use ($searchValue) {
                $q->where('a.whscode', 'LIKE', "%{$searchValue}%")
                  ->orWhere('b.whsname', 'LIKE', "%{$searchValue}%")
                  ->orWhere('a.itemcode', 'LIKE', "%{$searchValue}%")
                  ->orWhere('c.itemname', 'LIKE', "%{$searchValue}%")
                  ->orWhere('c.uomcode', 'LIKE', "%{$searchValue}%");
            });
        }

        $order = Request::input('order');
        if (!empty($order)) {
            $colIdx = $order[0]['column'];
            $dir = $order[0]['dir'];
            
            $columnMapping = [
                1 => 'a.whscode',
                2 => 'b.whsname',
                3 => 'a.itemcode',
                4 => 'c.itemname',
                5 => 'a.onhand',
                6 => 'c.uomcode',
            ];

            if (isset($columnMapping[$colIdx])) {
                $query->orderBy($columnMapping[$colIdx], $dir);
            }
        } else {
            $query->orderBy('a.itemcode', 'ASC');
        }
    }

    public function count_all(): int
    {
        return DB::table($this->table)->where('onhand', '>', 0)->count();
    }

    public function count_filtered(): int
    {
        $query = DB::table($this->table);
        $this->_get_datatables_report($query);
        return $query->count();
    }

    public function get_datatables_report(): array
    {
        $query = DB::table($this->table);
        $this->_get_datatables_report($query);
        
        $postLength = Request::post('length');
        $postStart  = Request::post('start');
        
        if (isset($postLength) && (int)$postLength !== -1) {
            $query->skip((int)$postStart)->take((int)$postLength);
        }
        return $query->get()->map(fn($item) => (array)$item)->toArray();
    }

    public function getReportData($request): array
    {
        $query = DB::table($this->table);
        $this->_get_datatables_report($query); 
        
        return $query->get()->map(fn($item) => (array)$item)->toArray();
    }
}