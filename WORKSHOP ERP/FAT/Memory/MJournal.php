<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

class MJournal extends BaseTransactionModel
{
    protected $table            = 'journalheader';
    protected $primaryKey       = 'id';
    public $incrementing        = true; 
    protected $keyType          = 'int';
    protected $returnType       = 'array';

    protected string $numberPrefix      = 'JE';
    protected string $txnNumberField    = 'docnum';
    protected string $detailTable       = 'journaldetail';
    public string $detailForeignKey     = 'journalid';

    protected array $column_order  = [null, 'a.docnum', 'a.docdate', 'a.refnum', 'a.source', 'a.status', null];
    protected array $column_search = ['a.docnum', 'a.refnum', 'a.source', 'a.description'];
    protected array $order         = ['a.id' => 'DESC'];

    protected $fillable = [
        'docnum', 'docdate', 'refnum', 'source', 'description', 'status', 'createddate', 'updateddate', 'canceleddate', 'approvedremark', 'canceledremark', 'closedremark'
    ];

    protected function _get_datatables_query($query): void
    {
        $query->from($this->table . ' as a')
              ->select('a.id', 'a.docnum', 'a.docdate', 'a.refnum', 'a.source', 'a.description', 'a.status');
        
        parent::_get_datatables_query($query);
    }

