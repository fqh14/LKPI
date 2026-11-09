<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

abstract class BaseTransactionModel extends Model
{
    protected $primaryKey = 'docnum';
    protected $returnType = 'array';
    
    protected string $txnNumberField    = '';
    protected string $detailTable       = '';
    public string $detailForeignKey     = ''; 
    protected array $column_order       = [];
    protected array $column_search      = [];
    protected array $order              = [];
    protected string $numberPrefix      = 'TRX'; 
    protected int $numberPadLength      = 4;

    public $timestamps = false;

    protected function getModuleCodeFromUri(): string
    {
        $segment = Request::segment(1) ?? $this->table;
        return strtoupper($segment);
    }

    protected function logTransactionActivity(string $action, string $docNum): void
    {
        if (class_exists(\App\Models\MUseractivity::class)) {
            \App\Models\MUseractivity::log($action, $docNum, $this->getModuleCodeFromUri());
        }
    }

    protected function _get_datatables_query($query)
    {
        $search = Request::post('search');
        
        if (is_array($search) && !empty($search['value']) && !empty($this->column_search)) {
            $searchValue = $search['value'];
            $query->where(function ($q) use ($searchValue) {
                foreach ($this->column_search as $i => $item) {
                    if ($i === 0) {
                        $q->where($item, 'LIKE', "%{$searchValue}%");
                    } else {
                        $q->orWhere($item, 'LIKE', "%{$searchValue}%");
                    }
                }
            });
        }

        $postOrder = Request::post('order');
        if (is_array($postOrder) && isset($postOrder[0]['column'], $postOrder[0]['dir'])) {
            $columnIndex = (int)$postOrder[0]['column'];
            if (isset($this->column_order[$columnIndex])) {
                $query->orderBy($this->column_order[$columnIndex], $postOrder[0]['dir']);
            }
        } elseif (!empty($this->order)) {
            foreach ($this->order as $key => $value) {
                $query->orderBy($key, $value);
            }
        }
    }

    public function get_datatables(): array
    {
        $query = self::query();
        $this->_get_datatables_query($query);
        
        $postLength = Request::post('length');
        $postStart  = Request::post('start');
        
        if (isset($postLength) && (int)$postLength !== -1) {
            $query->skip((int)$postStart)->take((int)$postLength);
        }
        return $query->get()->toArray();
    }

    public function count_filtered(): int
    {
        $query = self::query();
        $this->_get_datatables_query($query);
        return $query->count();
    }

    public function count_all(): int
    {
        return self::count();
    }

    public function get_details(string $txnNumber): array
    {
        return DB::table($this->detailTable)
                 ->where($this->detailForeignKey, $txnNumber)
                 ->get()
                 ->map(fn($item) => (array)$item)
                 ->toArray();
    }

    public function saveadd(array $headerData, array $detailsData, ?string $txnNumberField = null): array
    {
        $targetField = $txnNumberField ?? $this->txnNumberField;
        $txnNumber   = $headerData[$targetField] ?? '';

        try {
            return DB::transaction(function () use ($headerData, $detailsData, $txnNumber) {
                self::create($headerData);

                if (!empty($detailsData) && !empty($txnNumber)) {
                    foreach ($detailsData as &$row) {
                        $row[$this->detailForeignKey] = $txnNumber;
                    }
                    unset($row);
                    
                    DB::table($this->detailTable)->insert($detailsData);
                }
                
                $this->logTransactionActivity('ADD', $txnNumber);

                return ['status' => 'success'];
            });
        } catch (\Exception $e) {
            Log::error('Transaction SaveAdd Error: ' . $e->getMessage());
            return [
                'status'  => 'error', 
                'message' => 'DB Error: ' . $e->getMessage()
            ];
        }
    }

    public function saveedit($id, array $headerData, array $detailsData): array
    {
        try {
            return DB::transaction(function () use ($id, $headerData, $detailsData) {
                $currentData = self::find($id);
                if (!$currentData) {
                    return ['status' => 'error', 'message' => 'Data transaksi tidak ditemukan.'];
                }            
                
                $txnNumber = $currentData[$this->txnNumberField] ?? '';
                $currentData->update($headerData);
                
                if (!empty($txnNumber)) {
                    DB::table($this->detailTable)->where($this->detailForeignKey, $txnNumber)->delete();

                    if (!empty($detailsData)) {
                        foreach ($detailsData as &$row) {
                            $row[$this->detailForeignKey] = $txnNumber;
                        }
                        unset($row);
                        
                        DB::table($this->detailTable)->insert($detailsData);
                    }
                }

                $this->logTransactionActivity('EDIT', $txnNumber);

                return ['status' => 'success'];
            });
        } catch (\Exception $e) {
            Log::error('Transaction SaveEdit Error: ' . $e->getMessage());
            return [
                'status'  => 'error',
                'message' => 'DB Error: ' . $e->getMessage()
            ];
        }
    }

    protected function updateStatus($id, array $data): bool
    {
        $record = self::find($id);
        if (!$record) {
            return false;
        }

        $result = $record->update($data);
        if ($result) {
            $txnNumber = $record->{$this->txnNumberField} ?? $id;
            
            $statusVal = $data['status'] ?? null;
            $actionName = 'STATUS_CHANGE';
            if ((int)$statusVal === 1) $actionName = 'ACTIVE';
            elseif ((int)$statusVal === 2) $actionName = 'CLOSE';
            elseif ((int)$statusVal === 0) $actionName = 'CANCEL';

            $this->logTransactionActivity($actionName, (string)$txnNumber);
        }
        return (bool)$result;
    }

    public function saveactive($id, array $data): bool { return $this->updateStatus($id, $data); }
    public function saveclose($id, array $data): bool  { return $this->updateStatus($id, $data); }
    public function savecancel($id, array $data): bool { return $this->updateStatus($id, $data); }

    public function cancelDocument($id, int $userCode): bool
    {
        $data = [
            'status'       => 0,
            'canceleddate' => date('Y-m-d H:i:s'),
            'canceledby'   => $userCode
        ];

        return $this->updateStatus($id, $data);
    }

    public function generateNumber(string $docDate = null): string
    {
        $date  = $docDate ? new \DateTime($docDate) : new \DateTime();
        $year  = $date->format('y'); 
        $month = $date->format('m'); 
        
        $prefix    = $this->numberPrefix . '-' . $year . $month; 
        $padLength = $this->numberPadLength; 

        $lastData = self::query()
            ->select('docnum')
            ->where('docnum', 'LIKE', $prefix . '%')
            ->orderBy('docnum', 'DESC')
            ->first();

        if ($lastData) {
            $lastNumber = (int) substr($lastData->docnum, -$padLength);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);
    }
}