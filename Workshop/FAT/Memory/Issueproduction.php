<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MIssueproduction;
use App\Models\MWorkorder;
use App\Models\MBom;
use App\Models\MWarehousemaster;
use App\Models\MItemmaster;
use App\Models\MItemwarehouse;
use App\Models\MItembatch;
use App\Models\MStockledger;
use App\Models\MJournal;

class Issueproduction extends BaseTransactionController
{
    protected $model;
    protected MItemmaster $mItemmaster;
    protected MWarehousemaster $mWarehousemaster;
    protected MBom $mBom;
    protected MWorkorder $mWorkorder;
    protected MItemwarehouse $mItemwarehouse;
    protected MItembatch $mItembatch;
    protected MStockledger $mStockledger;
    protected MJournal $mJournal;
    
    protected bool $showImport   = false;
    protected bool $showRemark   = true;
    protected bool $showSummary  = false;
    protected bool $showFreight  = false;
    protected bool $showDpp      = false;
    protected bool $showBtndraft = true;

    protected array $unlimitedItems = ['100.00018'];

    public function __construct()
    {
        $this->model            = new MIssueproduction();
        $this->mItemmaster      = new MItemmaster();
        $this->mWarehousemaster = new MWarehousemaster();
        $this->mWorkorder       = new MWorkorder();
        $this->mBom             = new MBom();
        $this->mItemwarehouse   = new MItemwarehouse();
        $this->mItembatch       = new MItembatch();
        $this->mStockledger     = new MStockledger();
        $this->mJournal         = new MJournal();
    }

    protected function getTitle(): string { return 'Issue for Production'; }
    protected function getBaseRoute(): string { return 'issueproduction'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'FP-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor Dokumen', 'Tanggal', 'Work Order', 'Gudang', 'Catatan', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = (array)$item;
        return [
            e($itemArray['baseentry'] ?? '-'),
            e($itemArray['whsname'] ?? '-'),
            e($itemArray['remark'] ?? '-'),
        ]; 
    }

    protected function getCopyFromColumns(): array
    {
        return [
            [
                'name'  => 'docnum',
                'label' => 'No. Work Order',
                'class' => 'font-weight-bold'
            ],
            [
                'name'  => 'docdate',
                'label' => 'Tanggal'
            ],
            [
                'name'  => 'remark',
                'label' => 'Catatan'
            ]
        ];
    }