    private function getReportQuery()
    {
        $query = DB::table('journaldetail as b')
            ->select('a.docnum', 'a.docdate', 'a.refnum', 'a.source', 'b.acctcode', 'c.acctname', 'b.debit', 'b.credit', 'b.remark')
            ->join('journalheader as a', 'a.id', '=', 'b.journalid')
            ->leftJoin('account as c', 'c.acctcode', '=', 'b.acctcode')
            ->where('a.status', 1);
        
        $startDate = Request::input('start_date');
        $endDate   = Request::input('end_date');
        $source    = Request::input('source');

        if (!empty($startDate) && !empty($endDate)) {
            $query->where('a.docdate', '>=', $startDate . ' 00:00:00')
                  ->where('a.docdate', '<=', $endDate . ' 23:59:59');
        }

        if (!empty($source)) {
            $query->where('a.source', $source);
        }

        $searchValue = Request::input('search.value');
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $q->where('a.docnum', 'LIKE', "%{$searchValue}%")
                  ->orWhere('a.refnum', 'LIKE', "%{$searchValue}%")
                  ->orWhere('a.source', 'LIKE', "%{$searchValue}%")
                  ->orWhere('b.acctcode', 'LIKE', "%{$searchValue}%")
                  ->orWhere('c.acctname', 'LIKE', "%{$searchValue}%")
                  ->orWhere('b.remark', 'LIKE', "%{$searchValue}%");
            });
        }

        $order = Request::input('order');
        if (!empty($order)) {
            $colIdx = $order[0]['column'];
            $dir = $order[0]['dir'];
            
            $columnMapping = [
                1 => 'a.docnum',
                2 => 'a.docdate',
                3 => 'a.refnum',
                4 => 'a.source',
                5 => 'b.acctcode',
                6 => 'c.acctname',
                7 => 'b.debit',
                8 => 'b.credit',
                9 => 'b.remark'
            ];

            if (isset($columnMapping[$colIdx])) {
                $query->orderBy($columnMapping[$colIdx], $dir);
            }
        } else {
            $query->orderBy('a.id', 'DESC');
        }

        return $query;
    }

    public function count_all(): int
    {
        return DB::table('journaldetail as b')
            ->join('journalheader as a', 'a.id', '=', 'b.journalid')
            ->where('a.status', 1)
            ->count();
    }

    public function count_filtered(): int
    {
        return $this->getReportQuery()->count();
    }

    public function getReportData($request): array
    {
        return $this->getReportQuery()->get()->map(fn($item) => (array)$item)->toArray();
    }

    public function get_datatables_report(): array
    {
        $query = $this->getReportQuery();
        
        $postLength = Request::post('length');
        $postStart  = Request::post('start');
        
        if (isset($postLength) && (int)$postLength !== -1) {
            $query->skip((int)$postStart)->take((int)$postLength);
        }
        return $query->get()->map(fn($item) => (array)$item)->toArray();
    }

    public function saveadd(array $headerData, array $detailsData, ?string $txnNumberField = null): array
    {
        try {
            return DB::transaction(function () use ($headerData, $detailsData) {
                $record = self::create($headerData);
                $journalId = $record->id;

                if (!empty($detailsData) && !empty($journalId)) {
                    foreach ($detailsData as &$row) {
                        $row[$this->detailForeignKey] = $journalId; 
                    }
                    DB::table($this->detailTable)->insert($detailsData);
                }
                
                return ['status' => 'success', 'journal_id' => $journalId];
            });
        } catch (\Exception $e) {
            Log::error('Journal SaveAdd Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Gagal menyimpan jurnal: ' . $e->getMessage()];
        }
    }

    public function saveedit($id, array $headerData, array $detailsData): array
    {
        try {
            return DB::transaction(function () use ($id, $headerData, $detailsData) {
                if (!empty($headerData)) {
                    $record = self::find($id);
                    if ($record) {
                        $record->update($headerData);
                    }
                } 
                
                $builderDetail = DB::table($this->detailTable); 
                $existingDetails = $builderDetail->where($this->detailForeignKey, $id)->get();
                $existingMap = [];
                foreach ($existingDetails as $row) {
                    $existingMap[$row->linenum] = (array)$row;
                }

                $incomingLineNums = []; 
                foreach ($detailsData as $detail) {
                    $linenum = $detail['linenum'];
                    $incomingLineNums[] = $linenum; 
                    $detail[$this->detailForeignKey] = $id;

                    if (isset($existingMap[$linenum])) { 
                        $idToUpdate = $existingMap[$linenum]['id'];
                        $builderDetail->where('id', $idToUpdate)->update($detail);
                    } else { 
                        $builderDetail->insert($detail);
                    }
                } 

                foreach ($existingMap as $linenum => $row) {
                    if (!in_array($linenum, $incomingLineNums)) {
                        $builderDetail->where('id', $row['id'])->delete();
                    }
                } 

                return [
                    'status' => true,
                    'message' => 'Journal detail successfully updated'
                ];
            });
        } catch (\Exception $e) {
            Log::error('Journal SaveEdit Error: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Gagal memperbarui jurnal: ' . $e->getMessage()];
        }
    }

    public function createJournal(string $source, string $txnNumber, array $headerData, array $detailData): array
    {
        $source = strtoupper($source);

        switch ($source) {
            case 'GRPO':
            case 'APINV':
            case 'DO':
            case 'ARINV':
            case 'OUTPAY':
            case 'INPAY':
            case 'IP':
            case 'RP':
            case 'LOADING':
            case 'STOCK_OPNAME':
                return $this->buildDocumentJournal($source, $txnNumber, $headerData, $detailData, false);

            default:
                throw new \Exception("Sumber jurnal '{$source}' tidak dikenali atau belum dikonfigurasi.");
        }
    }

    public function updateJournal(string $source, string $txnNumber, array $headerData, array $detailData): array
    {
        $existingJournal = self::where('refnum', $txnNumber)->first();

        if (!$existingJournal) {
            return $this->createJournal($source, $txnNumber, $headerData, $detailData);
        }

        $source = strtoupper($source);

        switch ($source) {
            case 'GRPO':
            case 'APINV':
            case 'DO':
            case 'ARINV':
            case 'OUTPAY':
            case 'INPAY':
            case 'IP':
            case 'RP':
            case 'LOADING':
            case 'STOCK_OPNAME':
                return $this->buildDocumentJournal($source, $txnNumber, $headerData, $detailData, true, $existingJournal->id);
            
            default:
                throw new \Exception("Sumber update jurnal '{$source}' tidak dikenali.");
        }
    }

    public function checkExists(string $refNum): bool
    {
        return self::where('refnum', $refNum)->exists();
    }

    private function buildDocumentJournal(string $source, string $txnNumber, array $headerData, array $detailData, bool $isUpdate = false, ?int $journalId = null): array
    {
        $source = strtoupper($source);
        $docDate = $headerData['docdate'] ?? date('Y-m-d H:i:s');

        $prefixMap = [
            'GRPO'          => ['prefix' => 'JE-', 'name' => 'Goods Receipt PO'],
            'APINV'         => ['prefix' => 'JE-', 'name' => 'AP Invoice'],
            'DO'            => ['prefix' => 'JE-', 'name' => 'Delivery Order'],
            'ARINV'         => ['prefix' => 'JE-', 'name' => 'AR Invoice'],
            'OUTPAY'        => ['prefix' => 'JE-', 'name' => 'Outgoing Payment'],
            'INPAY'         => ['prefix' => 'JE-', 'name' => 'Incoming Payment'],
            'IP'            => ['prefix' => 'JE-', 'name' => 'Issue for Production'],
            'RP'            => ['prefix' => 'JE-', 'name' => 'Receipt from Production'],
            'LOADING'       => ['prefix' => 'JE-', 'name' => 'Loading & Ekspedisi'],
            'STOCK_OPNAME'  => ['prefix' => 'JE-', 'name' => 'Stock Opname'],
        ];

        $sourceInfo   = $prefixMap[$source] ?? ['prefix' => 'JE-', 'name' => $source];
        $sourceName   = $sourceInfo['name'];
        $cardCode     = $headerData['cardcode'] ?? $headerData['customercode'] ?? '';
        $updateText   = $isUpdate ? ' (Updated)' : '';

        if ($isUpdate) {
            $existingRecord = self::find($journalId);
            $existingDocNum = $existingRecord ? $existingRecord->docnum : null;
            $journalDocNum  = $existingDocNum ?: $this->generateNumber($docDate);
        } else {
            $journalDocNum  = $this->generateNumber($docDate);
        }

        $journalHeader = [
            'docnum'      => $journalDocNum,
            'docdate'     => $docDate,
            'refnum'      => $txnNumber,
            'source'      => $source,
            'description' => "{$sourceName}{$updateText} - {$txnNumber} " . (!empty($cardCode) ? "({$cardCode})" : ''),
            'status'      => 1,
        ];

        if ($isUpdate) {
            $journalHeader['updateddate'] = date('Y-m-d H:i:s');
        } else {
            $journalHeader['createddate'] = date('Y-m-d H:i:s');
        }

        $journalDetails = [];
        $itemCodes = array_unique(array_column($detailData, 'itemcode'));
        $itemMap = [];
        if (!empty($itemCodes)) {
            $itemsInfo = DB::table('itemmaster')->whereIn('itemcode', $itemCodes)->get()->map(fn($item) => (array)$item)->toArray();
            foreach ($itemsInfo as $inf) {
                $itemMap[$inf['itemcode']] = $inf;
            }
        }

        $groupMap = [];
        $allGroups = DB::table('itemgroup')->get()->map(fn($item) => (array)$item)->toArray();
        foreach ($allGroups as $grp) {
            foreach (['itemgroupcode', 'code', 'itemgroup', 'groupcode'] as $col) {
                if (!empty($grp[$col])) {
                    $groupMap[$grp[$col]] = $grp;
                }
            }
        }

        switch ($source) {
            case 'GRPO':
                foreach ($detailData as $item) {
                    $subtotal = (float)($item['subtotal'] ?? 0);
                    $itemTax  = (float)($item['taxamount'] ?? $item['tax'] ?? 0);
                    $itemCode = $item['itemcode'] ?? '';
                    $batchNum = $item['batchnum'] ?? '-'; 

                    $itemInfo  = $itemMap[$itemCode] ?? [];
                    $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                    $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                    $inventoryAccount     = $groupInfo['inventory-acct'] ?? '';
                    $goodsClearingAccount = $groupInfo['goodsclearing-acct'] ?? '';
                    $vatInAcct            = $groupInfo['vatin-acct'] ?? '';
                    $allocationAcct       = $groupInfo['allocation-acct'] ?? '';

                    if (empty($inventoryAccount)) {
                        throw new \Exception("Akun persediaan (inventory-acct) untuk item {$itemCode} belum dikonfigurasi.");
                    }
                    if (empty($goodsClearingAccount)) {
                        throw new \Exception("Akun goods clearing (goodsclearing-acct) untuk item {$itemCode} belum dikonfigurasi.");
                    }

                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $inventoryAccount,
                        'debit'     => $subtotal,
                        'credit'    => 0,
                        'remark'    => 'Persediaan Item: ' . $itemCode . ' [Batch: ' . $batchNum . '] - GRPO ' . $txnNumber
                    ];

                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $goodsClearingAccount,
                        'debit'     => 0,
                        'credit'    => $subtotal,
                        'remark'    => 'Hutang GRPO ' . $txnNumber . ' - Item ' . $itemCode . ' [Batch: ' . $batchNum . ']'
                    ];

                    if ($itemTax > 0) {
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $vatInAcct, 
                            'debit'     => $itemTax,
                            'credit'    => 0,
                            'remark'    => 'Pajak Masukan (VAT In) Item ' . $itemCode . ' - GRPO ' . $txnNumber
                        ];
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $allocationAcct, 
                            'debit'     => 0,
                            'credit'    => $itemTax,
                            'remark'    => 'Hutang Usaha Pajak Item ' . $itemCode . ' - GRPO ' . $txnNumber
                        ];
                    }
                }
                break;

            case 'APINV':
                $baseEntry  = $headerData['baseentry'] ?? null;
                $sourceType = strtoupper($headerData['sourcetype'] ?? '');
                
                if (empty($sourceType) && !empty($baseEntry)) {
                    $isGrpo = DB::table('grpoheader')->where('docnum', $baseEntry)->exists();
                    $sourceType = $isGrpo ? 'GRPO' : 'PO';
                }

                foreach ($detailData as $item) {
                    $subtotal = (float)($item['subtotal'] ?? 0);
                    $itemCode = $item['itemcode'] ?? '';
                    
                    $itemInfo  = $itemMap[$itemCode] ?? [];
                    $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                    $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                    $inventoryAcct    = $groupInfo['inventory-acct'] ?? $groupInfo['inventory_acct'] ?? '';
                    $shippedGoodsAcct = $groupInfo['shippedgoods-acct'] ?? $groupInfo['shippedgoods_acct'] ?? '';
                    $expenseClearAcct = $groupInfo['expenseclear-acct'] ?? $groupInfo['expenseclear_acct'] ?? $groupInfo['goodsclearing-acct'] ?? '';
                    $allocationAcct   = $groupInfo['allocation-acct'] ?? $groupInfo['allocation_acct'] ?? '';
                    $expenseAcct      = $groupInfo['expense-acct'] ?? $groupInfo['expense_acct'] ?? '';

                    if ($sourceType === 'GRPO') {
                        $account = $expenseClearAcct ?: ($inventoryAcct ?: $expenseAcct);
                        $remark  = 'Clearing GRPO / Item: ' . $itemCode . ' (' . ($item['remark'] ?? '') . ')';
                    } else {
                        if (!empty($inventoryAcct)) {
                            $account = $inventoryAcct;
                            $remark  = 'Persediaan (Inventory) Item: ' . $itemCode . ' (' . ($item['remark'] ?? '') . ')';
                        } elseif (!empty($shippedGoodsAcct)) {
                            $account = $shippedGoodsAcct;
                            $remark  = 'Shipped Goods Item: ' . $itemCode . ' (' . ($item['remark'] ?? '') . ')';
                        } elseif (!empty($allocationAcct)) {
                            $account = $allocationAcct;
                            $remark  = 'Allocation Account Item: ' . $itemCode . ' (' . ($item['remark'] ?? '') . ')';
                        } else {
                            $account = $expenseAcct;
                            $remark  = 'Beban Lain / Expense Item: ' . $itemCode . ' (' . ($item['remark'] ?? '') . ')';
                        }
                    }

                    if (empty($account)) {
                        throw new \Exception("Akun akuntansi untuk item {$itemCode} belum dikonfigurasi.");
                    }

                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $account,
                        'debit'     => $subtotal,
                        'credit'    => 0,
                        'remark'    => $remark
                    ];
                }

                $headerTaxAmount = (float)($headerData['taxamount'] ?? 0);
                if ($headerTaxAmount > 0) {
                    $firstItemCode = $detailData[0]['itemcode'] ?? '';
                    $firstItemInfo = $itemMap[$firstItemCode] ?? [];
                    $firstGroupCode = $firstItemInfo['itemgroupcode'] ?? $firstItemInfo['groupcode'] ?? null;
                    $firstGroupInfo = ($firstGroupCode && isset($groupMap[$firstGroupCode])) ? $groupMap[$firstGroupCode] : [];
                    $vatInAcct      = $firstGroupInfo['vatin-acct'] ?? $firstGroupInfo['vatin_acct'] ?? '1500-00-211';

                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $vatInAcct, 
                        'debit'     => $headerTaxAmount,
                        'credit'    => 0,
                        'remark'    => 'Pajak Masukan (VAT In) - APINV ' . $txnNumber
                    ];
                }

                $freightTotal = (float)($headerData['freighttotal'] ?? 0);
                if ($freightTotal > 0) {
                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => '5100-00-051',
                        'debit'     => $freightTotal,
                        'credit'    => 0,
                        'remark'    => 'Biaya Angkut / Freight AP Invoice ' . $txnNumber
                    ];
                }

                $grandTotal = (float)($headerData['grandtotal'] ?? 0);
                $cardCode   = $headerData['cardcode'] ?? '';
                $journalDetails[] = [
                    'linenum'   => count($journalDetails) + 1,
                    'acctcode'  => '2100-00-010',
                    'debit'     => 0,
                    'credit'    => $grandTotal,
                    'remark'    => 'Hutang Dagang AP Invoice ' . $txnNumber . (!empty($cardCode) ? " ({$cardCode})" : '')
                ];
                break;

            case 'DO':
                $ledgerEntries = DB::table('stockledger')
                    ->where(['docnum' => $txnNumber, 'source' => 'Delivery Order'])
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();

                if (!empty($ledgerEntries)) {
                    foreach ($ledgerEntries as $ledger) {
                        $qty      = (float)($ledger['out_qty'] ?? 0);
                        if ($qty <= 0) continue;
                        
                        $itemCode = $ledger['itemcode'] ?? '';
                        $unitCost = (float)($ledger['unitcost'] ?? 0);
                        $cost     = $qty * $unitCost; 
                        $batchNum = $ledger['batchnum'] ?? '-';

                        $itemInfo  = $itemMap[$itemCode] ?? [];
                        $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                        $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                        $inventoryAccount = $groupInfo['inventory-acct'] ?? '';
                        $cogsAccount      = $groupInfo['cogs-acct'] ?? $groupInfo['expense-acct'] ?? '';

                        if (empty($cogsAccount) || empty($inventoryAccount)) {
                            throw new \Exception("Akun HPP/Beban (cogs-acct) atau Persediaan untuk item {$itemCode} belum dikonfigurasi.");
                        }

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $cogsAccount,
                            'debit'     => $cost,
                            'credit'    => 0,
                            'remark'    => "HPP/Biaya Transfer Item: {$itemCode} [Batch: {$batchNum}] - DO {$txnNumber}"
                        ];

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $inventoryAccount,
                            'debit'     => 0,
                            'credit'    => $cost,
                            'remark'    => "Pengurangan Persediaan Item: {$itemCode} [Batch: {$batchNum}] - DO {$txnNumber}"
                        ];
                    }
                } else {
                    foreach ($detailData as $item) {
                        $subtotal = (float)($item['subtotal'] ?? 0);
                        $itemCode = $item['itemcode'] ?? '';
                        $batchNum = $item['batchnum'] ?? '-';
                        
                        $itemInfo  = $itemMap[$itemCode] ?? [];
                        $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                        $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                        $inventoryAccount = $groupInfo['inventory-acct'] ?? '';
                        $cogsAccount      = $groupInfo['cogs-acct'] ?? $groupInfo['expense-acct'] ?? '';

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $cogsAccount,
                            'debit'     => $subtotal,
                            'credit'    => 0,
                            'remark'    => "HPP Item: {$itemCode} [Batch: {$batchNum}] - DO {$txnNumber}"
                        ];

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $inventoryAccount,
                            'debit'     => 0,
                            'credit'    => $subtotal,
                            'remark'    => "Pengurangan Persediaan Item: {$itemCode} [Batch: {$batchNum}] - DO {$txnNumber}"
                        ];
                    }
                }
                break;

            case 'ARINV':
                $grandTotal = (float)($headerData['grandtotal'] ?? 0);
                
                $journalDetails[] = [
                    'linenum'   => count($journalDetails) + 1,
                    'acctcode'  => '1300-00-010',
                    'debit'     => $grandTotal,
                    'credit'    => 0,
                    'remark'    => 'Piutang Usaha AR Invoice ' . $txnNumber . (!empty($cardCode) ? " ({$cardCode})" : '')
                ];

                foreach ($detailData as $item) {
                    $subtotal = (float)($item['subtotal'] ?? 0);
                    $itemCode = $item['itemcode'] ?? '';
                    
                    $itemInfo  = $itemMap[$itemCode] ?? [];
                    $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                    $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                    $revenueAccount = $groupInfo['revenue-acct'] ?? '';

                    if (empty($revenueAccount)) {
                        throw new \Exception("Akun Pendapatan (revenue-acct) untuk item {$itemCode} belum dikonfigurasi.");
                    }

                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $revenueAccount,
                        'debit'     => 0,
                        'credit'    => $subtotal,
                        'remark'    => 'Pendapatan Item: ' . $itemCode . ' - ARINV ' . $txnNumber
                    ];
                }

                $headerTaxAmount = (float)($headerData['taxamount'] ?? 0);
                if ($headerTaxAmount > 0) {
                    $firstItemCode = $detailData[0]['itemcode'] ?? '';
                    $firstItemInfo = $itemMap[$firstItemCode] ?? [];
                    $firstGroupCode = $firstItemInfo['itemgroupcode'] ?? $firstItemInfo['groupcode'] ?? null;
                    $firstGroupInfo = ($firstGroupCode && isset($groupMap[$firstGroupCode])) ? $groupMap[$firstGroupCode] : [];
                    $vatOutAcct     = $firstGroupInfo['vatout-acct'] ?? $firstGroupInfo['vatout_acct'] ?? '2100-00-221';

                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $vatOutAcct,
                        'debit'     => 0,
                        'credit'    => $headerTaxAmount,
                        'remark'    => 'Pajak Keluaran (VAT Out) - ARINV ' . $txnNumber
                    ];
                }

                $freightTotal = (float)($headerData['freighttotal'] ?? 0);
                if ($freightTotal > 0) {
                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => '4900-00-010', 
                        'debit'     => 0,
                        'credit'    => $freightTotal,
                        'remark'    => 'Pendapatan Biaya Angkut / Freight AR Invoice ' . $txnNumber
                    ];
                }
                break;

            case 'OUTPAY':
                $cashBankAccount = $headerData['cashbankaccount'] ?? '';
                if (empty($cashBankAccount)) {
                    throw new \Exception("Akun Kas / Bank untuk Outgoing Payment {$txnNumber} belum dipilih.");
                }

                $apAccount       = $headerData['apaccount'] ?? $headerData['acctcode'] ?? '2100-00-010';
                $discountAccount = $headerData['purchase_discount_acct'] ?? '5100-00-060';

                $totalPayment  = 0;
                $totalDiscount = 0;

                foreach ($detailData as $item) {
                    $amountApplied   = (float)($item['amountapplied'] ?? 0);
                    $discountApplied = (float)($item['discountapplied'] ?? 0);
                    $baseEntry       = $item['baseentry'] ?? '';
                    $totalLine       = $amountApplied + $discountApplied; 

                    if ($totalLine > 0) {
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $apAccount, 
                            'debit'     => $totalLine,
                            'credit'    => 0,
                            'remark'    => 'Pelunasan AP Invoice ' . $baseEntry . (!empty($cardCode) ? " ({$cardCode})" : '')
                        ];
                    }

                    $totalPayment  += $amountApplied; 
                    $totalDiscount += $discountApplied;
                }

                if ($totalPayment > 0) {
                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $cashBankAccount,  
                        'debit'     => 0,
                        'credit'    => $totalPayment,
                        'remark'    => 'Pembayaran Outgoing Payment ' . $txnNumber . (!empty($cardCode) ? " ({$cardCode})" : '')
                    ];
                }

                if ($totalDiscount > 0) {
                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $discountAccount, 
                        'debit'     => 0,
                        'credit'    => $totalDiscount,
                        'remark'    => 'Diskon Pembayaran Outgoing Payment ' . $txnNumber . (!empty($cardCode) ? " ({$cardCode})" : '')
                    ];
                }
                break;

            case 'INPAY':
                $cashBankAccount = $headerData['cashbankaccount'] ?? '';
                if (empty($cashBankAccount)) {
                    throw new \Exception("Akun Kas / Bank untuk Incoming Payment {$txnNumber} belum dipilih.");
                }

                $arAccount       = $headerData['araccount'] ?? $headerData['acctcode'] ?? '1300-00-010';
                $discountAccount = $headerData['sales_discount_acct'] ?? '6100-00-012';

                $totalPayment  = 0;
                $totalDiscount = 0;

                foreach ($detailData as $item) {
                    $amountApplied   = (float)($item['amountapplied'] ?? 0);
                    $discountApplied = (float)($item['discountapplied'] ?? 0);
                    $baseEntry       = $item['baseentry'] ?? '';
                    $totalLine       = $amountApplied + $discountApplied; 

                    $totalPayment  += $amountApplied;  
                    $totalDiscount += $discountApplied;

                    if ($totalLine > 0) {
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $arAccount, 
                            'debit'     => 0,
                            'credit'    => $totalLine,
                            'remark'    => 'Pelunasan AR Invoice ' . $baseEntry . (!empty($cardCode) ? " ({$cardCode})" : '')
                        ];
                    }
                }

                if ($totalPayment > 0) {
                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $cashBankAccount,
                        'debit'     => $totalPayment,
                        'credit'    => 0,
                        'remark'    => 'Penerimaan Incoming Payment ' . $txnNumber . (!empty($cardCode) ? " ({$cardCode})" : '')
                    ];
                }

                if ($totalDiscount > 0) {
                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $discountAccount, 
                        'debit'     => $totalDiscount,
                        'credit'    => 0,
                        'remark'    => 'Diskon Potongan Incoming Payment ' . $txnNumber . (!empty($cardCode) ? " ({$cardCode})" : '')
                    ];
                }
                break;

            case 'IP':
                $ledgerEntries = DB::table('stockledger')
                    ->where(['docnum' => $txnNumber, 'source' => 'Issue Production'])
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();

                if (empty($ledgerEntries)) {
                    foreach ($detailData as $item) {
                        $qty      = (float)($item['qty'] ?? 0);
                        $itemCode = $item['itemcode'] ?? '';
                        $itemInfo = $itemMap[$itemCode] ?? [];
                        $unitCost = (float)($item['unitcost'] ?? $itemInfo['price'] ?? 0);
                        $subtotal = $qty * $unitCost;
                        $batchNum = $item['batchnum'] ?? '-';

                        $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                        $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                        $inventoryAccount = $groupInfo['inventory-acct'] ?? '';
                        $expenseAccount   = $groupInfo['expense-acct'] ?? $groupInfo['cogs-acct'] ?? '';

                        if (empty($inventoryAccount) || empty($expenseAccount)) {
                            throw new \Exception("Konfigurasi akun akuntansi untuk item {$itemCode} belum lengkap.");
                        }

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $expenseAccount,
                            'debit'     => $subtotal,
                            'credit'    => 0,
                            'remark'    => "Beban Bahan Item: {$itemCode} [Batch: {$batchNum}] - IP {$txnNumber}"
                        ];
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $inventoryAccount,
                            'debit'     => 0,
                            'credit'    => $subtotal,
                            'remark'    => "Pengurangan Persediaan Item: {$itemCode} [Batch: {$batchNum}] - IP {$txnNumber}"
                        ];
                    }
                } else {
                    foreach ($ledgerEntries as $ledger) {
                        $qty      = (float)($ledger['out_qty'] ?? 0);
                        if ($qty <= 0) continue;
                        
                        $itemCode = $ledger['itemcode'] ?? '';
                        $unitCost = (float)($ledger['unitcost'] ?? 0);
                        $subtotal = $qty * $unitCost;
                        $batchNum = $ledger['batchnum'] ?? '-';

                        $itemInfo  = $itemMap[$itemCode] ?? [];
                        $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                        $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                        $inventoryAccount = $groupInfo['inventory-acct'] ?? '';
                        $expenseAccount   = $groupInfo['expense-acct'] ?? $groupInfo['cogs-acct'] ?? '';

                        if (empty($inventoryAccount) || empty($expenseAccount)) {
                            throw new \Exception("Akun persediaan/beban untuk item {$itemCode} belum dikonfigurasi.");
                        }

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $expenseAccount,
                            'debit'     => $subtotal,
                            'credit'    => 0,
                            'remark'    => "Beban Pemakaian Bahan Item: {$itemCode} [Batch: {$batchNum}, Qty: {$qty}, Cost: {$unitCost}] - IP {$txnNumber}"
                        ];

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $inventoryAccount,
                            'debit'     => 0,
                            'credit'    => $subtotal,
                            'remark'    => "Pengurangan Persediaan Item: {$itemCode} [Batch: {$batchNum}, Qty: {$qty}, Cost: {$unitCost}] - IP {$txnNumber}"
                        ];
                    }
                }
                break;

            case 'RP':
                foreach ($detailData as $item) {
                    $qty      = (float)($item['qty'] ?? 0);
                    $itemCode = $item['itemcode'] ?? '';

                    $itemInfo  = $itemMap[$itemCode] ?? [];
                    $unitCost  = (float)($item['unitcost'] ?? $itemInfo['price'] ?? $itemInfo['unitprice'] ?? 0);
                    $subtotal  = $qty * $unitCost;

                    $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                    $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                    $inventoryAccount = $groupInfo['inventory-acct'] ?? '';
                    $expenseAccount   = $groupInfo['expense-acct'] ?? $groupInfo['cogs-acct'] ?? '';

                    if (empty($inventoryAccount)) {
                        throw new \Exception("Akun persediaan (inventory-acct) untuk item {$itemCode} belum dikonfigurasi.");
                    }
                    if (empty($expenseAccount)) {
                        throw new \Exception("Akun beban/produksi (expense-acct/cogs-acct) untuk item {$itemCode} belum dikonfigurasi.");
                    }

                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $inventoryAccount,
                        'debit'     => $subtotal,
                        'credit'    => 0,
                        'remark'    => 'Persediaan Barang Jadi Item: ' . $itemCode . ' - Receipt from Production ' . $txnNumber
                    ];

                    $journalDetails[] = [
                        'linenum'   => count($journalDetails) + 1,
                        'acctcode'  => $expenseAccount,
                        'debit'     => 0,
                        'credit'    => $subtotal,
                        'remark'    => 'Penerimaan Produksi Clearing ' . $txnNumber . ' - Item ' . $itemCode
                    ];
                }
                break;

            case 'LOADING':
                $ledgerEntries = DB::table('stockledger')
                    ->where(['docnum' => $txnNumber, 'source' => 'LOADING OUT'])
                    ->get()
                    ->map(fn($item) => (array)$item)
                    ->toArray();

                if (!empty($ledgerEntries)) {
                    foreach ($ledgerEntries as $ledger) {
                        $qty      = (float)($ledger['out_qty'] ?? 0);
                        if ($qty <= 0) continue;
                        
                        $itemCode = $ledger['itemcode'] ?? '';
                        $unitCost = (float)($ledger['unitcost'] ?? 0);
                        $cost     = $qty * $unitCost; 
                        $batchNum = $ledger['batchnum'] ?? '-';

                        $itemInfo  = $itemMap[$itemCode] ?? [];
                        $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? null;
                        $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                        $inventoryAccount = $groupInfo['inventory-acct'] ?? '';
                        $cogsAccount      = $groupInfo['cogs-acct'] ?? $groupInfo['shippedgoods-acct'] ?? $groupInfo['expense-acct'] ?? '';

                        if (empty($cogsAccount) || empty($inventoryAccount)) {
                            throw new \Exception("Akun HPP/Shipped Goods atau Persediaan untuk item {$itemCode} belum dikonfigurasi di Item Group.");
                        }

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $cogsAccount,
                            'debit'     => $cost,
                            'credit'    => 0,
                            'remark'    => "HPP / Barang Terkirim Item: {$itemCode} [Batch: {$batchNum}] - Loading {$txnNumber}"
                        ];

                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $inventoryAccount,
                            'debit'     => 0,
                            'credit'    => $cost,
                            'remark'    => "Pengurangan Persediaan Gudang STP: {$itemCode} [Batch: {$batchNum}] - Loading {$txnNumber}"
                        ];
                    }
                }
                break;

            case 'STOCK_OPNAME':
                foreach ($detailData as $item) {
                    $systemQty = (float)($item['systemqty'] ?? 0);
                    $actualQty = (float)($item['physicalqty'] ?? $item['qty'] ?? 0);
                    $diffQty   = $actualQty - $systemQty;

                    if (abs($diffQty) <= 0.0001) continue;

                    $itemCode = $item['itemcode'] ?? '';
                    $unitCost = (float)($item['unitcost'] ?? 0);
                    $batchNum = $item['batchnum'] ?? '-';
                    $totalValuation = abs($diffQty) * $unitCost;

                    $itemInfo  = $itemMap[$itemCode] ?? [];
                    $groupCode = $itemInfo['itemgroupcode'] ?? $itemInfo['groupcode'] ?? $itemInfo['itemgroup'] ?? $itemInfo['group'] ?? null;
                    $groupInfo = ($groupCode && isset($groupMap[$groupCode])) ? $groupMap[$groupCode] : [];

                    $inventoryAccount = $groupInfo['inventory-acct'] ?? '';
                    if (empty($inventoryAccount)) {
                        throw new \Exception("Akun persediaan (inventory-acct) untuk item {$itemCode} belum dikonfigurasi.");
                    }

                    $opnameAcct = '5100-00-041';

                    if ($diffQty > 0) {
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $inventoryAccount,
                            'debit'     => $totalValuation,
                            'credit'    => 0,
                            'remark'    => "Stock Opname Gain Item: {$itemCode} [Batch: {$batchNum}, Diff: {$diffQty}] - {$txnNumber}"
                        ];
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $opnameAcct,
                            'debit'     => 0,
                            'credit'    => $totalValuation,
                            'remark'    => "Stock Opname Gain Item: {$itemCode} [Batch: {$batchNum}, Diff: {$diffQty}] - {$txnNumber}"
                        ];
                    } else {
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $opnameAcct,
                            'debit'     => $totalValuation,
                            'credit'    => 0,
                            'remark'    => "Stock Opname Loss Item: {$itemCode} [Batch: {$batchNum}, Diff: " . abs($diffQty) . "] - {$txnNumber}"
                        ];
                        $journalDetails[] = [
                            'linenum'   => count($journalDetails) + 1,
                            'acctcode'  => $inventoryAccount,
                            'debit'     => 0,
                            'credit'    => $totalValuation,
                            'remark'    => "Stock Opname Loss Item: {$itemCode} [Batch: {$batchNum}, Diff: " . abs($diffQty) . "] - {$txnNumber}"
                        ];
                    }
                }
                break;    
        }

        return $isUpdate && $journalId 
            ? $this->saveedit($journalId, $journalHeader, $journalDetails) 
            : $this->saveadd($journalHeader, $journalDetails);
    }

    public function updateStatusByReference($reference, $status)
    {
        $updateData = [
            'status'      => $status,
            'updateddate' => date('Y-m-d H:i:s')
        ];

        if ((int)$status === 0) {
            $updateData['canceleddate'] = date('Y-m-d H:i:s');
        }

        return DB::table($this->table)
                ->where('refnum', $reference)
                ->update($updateData);
    }

    public function deleteByReference(string $txnNumber): bool
    {
        $existingJournal = self::where('refnum', $txnNumber)->first();
        if ($existingJournal) {
            $journalId = $existingJournal->id;
            DB::table($this->detailTable)->where($this->detailForeignKey, $journalId)->delete();
            $existingJournal->delete();
            return true;
        }
        return false;
    }
}