    public function add()
    {
        if ($guard = $this->guardModuleAccess('isadd')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        $data = [
            'title'                => $this->getTitle(),
            'base_route'           => $this->getBaseRoute(),
            'field_code'           => $this->getTxnNumberField(),
            'field_date'           => $this->getDateField(),
            'current_number'       => $this->generateTxnNumber(),
            'extra'                => $this->getExtraFields(),
            'detail_columns'       => $this->getDetailFields(),
            'action_url'           => $this->getBaseRoute() . '/saveadd',
            'show_remark'          => $this->showRemark,
            'show_summary'         => $this->showSummary,
            'show_freight'         => $this->showFreight,
            'show_dpp'             => $this->showDpp,
            'show_btndraft'        => $this->showBtndraft,
            'message'              => '',

            'copy_from_url'        => url('issueproduction/getOpenList'),
            'copy_from_detail_url' => url('issueproduction/getOpenDetail'),
            'copy_from_label'      => 'Copy from Work Order',
            'copy_from_columns'    => $this->getCopyFromColumns(),

            'batch_allocation'     => true,
        ];

        return view('basetransaction.add', $data);
    }

    protected function getExtraFields($row = null): array
    {
        return [
            [
                'name'      => 'baseentry',
                'label'     => 'No. Work Order (WO)',
                'type'      => 'text',
                'value'     => $row['baseentry'] ?? '',
                'readonly'  => true,
                'disable'   => true
            ],
            [
                'name'     => 'whscode',
                'label'    => 'Gudang Bahan',
                'type'     => 'text', 
                'value'    => $row['whscode'] ?? 'WIP',
                'readonly' => true, 
                'required' => true,
            ],
        ];
    }

    protected function getDetailFields(): array
    {
        return [
            [
                'name'          => 'itemcode',
                'label'         => 'Kode Item',
                'type'          => 'text',
                'width'         => '12%',
                'readonly'      => true, 
            ],
            [
                'name'          => 'itemname',
                'label'         => 'Item Bahan Baku',
                'type'          => 'text',
                'width'         => '28%',
                'readonly'      => true, 
            ],
            [
                'name'          => 'uomcode',
                'label'         => 'Uom',
                'type'          => 'text',
                'width'         => '7%',
                'readonly'      => true,
            ],
            [
                'name'          => 'qty_wo',
                'label'         => 'Qty Wo',
                'type'          => 'text',
                'width'         => '10%',
                'readonly'      => true,
            ],
            [
                'name'          => 'qty',
                'label'         => 'Qty Pemakaian',
                'type'          => 'number',
                'class'         => 'text-end input-qty',
                'min'           => 0,
                'max'           => 9999999,
                'step'          => '0.01',
                'width'         => '10%',
                'required'      => true,
            ],
            [
                'name'          => 'batchnum',
                'label'         => 'Batch Alokasi',
                'type'          => 'batch_with_btn', 
                'class'         => 'bg-light text-primary font-weight-bold', 
                'placeholder'   => 'Hasil Alokasi',
                'width'         => '20%',
                'readonly'      => true,
                'required'      => true,
            ],
            [
                'name'          => 'expdate',
                'label'         => 'Kadaluarsa',
                'type'          => 'date',
                'width'         => '10%',
                'readonly'      => true,
            ],
            [
                'name'          => 'unitcost',
                'label'         => 'Unit Cost',
                'type'          => 'text',  
                'class'         => 'text-end',
                'width'         => '12%', 
                'readonly'      => true, 
                'required'      => true,
            ],
        ];
    }

    private function processStockDeduction($db, $docDate, $txnNumber, $itemCode, $whsCode, $qty, $batchStr, $baseEntry, $baseLine, ?string $batchAllocationsJson = null)
    {
        if (empty($itemCode) || $qty <= 0) return;

        if (in_array($itemCode, $this->unlimitedItems)) {
            if (!empty($baseEntry) && !empty($baseLine)) {
                $this->updateWOBaseQty($db, $baseEntry, $baseLine, $qty);
            }
            
            if (method_exists($this->mStockledger, 'recordEntry')) {
                $this->mStockledger->recordEntry([
                    'docdate'  => $docDate, 
                    'docnum'   => $txnNumber, 
                    'source'   => 'Issue Production',
                    'itemcode' => $itemCode, 
                    'whscode'  => $whsCode, 
                    'batchnum' => 'UNL', 
                    'expdate'  => '2045-08-17', 
                    'areacode' => '',
                    'in_qty'   => 0.0000, 
                    'out_qty'  => $qty, 
                    'unitcost' => 0
                ]);
            }
            return; 
        }

        $allocations = [];
        if (!empty($batchAllocationsJson) && $batchAllocationsJson !== 'null') {
            $allocations = json_decode($batchAllocationsJson, true);
        }

        if (!empty($allocations) && is_array($allocations)) {
            foreach ($allocations as $batchNum => $takeQty) {
                $takeQty = (float)$takeQty;
                if ($takeQty <= 0) continue;

                $batchInfo = DB::table('itembatch')
                                ->where('itemcode', $itemCode)
                                ->where('whscode', $whsCode)
                                ->where('batchnum', $batchNum)
                                ->first();
                $batchInfoArray = is_object($batchInfo) ? (array)$batchInfo : $batchInfo;

                $rawExpDate = $batchInfoArray['expdate'] ?? null;
                $expDate    = (empty($rawExpDate) || $rawExpDate === '0000-00-00' || $rawExpDate === 'dd/mm/yyyy') ? '2045-08-17' : $rawExpDate;
                $unitCost   = (float)($batchInfoArray['unitcost'] ?? 0);
                $areaCode   = $batchInfoArray['areacode'] ?? ''; 

                if (method_exists($this->mStockledger, 'recordEntry')) {
                    $this->mStockledger->recordEntry([
                        'docdate'  => $docDate,
                        'docnum'   => $txnNumber,
                        'source'   => 'Issue Production',
                        'itemcode' => $itemCode,
                        'whscode'  => $whsCode,
                        'batchnum' => $batchNum,
                        'expdate'  => $expDate,
                        'areacode' => $areaCode, 
                        'in_qty'   => 0.0000,
                        'out_qty'  => $takeQty,
                        'unitcost' => $unitCost
                    ]);
                }

                if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                    $this->mItemwarehouse->reduceStock($itemCode, $whsCode, $takeQty);
                }
                if (method_exists($this->mItembatch, 'reduceBatchStock')) {
                    $this->mItembatch->reduceBatchStock($itemCode, $whsCode, $areaCode, $batchNum, $takeQty);
                }
            }
        } else {
            $selectedBatches = array_filter(array_map('trim', explode(',', $batchStr)));
            $remainingQty = $qty;

            $builder = DB::table('itembatch')
                          ->where('itemcode', $itemCode)
                          ->where('whscode', $whsCode)
                          ->where('onhand', '>', 0);
                          
            if (!empty($selectedBatches)) {
                $builder->whereIn('batchnum', $selectedBatches);
            }
            
            $availableBatches = $builder->orderByRaw('expdate IS NULL ASC')
                                        ->orderBy('expdate', 'ASC')
                                        ->get();

            foreach ($availableBatches as $batch) {
                if ($remainingQty <= 0) break;
                
                $batchArray = (array)$batch;
                $bStock = (float)($batchArray['onhand']);
                $take = min($bStock, $remainingQty);
                
                $batchNum   = $batchArray['batchnum'];
                $rawExpDate = $batchArray['expdate'] ?? null;
                $expDate    = (empty($rawExpDate) || $rawExpDate === '0000-00-00' || $rawExpDate === 'dd/mm/yyyy') ? '2045-08-17' : $rawExpDate;
                $unitCost   = (float)($batchArray['unitcost'] ?? 0);
                $areaCode   = $batchArray['areacode'] ?? ''; 
                
                if (method_exists($this->mStockledger, 'recordEntry')) {
                    $this->mStockledger->recordEntry([
                        'docdate'  => $docDate,
                        'docnum'   => $txnNumber,
                        'source'   => 'Issue Production',
                        'itemcode' => $itemCode,
                        'whscode'  => $whsCode,
                        'batchnum' => $batchNum,
                        'expdate'  => $expDate,
                        'areacode' => $areaCode, 
                        'in_qty'   => 0.0000,
                        'out_qty'  => $take,
                        'unitcost' => $unitCost
                    ]);
                }

                if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                    $this->mItemwarehouse->reduceStock($itemCode, $whsCode, $take);
                }
                if (method_exists($this->mItembatch, 'reduceBatchStock')) {
                    $this->mItembatch->reduceBatchStock($itemCode, $whsCode, $areaCode, $batchNum, $take);
                }
                
                $remainingQty -= $take;
            }

            if ($remainingQty > 0.001) {
                throw new \Exception("Stok tidak mencukupi di gudang {$whsCode} pada batch yang dipilih ({$batchStr}) untuk item {$itemCode}.");
            }
        }

        if (!empty($baseEntry) && !empty($baseLine)) {
            $this->updateWOBaseQty($db, $baseEntry, $baseLine, $qty);
        }
    }

    public function saveadd(Request $request): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) {
            return response()->json([], 405);
        }

        $postData = $request->post();
        
        $validationError = $this->validateTransaction($postData);
        if ($validationError) {
            return $this->ajaxResponse('error', $validationError);
        }

        $txnNumberField = $this->getTxnNumberField(); 
        $txnNumber      = $postData[$txnNumberField] ?? $this->generateTxnNumber();
        $status         = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1; 

        DB::beginTransaction();

        try {
            $headerData = $this->prepareHeaderOnCreate($postData, $status, $request);
            $headerData[$txnNumberField] = $txnNumber;

            $items = $postData['items'] ?? $postData['details'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?? [];
            }
            $detailData = $this->prepareDetailsOnCreate($items, $txnNumber);
            
            $this->model->saveadd($headerData, $detailData, $txnNumberField);

            if ($status === 1) {
                $defaultWhs = $headerData['whscode'] ?? '';
                $docDate    = $headerData['docdate'] ?? date('Y-m-d H:i:s');
                $baseEntry  = $headerData['baseentry'] ?? null; 

                foreach ($detailData as $index => $item) {
                    $itemCode       = $item['itemcode'] ?? '';
                    $qty            = (float)($item['qty'] ?? 0);
                    $whsCode        = $item['whscode'] ?? $defaultWhs;
                    $batchStr       = $item['batchnum'] ?? '';
                    $baseLine       = $item['baseline'] ?? null; 
                    $batchAllocJson = $items[$index]['batch_allocations'] ?? null;

                    $this->processStockDeduction(DB::getFacadeRoot(), $docDate, $txnNumber, $itemCode, $whsCode, $qty, $batchStr, $baseEntry, $baseLine, $batchAllocJson);
                }

                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'IP');
                } elseif (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('IP', $txnNumber, $headerData, $detailData);
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => ($status === 9 ? 'draft' : 'add'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            $message = $status === 9 ? 'Draft Issue Production berhasil disimpan.' : 'Issue Production berhasil disimpan dan stok gudang WIP telah dipotong.';

            return $this->ajaxResponse('success', $message, [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        if ($guard = $this->guardModuleAccess('isedit')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        $header = $this->model->find($id);
        if (!$header) {
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Data transaksi tidak ditemukan.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') 
            ? $header->toArray() 
            : (array)$header;

        $fieldCode = $this->getTxnNumberField();
        $fieldDate = $this->getDateField();
        $docNumber = $headerArray[$fieldCode] ?? $id;
        $docRemark = $headerArray['remark'] ?? $headerArray['catatan'] ?? '';

        $status = (string)($headerArray['status'] ?? '');
        if (in_array($status, ['0', '2'], true)) { 
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Transaksi yang sudah selesai/batal tidak dapat diubah.');
        }

        $txnData = $this->model->getWithDetails($docNumber);
        $details = $txnData['details'] ?? [];

        $oldBaseEntry = $headerArray['baseentry'] ?? null;
        $woMap = [];
        if (!empty($oldBaseEntry)) {
            $woData = $this->mWorkorder->getWithDetails($oldBaseEntry);
            if (!empty($woData['details'])) {
                foreach ($woData['details'] as $wRow) {
                    $wRowArray = (array)$wRow;
                    $woMap[$wRowArray['linenum']] = [
                        'total' => (float)($wRowArray['qty'] ?? 0),
                        'done'  => (float)($wRowArray['qtycompleted'] ?? 0)
                    ];
                }
            }
        }

        return view('basetransaction.edit', [
            'title'          => 'Edit ' . $this->getTitle(),
            'base_route'     => $this->getBaseRoute(),
            'action_url'     => $this->getBaseRoute() . '/saveedit/' . $id,
            'field_code'     => $fieldCode,
            'field_date'     => $fieldDate,
            'docNumber'      => $docNumber,
            'docRemark'      => $docRemark,
            'header'         => $headerArray,
            'details'        => $details,
            'show_remark'    => $this->showRemark,
            'show_summary'   => $this->showSummary,
            'show_freight'   => $this->showFreight,
            'show_dpp'       => $this->showDpp,
            'show_btndraft'  => $this->showBtndraft,
            'extra'          => $this->getExtraFields($headerArray),
            'detail_columns' => $this->getDetailFields() ?? [],
            'message'        => Session::get('message') ?? Session::get('error_msg') ?? '',
            'batch_allocation' => true 
        ]);
    }

    public function saveedit(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) {
            return response()->json([], 405);
        }

        $postData  = $request->post();
        $txnNumber = $id ?? $postData['docnum'] ?? null;

        if (empty($txnNumber)) {
            return $this->ajaxResponse('error', 'Nomor transaksi tidak valid untuk proses edit.');
        }

        $validationError = $this->validateTransaction($postData, $txnNumber);
        if ($validationError) {
            return $this->ajaxResponse('error', $validationError);
        }

        $status = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;

        DB::beginTransaction();

        try {
            $oldHeader    = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            $oldDetails   = $this->model->get_details($txnNumber);
            $oldStatus    = (int)($oldHeaderArray['status'] ?? 9); 
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;

            if ($oldStatus === 1 && !empty($oldDetails)) {
                $oldLedgers = DB::table('stockledger')->where('docnum', $txnNumber)->get();
                
                $netLedgerMap = [];
                foreach ($oldLedgers as $l) {
                    $lArray = (array)$l;
                    $iCode = $lArray['itemcode'];
                    $wCode = $lArray['whscode'];
                    $bNum  = $lArray['batchnum'] ?? '';
                    $key   = $iCode . '|' . $wCode . '|' . $bNum;
                    
                    if (!isset($netLedgerMap[$key])) {
                        $netLedgerMap[$key] = [
                            'itemcode' => $iCode,
                            'whscode'  => $wCode,
                            'batchnum' => $bNum,
                            'areacode' => $lArray['areacode'] ?? '',
                            'expdate'  => $lArray['expdate'],
                            'unitcost' => (float)$lArray['unitcost'],
                            'net_qty'  => 0
                        ];
                    }
                    $netLedgerMap[$key]['net_qty'] += ((float)$lArray['out_qty'] - (float)$lArray['in_qty']);
                }

                foreach ($netLedgerMap as $ledger) {
                    $netQty = $ledger['net_qty'];
                    if ($netQty <= 0.0001) continue; 

                    if (method_exists($this->mStockledger, 'recordEntry')) {
                        $this->mStockledger->recordEntry([
                            'docdate'  => date('Y-m-d H:i:s'),
                            'docnum'   => $txnNumber,
                            'source'   => 'Edit Issue Production', 
                            'itemcode' => $ledger['itemcode'],
                            'whscode'  => $ledger['whscode'],
                            'batchnum' => $ledger['batchnum'],
                            'expdate'  => $ledger['expdate'],
                            'areacode' => $ledger['areacode'],
                            'in_qty'   => $netQty, 
                            'out_qty'  => 0.0000,
                            'unitcost' => $ledger['unitcost']
                        ]);
                    }

                    if (in_array($ledger['itemcode'], $this->unlimitedItems)) continue;
                    
                    if (method_exists($this->mItemwarehouse, 'addStock')) {
                        $this->mItemwarehouse->addStock($ledger['itemcode'], $ledger['whscode'], $netQty);
                    }
                    if (method_exists($this->mItembatch, 'addBatchStock')) {
                        $this->mItembatch->addBatchStock(
                            $ledger['itemcode'], $ledger['whscode'], $ledger['areacode'], 
                            $ledger['batchnum'], $ledger['expdate'], $ledger['unitcost'], $netQty
                        );
                    }
                }
                
                foreach ($oldDetails as $oldItem) {
                    $oldItemArray = (array)$oldItem;
                    $oldQty = (float)($oldItemArray['qty'] ?? 0);
                    $oldBaseLine = $oldItemArray['baseline'] ?? null;
                    if (!empty($oldItemArray['itemcode']) && $oldQty > 0 && !empty($oldBaseEntry) && !empty($oldBaseLine)) {
                        $this->updateWOBaseQty(DB::getFacadeRoot(), $oldBaseEntry, $oldBaseLine, -$oldQty);
                    }
                }
            }

            $modelId = $oldHeaderArray['docnum'] ?? $id;
            $items = $postData['items'] ?? $postData['details'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?? [];
            }

            $headerData = $this->prepareHeaderData($txnNumber, $postData, $status);
            $detailData = $this->prepareDetailsData($txnNumber, $postData); 

            $saveResult = $this->model->saveedit($modelId, $headerData, $detailData);
            if (is_array($saveResult) && ($saveResult['status'] ?? '') === 'error') {
                throw new \Exception($saveResult['message'] ?? 'Gagal memperbarui data.');
            }

            if ($status === 1) {
                $defaultWhs   = $headerData['whscode'] ?? '';
                $rawDocDate   = $headerData['docdate'] ?? date('Y-m-d');
                $docDate      = (strlen($rawDocDate) === 10) ? $rawDocDate . ' ' . date('H:i:s') : $rawDocDate;
                $newBaseEntry = $headerData['baseentry'] ?? null;

                foreach ($detailData as $index => $item) {
                    $itemCode       = $item['itemcode'] ?? '';
                    $qty            = (float)($item['qty'] ?? 0);
                    $whsCode        = $item['whscode'] ?? $defaultWhs;
                    $batchStr       = $item['batchnum'] ?? '';
                    $baseLine       = $item['baseline'] ?? null; 
                    $batchAllocJson = $items[$index]['batch_allocations'] ?? null;

                    if (!empty($batchAllocJson) && $batchAllocJson !== 'null') {
                        $allocs = json_decode($batchAllocJson, true);
                        if (is_array($allocs) && count($allocs) > 0) {
                            $sumAlloc = array_sum($allocs);
                            if (abs($sumAlloc - $qty) > 0.001) {
                                reset($allocs);
                                $firstBatch = key($allocs);
                                $allocs[$firstBatch] = $qty;
                                $batchAllocJson = json_encode($allocs);
                            }
                        }
                    } elseif (!empty($batchStr)) {
                        $batchAllocJson = json_encode([$batchStr => $qty]);
                    }

                    $this->processStockDeduction(DB::getFacadeRoot(), $docDate, $txnNumber, $itemCode, $whsCode, $qty, $batchStr, $newBaseEntry, $baseLine, $batchAllocJson);
                }

                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'IP');
                } else {
                    if (method_exists($this->mJournal, 'updateJournal')) {
                        $this->mJournal->updateJournal('IP', $txnNumber, $headerData, $detailData);
                    } elseif (method_exists($this->mJournal, 'createJournal')) {
                        $this->mJournal->createJournal('IP', $txnNumber, $headerData, $detailData);
                    }
                }
            } else {
                if ($oldStatus === 1) {
                    if (method_exists($this->mJournal, 'deleteByReference')) {
                        $this->mJournal->deleteByReference($txnNumber);
                    }
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => ($status === 9 ? 'draft' : 'edit'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'Issue Production berhasil diperbarui.', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function saveactive(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) {
            return response()->json([], 405);
        }

        $txnNumber = $id ?? $request->post('docnum') ?? $request->post('id') ?? null;

        if (empty($txnNumber)) {
            return $this->ajaxResponse('error', 'Nomor transaksi tidak valid untuk proses aktivasi.');
        }

        DB::beginTransaction();

        try {
            $header = $this->model->find($txnNumber) ?? $this->model->where('docnum', $txnNumber)->first();
            $headerArray = is_object($header) ? (array)$header : $header;

            if (!$headerArray) {
                return $this->ajaxResponse('error', 'Data transaksi Issue Production tidak ditemukan.');
            }

            if ((int)($headerArray['status'] ?? 0) === 1) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Active.');
            }

            $realId     = $headerArray['id'] ?? $txnNumber;
            $docNum     = $headerArray['docnum'] ?? $txnNumber;
            $defaultWhs = $headerArray['whscode'] ?? '';
            $docDate    = $headerArray['docdate'] ?? date('Y-m-d H:i:s');
            $baseEntry  = $headerArray['baseentry'] ?? null;

            $details = $this->model->get_details($docNum);
            if (empty($details)) {
                return $this->ajaxResponse('error', 'Detail item transaksi kosong, tidak dapat diaktifkan.');
            }

            $updateData = [
                'status'       => 1,
                'approveddate' => date('Y-m-d H:i:s'),
                'approvedby'   => (int) (Session::get('usercode') ?? 1),
            ];

            if (method_exists($this->model, 'saveactive')) {
                $this->model->saveactive($realId, $updateData);
            } else {
                $this->model->update($realId, $updateData);
            }

            foreach ($details as $item) {
                $itemArray = (array)$item;
                $itemCode  = $itemArray['itemcode'] ?? '';
                $qty       = (float)($itemArray['qty'] ?? 0);
                $whsCode   = $itemArray['whscode'] ?? $defaultWhs;
                $batchStr  = $itemArray['batchnum'] ?? ''; 
                $baseLine  = $itemArray['baseline'] ?? null;

                $this->processStockDeduction(DB::getFacadeRoot(), $docDate, $docNum, $itemCode, $whsCode, $qty, $batchStr, $baseEntry, $baseLine, null);
            }

            if (method_exists($this, 'handleJournal')) {
                $this->handleJournal($docNum, 1, $headerArray, $details, 'IP');
            } else {
                if (method_exists($this->mJournal, 'deleteByReference')) {
                    $this->mJournal->deleteByReference($docNum);
                }
                if (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('IP', $docNum, $headerArray, $details);
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'active',
                'fa_id'   => $docNum,
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'Issue Production berhasil diaktifkan dan disetujui (Approved).', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function savecancel(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) {
            return response()->json([], 405);
        }

        $txnNumber = $id ?? $request->post('docnum') ?? null;

        if (empty($txnNumber)) {
            return $this->ajaxResponse('error', 'Nomor transaksi tidak valid untuk proses pembatalan.');
        }

        DB::beginTransaction();

        try {
            $oldHeader  = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            if (!$oldHeaderArray) {
                return $this->ajaxResponse('error', 'Data transaksi Issue Production tidak ditemukan.');
            }

            $oldStatus = (int)($oldHeaderArray['status'] ?? 0);
            if ($oldStatus === 0) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Canceled.');
            }

            $oldDetails   = $this->model->get_details($txnNumber);
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;

            if ($oldStatus === 1 && !empty($oldDetails)) {
                $oldLedgers = DB::table('stockledger')->where('docnum', $txnNumber)->get();
                
                $netLedgerMap = [];
                foreach ($oldLedgers as $l) {
                    $lArray = (array)$l;
                    $iCode = $lArray['itemcode'];
                    $wCode = $lArray['whscode'];
                    $bNum  = $lArray['batchnum'] ?? '';
                    $key   = $iCode . '|' . $wCode . '|' . $bNum;
                    
                    if (!isset($netLedgerMap[$key])) {
                        $netLedgerMap[$key] = [
                            'itemcode' => $iCode,
                            'whscode'  => $wCode,
                            'batchnum' => $bNum,
                            'areacode' => $lArray['areacode'] ?? '',
                            'expdate'  => $lArray['expdate'],
                            'unitcost' => (float)$lArray['unitcost'],
                            'net_qty'  => 0
                        ];
                    }
                    $netLedgerMap[$key]['net_qty'] += ((float)$lArray['out_qty'] - (float)$lArray['in_qty']);
                }
                    
                foreach ($netLedgerMap as $ledger) {
                    $netQty = $ledger['net_qty'];
                    if ($netQty <= 0.0001) continue; 

                    if (method_exists($this->mStockledger, 'recordEntry')) {
                        $this->mStockledger->recordEntry([
                            'docdate'  => date('Y-m-d H:i:s'),
                            'docnum'   => $txnNumber,
                            'source'   => 'Cancel Issue Production',
                            'itemcode' => $ledger['itemcode'],
                            'whscode'  => $ledger['whscode'],
                            'batchnum' => $ledger['batchnum'],
                            'expdate'  => $ledger['expdate'],
                            'areacode' => $ledger['areacode'],
                            'in_qty'   => $netQty, 
                            'out_qty'  => 0.0000,
                            'unitcost' => $ledger['unitcost']
                        ]);
                    }

                    if (in_array($ledger['itemcode'], $this->unlimitedItems)) continue;

                    if (method_exists($this->mItemwarehouse, 'addStock')) {
                        $this->mItemwarehouse->addStock($ledger['itemcode'], $ledger['whscode'], $netQty);
                    }
                    if (method_exists($this->mItembatch, 'addBatchStock')) {
                        $this->mItembatch->addBatchStock(
                            $ledger['itemcode'], $ledger['whscode'], $ledger['areacode'], 
                            $ledger['batchnum'], $ledger['expdate'], $ledger['unitcost'], $netQty
                        );
                    }
                }

                foreach ($oldDetails as $oldItem) {
                    $oldItemArray = (array)$oldItem;
                    $oldQty = (float)($oldItemArray['qty'] ?? 0);
                    $oldBaseLine = $oldItemArray['baseline'] ?? null;
                    if (!empty($oldItemArray['itemcode']) && $oldQty > 0 && !empty($oldBaseEntry) && !empty($oldBaseLine)) {
                        $this->updateWOBaseQty(DB::getFacadeRoot(), $oldBaseEntry, $oldBaseLine, -$oldQty);
                    }
                }
            }
 
            $userCode = (int) (Session::get('usercode') ?? 1);
            
            if (method_exists($this->model, 'cancelDocument')) {
                $this->model->cancelDocument($oldHeaderArray['id'] ?? $id, $userCode);
            } else {
                $this->model->update($oldHeaderArray['id'] ?? $id, [
                    'status'       => 0,
                    'canceleddate' => date('Y-m-d H:i:s'),
                    'canceledby'   => $userCode
                ]);
            }

            if (method_exists($this->mJournal, 'updateStatusByReference')) {
                $this->mJournal->updateStatusByReference($txnNumber, 0);
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'savecancel',
                'fa_id'   => $txnNumber,
                'fa_name' => $this->getTitle()
            ]);

            $alertMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                          . 'Nomor transaksi <strong>' . e($txnNumber) . '</strong> berhasil dibatalkan (Cancel) dan stok dikembalikan.'
                          . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                          . '</div>';

            return $this->ajaxResponse('success', $alertMessage, [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'System Error: ' . $e->getMessage());
        }
    }

    public function view($id)
    {
        if ($guard = $this->guardModuleAccess('isread')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        $header = $this->model->find($id);
        if (!$header) {
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Data transaksi tidak ditemukan.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') 
            ? $header->toArray() 
            : (array)$header;

        $docNumber = $headerArray[$this->getTxnNumberField()] ?? $headerArray['docnum'] ?? $id;

        $txnData = $this->model->getWithDetails($docNumber);
        if (!$txnData) {
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Detail data transaksi tidak ditemukan.');
        }

        $headerArray = $txnData['header'];
        $details     = $txnData['details'] ?? [];

        return view('basetransaction.view', [
            'title'          => 'Detail ' . $this->getTitle(),
            'base_route'     => $this->getBaseRoute(),
            'field_code'     => $this->getTxnNumberField(),
            'field_date'     => $this->getDateField(),
            'docNumber'      => $docNumber,
            'docRemark'      => $headerArray['remark'] ?? $headerArray['catatan'] ?? $headerArray['notes'] ?? '',
            'header'         => $headerArray,
            'details'        => $details,
            'show_remark'    => $this->showRemark ?? true,
            'show_summary'   => $this->showSummary ?? true,
            'show_freight'   => $this->showFreight ?? true,
            'show_dpp'       => $this->showDpp ?? true,
            'show_btndraft'  => $this->showBtndraft ?? true,
            'extra'          => $this->getExtraFields($headerArray),
            'detail_columns' => $this->getDetailFields(),
            'summary'        => [
                'subtotal'   => $headerArray['subtotal'] ?? 0,
                'discount'   => $headerArray['discountamount'] ?? 0,
                'dpp'        => $headerArray['dppamount'] ?? 0, 
                'tax'        => $headerArray['taxamount'] ?? 0, 
                'freight'    => $headerArray['freighttotal'] ?? 0,
                'grandtotal' => $headerArray['grandtotal'] ?? 0,
            ]
        ]);
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request = null): array
    {
        $header = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());

        $data = array_merge($header, [
            'docdate'     => !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s'),
            'baseentry'   => !empty($postData['baseentry']) ? $postData['baseentry'] : null,
            'whscode'     => !empty($postData['whscode']) ? $postData['whscode'] : 'WIP',
            'remark'      => $postData['remark'] ?? '',
            'status'      => $status,
            'createddate' => date('Y-m-d H:i:s'),
            'createdby'   => (int) (Session::get('usercode') ?? 1),
        ]);

        if ($status === 1) {
            $data['approveddate'] = date('Y-m-d H:i:s');
            $data['approvedby']   = (int) (Session::get('usercode') ?? 1);
        }

        return $data;
    }

    protected function prepareHeaderData(string $txnNumber, array $postData, int $status = 1): array
    {
        $data = [
            'docnum'      => $txnNumber,
            'baseentry'   => !empty($postData['baseentry']) ? $postData['baseentry'] : null,
            'whscode'     => !empty($postData['whscode']) ? $postData['whscode'] : 'WIP',
            'remark'      => $postData['remark'] ?? '',
            'status'      => $status,
            'updateddate' => date('Y-m-d H:i:s'),
            'updatedby'   => (int) (Session::get('usercode') ?? 1),
        ];

        if ($status === 1) {
            $data['approveddate'] = date('Y-m-d H:i:s');
            $data['approvedby']   = (int) (Session::get('usercode') ?? 1);
        }

        return $data;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, ?Request $request = null): array
    {
        $detailsData = [];
        foreach ($items as $index => $item) {
            $itemCode    = $item['itemcode'] ?? '';
            $isUnlimited = in_array($itemCode, $this->unlimitedItems);

            $expDate = $item['expdate'] ?? null;
            
            if (empty($expDate) || $expDate === '0000-00-00' || $expDate === 'dd/mm/yyyy') {
                $expDate = '2045-08-17';
            }

            $detailsData[] = [
                'docnum'   => $txnNumber,
                'linenum'  => $index + 1,
                'baseline' => $item['baseline'] ?? $item['linenum'] ?? ($index + 1), 
                'itemcode' => $itemCode,
                'uomcode'  => $item['uomcode'] ?? '',
                'qty'      => (float)($item['qty'] ?? 0),
                'batchnum' => $isUnlimited ? 'UNL' : ($item['batchnum'] ?? ''),
                'expdate'  => $expDate,
                'unitcost' => $isUnlimited ? 0 : ($item['unitcost'] ?? 0), 
            ];
        }
        return $detailsData;
    }

    protected function prepareDetailsData(string $txnNumber, array $postData): array
    {
        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }
        return $this->prepareDetailsOnCreate($items, $txnNumber);
    }

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        if (empty($postData['whscode'])) {
            return 'Gudang asal bahan (whscode) wajib dipilih.';
        }

        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        if (empty($items)) {
            return 'Detail bahan produksi tidak boleh kosong!';
        }

        $baseEntry = $postData['baseentry'] ?? null;
        $woDetailsMap = [];
        $bomDetails = [];
        
        if (!empty($baseEntry)) {
            $woData = $this->mWorkorder->getWithDetails($baseEntry);
            $woHeader = $woData['header'] ?? [];
            if (!empty($woData['details'])) {
                foreach ($woData['details'] as $woRow) {
                    $woRowArray = (array)$woRow;
                    $lineNum = $woRowArray['linenum'] ?? 0;
                    $woDetailsMap[$lineNum] = $woRowArray;
                }
            }

            $bomDocNum = $woHeader['baseentry'] ?? '';
            if (!empty($bomDocNum)) {
                $bomData = method_exists($this->mBom, 'getWithDetails')
                         ? $this->mBom->getWithDetails($bomDocNum)
                         : null;
                $bomDetails = $bomData['details'] ?? [];
            }
        }

        $oldQtyMap = [];
        if (!empty($currentDocNum)) {
            $oldDetails = $this->model->get_details($currentDocNum);
            if (!empty($oldDetails)) {
                foreach ($oldDetails as $oldItem) {
                    $oldItemArray = (array)$oldItem;
                    $bLine = $oldItemArray['baseline'] ?? 0;
                    $oldQtyMap[$bLine] = (float)($oldItemArray['qty'] ?? 0);
                }
            }
        }

        foreach ($items as $index => $item) {
            $rowNum   = $index + 1;
            $itemCode = $item['itemcode'] ?? '';
            $qty      = (float)($item['qty'] ?? 0);
            $batchNum = $item['batchnum'] ?? '';
            $baseLine = $item['baseline'] ?? ($index + 1);

            if (empty($itemCode)) {
                return "Item bahan pada baris ke-{$rowNum} belum dipilih.";
            }
            if ($qty <= 0) {
                return "Jumlah (Qty) pemakaian pada baris ke-{$rowNum} harus lebih besar dari 0.";
            }
            
            if (!in_array($itemCode, $this->unlimitedItems) && empty($batchNum)) {
                return "Kode batch pada bahan baris ke-{$rowNum} wajib dialokasikan."; 
            }

            $batchAllocJson = $items[$index]['batch_allocations'] ?? null;
            if (!empty($batchAllocJson) && $batchAllocJson !== 'null' && !in_array($itemCode, $this->unlimitedItems)) {
                $allocs = json_decode($batchAllocJson, true);
                if (is_array($allocs)) {
                    $sumAlloc = 0;
                    foreach ($allocs as $aQty) {
                        $sumAlloc += (float)$aQty;
                    }
                    if (abs($sumAlloc - $qty) > 0.001) {
                        return "Total alokasi batch (" . $sumAlloc . ") tidak sesuai dengan Qty Pemakaian (" . $qty . ") pada baris ke-{$rowNum}. Pastikan Qty Pemakaian disesuaikan dengan nilai total batch.";
                    }
                }
            }

            if (!empty($woDetailsMap) && isset($woDetailsMap[$baseLine])) {
                $woRow           = $woDetailsMap[$baseLine];
                $qtyRequired     = (float)($woRow['qty'] ?? 0) - (float)($woRow['qtycompleted'] ?? 0);
                
                if (!empty($currentDocNum) && isset($oldQtyMap[$baseLine])) {
                    $qtyRequired += $oldQtyMap[$baseLine];
                }

                $tolerancePct    = (float)($woRow['toleransi'] ?? 0); 
                if ($tolerancePct == 0 && !empty($bomDetails)) {
                    foreach ($bomDetails as $bd) {
                        $bdArray = (array)$bd;
                        if ($bdArray['itemcode'] === $itemCode && ($bdArray['itemtype'] ?? '') === 'INPUT') {
                            $tolerancePct = (float)($bdArray['toleransi'] ?? 0);
                            break;
                        }
                    }
                }

                $toleranceAmount = $qtyRequired * ($tolerancePct / 100);
                $maxAllowed = $qtyRequired + $toleranceAmount;

                if ($qty > $maxAllowed) {
                    return "Qty untuk item {$itemCode} ({$qty}) melampaui batas toleransi {$tolerancePct}% dari sisa Work Order (Maksimal pemakaian diizinkan: {$maxAllowed}).";
                }
            }
        }

        return null;
    }

    public function getOpenList(Request $request): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        $listData = $this->mWorkorder->getOpenIssue();

        $html = view('basetransaction.popcopyfromrows', [
            'listData'          => $listData,
            'copy_from_columns' => $this->getCopyFromColumns(),
        ])->render();

        return response()->json([
            'status' => true, 
            'html'   => $html
        ]);
    }

    public function getOpenDetail(Request $request, $docnum = null): JsonResponse
    {
        if (!$request->ajax() || empty($docnum)) {
            return response()->json([], 404);
        }

        $woData = method_exists($this->mWorkorder, 'getWithDetails') 
            ? $this->mWorkorder->getWithDetails($docnum) 
            : null;

        if (!$woData) {
            return response()->json(['status' => false, 'message' => 'Data Work Order tidak ditemukan.']);
        }

        $woHeader = $woData['header'] ?? [];

        $bomDocNum  = $woHeader['baseentry'] ?? '';
        $bomDetails = [];
        if (!empty($bomDocNum)) {
            $bomData = method_exists($this->mBom, 'getWithDetails')
                     ? $this->mBom->getWithDetails($bomDocNum)
                     : null;
            $bomDetails = $bomData['details'] ?? [];
        }

        $header = $woHeader;
        $header['baseentry'] = $header['docnum'] ?? $docnum;
        $header['whscode']   = !empty($header['whscode']) ? $header['whscode'] : 'WIP';

        unset($header['docnum'], $header['docdate'], $header['status'], $header['createddate'], $header['createdby']);

        $processedDetails = [];
        if (!empty($woData['details'])) {
            foreach ($woData['details'] as $row) {
                $rowArray = (array)$row;
                if (($rowArray['itemtype'] ?? '') !== 'INPUT') {
                    continue; 
                }

                $itemCode  = $rowArray['itemcode'] ?? '';
                $qtyTotal  = (float)($rowArray['qty'] ?? 0);
                $qtyDone   = (float)($rowArray['qtycompleted'] ?? 0);
                $qtyNeeded = $qtyTotal - $qtyDone;

                if ($qtyNeeded <= 0) continue; 
                
                $toleransi = (float)($rowArray['toleransi'] ?? 0);
                if ($toleransi == 0 && !empty($bomDetails)) {
                    foreach ($bomDetails as $bd) {
                        $bdArray = (array)$bd;
                        if ($bdArray['itemcode'] === $itemCode && ($bdArray['itemtype'] ?? '') === 'INPUT') {
                            $toleransi = (float)($bdArray['toleransi'] ?? 0);
                            break;
                        }
                    }
                }

                $maxAllowableQty = $qtyNeeded + ($qtyNeeded * $toleransi / 100);

                if (in_array($itemCode, $this->unlimitedItems)) {
                    $processedDetails[] = [
                        'baseline'          => $rowArray['linenum'] ?? 1, 
                        'itemcode'          => $itemCode,
                        'itemname'          => $rowArray['itemname'] ?? '',
                        'uomcode'           => $rowArray['uomcode'] ?? '',
                        'qty_wo'            => $qtyNeeded, 
                        'qty'               => $qtyNeeded,  
                        'qtyrequest'        => 9999999,
                        'batchnum'          => 'UNL',
                        'batch_allocations' => json_encode(['UNL' => $qtyNeeded]), 
                        'expdate'           => '2045-08-17', 
                        'unitcost'          => 0, 
                    ];
                    continue;
                }

                $whsCode   = $header['whscode'];

                $fefoBatches = method_exists($this->mItembatch, 'getStockByFEFO') 
                    ? $this->mItembatch->getStockByFEFO($itemCode, $whsCode) 
                    : [];

                $remainingQty = $qtyNeeded;
                $batchNumArray = [];
                $firstExpDate = null;
                $unitCostArray = []; 
                $totalAvailableStock = 0; 
                $allocMap = []; 

                if (!empty($fefoBatches)) {
                    foreach ($fefoBatches as $batch) {
                        $batchArray = (array)$batch;
                        $batchStock = (float)($batchArray['onhand'] ?? 0);
                        if ($batchStock <= 0) continue;
                        
                        $totalAvailableStock += $batchStock; 

                        if ($remainingQty > 0) {
                            $qtyToTake  = min($batchStock, $remainingQty);
                            $batchNumArray[] = $batchArray['batchnum'];
                            $allocMap[$batchArray['batchnum']] = $qtyToTake; 
                            
                            if (!$firstExpDate) {
                                $bExp = $batchArray['expdate'] ?? null;
                                $firstExpDate = (empty($bExp) || $bExp === '0000-00-00' || $bExp === 'dd/mm/yyyy') ? '2045-08-17' : $bExp;
                            }
                            
                            $uCost = (float)($batchArray['unitcost'] ?? 0);
                            if (!in_array($uCost, $unitCostArray)) {
                                $unitCostArray[] = $uCost;
                            }

                            $remainingQty -= $qtyToTake;
                        }
                    }
                }

                $finalQty = min($qtyNeeded, $totalAvailableStock);
                
                if ($finalQty <= 0) continue; 

                $processedDetails[] = [
                    'baseline'          => $rowArray['linenum'] ?? 1, 
                    'itemcode'          => $itemCode,
                    'itemname'          => $rowArray['itemname'] ?? '',
                    'uomcode'           => $rowArray['uomcode'] ?? '',
                    'qty_wo'            => $qtyNeeded, 
                    'qty'               => $finalQty,  
                    'qtyrequest'        => $maxAllowableQty,  
                    'batchnum'          => implode(', ', $batchNumArray),
                    'batch_allocations' => json_encode($allocMap), 
                    'expdate'           => $firstExpDate,
                    'unitcost'          => implode(', ', $unitCostArray), 
                ];
            }
        }

        return response()->json([
            'status'  => true,
            'header'  => $header,
            'details' => $processedDetails
        ]);
    }

    private function updateWOBaseQty($db, string $baseEntry, ?int $baseLine, float $qtyChange)
    {
        if (empty($baseEntry) || empty($baseLine) || $qtyChange == 0) {
            return;
        }

        DB::table('wodetail')
           ->where('docnum', $baseEntry)
           ->where('linenum', $baseLine)
           ->update([
               'qtycompleted' => DB::raw('qtycompleted + ' . $qtyChange)
           ]);
    }

    public function getBatchAllocation(Request $request): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        $itemCode = $request->post('itemcode');
        $whsCode  = $request->post('whscode');

        if (empty($itemCode) || empty($whsCode)) {
            return response()->json([
                'status'  => false,
                'message' => 'Parameter item atau gudang tidak lengkap.'
            ]);
        }
        
        if (in_array($itemCode, $this->unlimitedItems)) {
            return response()->json([
                'status' => true,
                'data'   => [
                    [
                        'batchnum' => 'UNL',
                        'expdate'  => '2045-08-17', 
                        'onhand'   => 9999999,
                        'unitcost' => 0
                    ]
                ]
            ]);
        }

        $batches = method_exists($this->mItembatch, 'getStockByFEFO') 
            ? $this->mItembatch->getStockByFEFO($itemCode, $whsCode) 
            : [];

        foreach ($batches as &$b) {
            $bArray = (array)$b;
            if (empty($bArray['expdate']) || $bArray['expdate'] === '0000-00-00' || $bArray['expdate'] === 'dd/mm/yyyy') {
                $bArray['expdate'] = '2045-08-17';
            }
            $b = $bArray;
        }
        unset($b);

        return response()->json([
            'status' => true,
            'data'   => $batches
        ]);
    }

    public function getEditBatchData(Request $request, $docnum = null): JsonResponse
    {
        if (!$request->ajax() || empty($docnum)) {
            return response()->json([], 404);
        }

        $details = $this->model->get_details($docnum) ?? [];
        
        $oldLedgers = DB::table('stockledger')
            ->where('docnum', $docnum)
            ->get();

        $ledgerMap = [];
        foreach ($oldLedgers as $l) {
            $lArray = (array)$l;
            $lItem = $lArray['itemcode'];
            $lBatch = $lArray['batchnum'];
            
            $netQty = (float)$lArray['out_qty'] - (float)$lArray['in_qty'];
            
            if (!isset($ledgerMap[$lItem])) $ledgerMap[$lItem] = [];
            $ledgerMap[$lItem][$lBatch] = ($ledgerMap[$lItem][$lBatch] ?? 0) + $netQty;
        }

        foreach ($ledgerMap as $iCode => $batches) {
            foreach ($batches as $bNum => $qty) {
                if ($qty <= 0.0001) {
                    unset($ledgerMap[$iCode][$bNum]);
                }
            }
        }

        $result = [];
        foreach ($details as $index => $det) {
            $detArray = (array)$det;
            $itemCode = $detArray['itemcode'] ?? '';
            $batchNum = $detArray['batchnum'] ?? '';
            $qtyVal   = (float)($detArray['qty'] ?? 0);
            
            $allocMap = [];
            if (isset($ledgerMap[$itemCode]) && !empty($ledgerMap[$itemCode])) {
                $allocMap = $ledgerMap[$itemCode];
            } elseif (!empty($batchNum)) {
                $allocMap = [$batchNum => $qtyVal];
            }

            $result[] = [
                'baseline'    => $detArray['baseline'] ?? ($index + 1),
                'itemcode'    => $itemCode,
                'batchnum'    => $batchNum,
                'allocations' => $allocMap
            ];
        }

        return response()->json([
            'status' => true,
            'data'   => $result
        ]);
    }
}