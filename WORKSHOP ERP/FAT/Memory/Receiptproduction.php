<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MReceiptproduction;
use App\Models\MWorkorder;
use App\Models\MBom;
use App\Models\MWarehousemaster;
use App\Models\MItemmaster;
use App\Models\MItemwarehouse;
use App\Models\MItembatch;
use App\Models\MStockledger;
use App\Models\MJournal;

class Receiptproduction extends BaseTransactionController
{
    protected $model;
    protected MItemmaster $mItemmaster;
    protected MWarehousemaster $mWarehousemaster;
    protected MWorkorder $mWorkorder;
    protected MBom $mBom;
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

    public function __construct()
    {
        $this->model            = new MReceiptproduction();
        $this->mItemmaster      = new MItemmaster();
        $this->mWarehousemaster = new MWarehousemaster();
        $this->mWorkorder       = new MWorkorder();
        $this->mBom             = new MBom();
        $this->mItemwarehouse   = new MItemwarehouse();
        $this->mItembatch       = new MItembatch();
        $this->mStockledger     = new MStockledger();
        $this->mJournal         = new MJournal();
    }

    protected function getTitle(): string { return 'Receipt from Production'; }
    protected function getBaseRoute(): string { return 'receiptproduction'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'RP-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor', 'Tanggal', 'No. Work Order', 'Gudang Tujuan', 'Catatan', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = is_object($item) ? (array)$item : $item;
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
                'label' => 'Dokumen',
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

            'copy_from_url'        => url('receiptproduction/getOpenList'),
            'copy_from_detail_url' => url('receiptproduction/getOpenDetail'),
            'copy_from_label'      => 'Copy From Work Order',
            'copy_from_columns'    => $this->getCopyFromColumns(),
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
                'label'    => 'Gudang Tujuan',
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
                'width'         => '10%',
                'readonly'      => true, 
            ],
            [
                'name'          => 'itemname',
                'label'         => 'Item Barang Jadi',
                'type'          => 'text',
                'width'         => '30%',
                'readonly'      => true, 
            ],
            [
                'name'          => 'uomcode',
                'label'         => 'Uom',
                'type'          => 'text',
                'width'         => '10%',
                'readonly'      => true,
            ],
            [
                'name'          => 'qty',
                'label'         => 'Qty Hasil',
                'type'          => 'number',
                'class'         => 'text-end input-qty',
                'required'      => true,
                'min'           => 0,
                'max'           => 9999999,
                'width'         => '15%',
                'step'          => '0.01'
            ],
            [
                'name'          => 'batchnum',
                'label'         => 'Kode Batch',
                'type'          => 'text',
                'width'         => '10%',
                'readonly'      => true,
            ],
            [
                'name'          => 'expdate',
                'label'         => 'Tanggal Kadaluarsa',
                'type'          => 'date',
                'width'         => '10%',
                'readonly'      => true,
            ],
            [
                'name'          => 'unitcost',
                'label'         => 'Unit Cost',
                'type'          => 'number',
                'min'           => 0,
                'max'           => 9999999,
                'width'         => '10%',
                'step'          => '0.01'
            ],
        ];
    }

    protected function updateWorkOrderCompletedQty(string $baseentry, string $itemcode, float $qtyChange, string $action = 'add'): void
    {
        if (empty($baseentry) || empty($itemcode)) {
            return;
        }

        $builder = DB::table('wodetail')
            ->where('docnum', $baseentry)
            ->where('itemcode', $itemcode)
            ->where('itemtype', 'OUTPUT');

        if ($action === 'add') {
            $builder->update(['qtycompleted' => DB::raw('qtycompleted + ' . $qtyChange)]);
        } else {
            $builder->update(['qtycompleted' => DB::raw('GREATEST(0, qtycompleted - ' . $qtyChange . ')')]);
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

            $baseentry = $headerData['baseentry'] ?? null;

            if ($status === 1) {
                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'RP');
                } elseif (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('RP', $txnNumber, $headerData, $detailData);
                }

                $defaultWhs = $headerData['whscode'] ?? '';
                $docDate    = $headerData['docdate'] ?? date('Y-m-d H:i:s');

                foreach ($detailData as $item) {
                    $itemCode  = $item['itemcode'] ?? '';
                    $qty       = (float)($item['qty'] ?? 0);
                    $whsCode   = $item['whscode'] ?? $defaultWhs;
                    $areaCode  = $item['areacode'] ?? '';
                    $batchNum  = $item['batchnum'] ?? '';
                    $expDate   = $item['expdate'] ?? null;
                    $unitCost  = (float)($item['unitcost'] ?? 0);

                    if (!empty($itemCode) && $qty > 0) {
                        if (method_exists($this->mStockledger, 'recordEntry')) {
                            $this->mStockledger->recordEntry([
                                'docdate'  => $docDate,
                                'docnum'   => $txnNumber,
                                'source'   => 'Receipt Production',
                                'itemcode' => $itemCode,
                                'whscode'  => $whsCode,
                                'areacode' => $areaCode,
                                'batchnum' => $batchNum,
                                'expdate'  => $expDate,
                                'in_qty'   => $qty,
                                'out_qty'  => 0.0000,
                                'unitcost' => $unitCost 
                            ]);
                        }

                        if (method_exists($this->mItemwarehouse, 'addStock')) {
                            $this->mItemwarehouse->addStock($itemCode, $whsCode, $qty);
                        }
                        if (method_exists($this->mItembatch, 'addBatchStock')) {
                            $this->mItembatch->addBatchStock($itemCode, $whsCode, $areaCode, $batchNum, $expDate, $unitCost, $qty);
                        }

                        if (!empty($baseentry)) {
                            $this->updateWorkOrderCompletedQty($baseentry, $itemCode, $qty, 'add');
                        }
                    }
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => ($status === 9 ? 'draft' : 'add'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            $message = $status === 9 ? 'Draft Receipt from Production berhasil disimpan.' : 'Receipt from Production berhasil disimpan, jurnal tercatat, stok gudang bertambah, dan Work Order diperbarui.';

            return $this->ajaxResponse('success', $message, [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
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
            if (!$oldHeader) {
                return $this->ajaxResponse('error', 'Data transaksi Receipt from Production tidak ditemukan.');
            }
            $oldHeaderArray = is_object($oldHeader) && method_exists($oldHeader, 'toArray') ? $oldHeader->toArray() : (array)$oldHeader;
            $oldDetails   = $this->model->getWithDetails($txnNumber)['details'] ?? [];
            $oldStatus    = (int)($oldHeaderArray['status'] ?? 9); 
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;

            if ($oldStatus === 1 && !empty($oldDetails)) {
                $oldLedgers = DB::table('stockledger')->where('docnum', $txnNumber)->get()->toArray();
                
                $netLedgerMap = [];
                foreach ($oldLedgers as $l) {
                    $lArray = is_object($l) ? (array)$l : $l;
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
                    $netLedgerMap[$key]['net_qty'] += ((float)$lArray['in_qty'] - (float)$lArray['out_qty']);
                }

                foreach ($netLedgerMap as $ledger) {
                    $netQty = $ledger['net_qty'];
                    if ($netQty <= 0.0001) continue; 

                    if (method_exists($this->mStockledger, 'recordEntry')) {
                        $this->mStockledger->recordEntry([
                            'docdate'  => date('Y-m-d H:i:s'),
                            'docnum'   => $txnNumber,
                            'source'   => 'Edit Receipt Production', 
                            'itemcode' => $ledger['itemcode'],
                            'whscode'  => $ledger['whscode'],
                            'batchnum' => $ledger['batchnum'],
                            'expdate'  => $ledger['expdate'],
                            'areacode' => $ledger['areacode'],
                            'in_qty'   => 0.0000, 
                            'out_qty'  => $netQty,
                            'unitcost' => $ledger['unitcost']
                        ]);
                    }

                    if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                        $this->mItemwarehouse->reduceStock($ledger['itemcode'], $ledger['whscode'], $netQty);
                    }
                    if (method_exists($this->mItembatch, 'reduceBatchStock')) {
                        $this->mItembatch->reduceBatchStock($ledger['itemcode'], $ledger['whscode'], $ledger['areacode'], $ledger['batchnum'], $netQty);
                    }
                    if (!empty($oldBaseEntry)) {
                        $this->updateWorkOrderCompletedQty($oldBaseEntry, $ledger['itemcode'], $netQty, 'sub');
                    }
                }
            }

            $items = $postData['items'] ?? $postData['details'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?? [];
            }

            $headerData   = $this->prepareHeaderData($txnNumber, $postData, $status, $oldHeaderArray);
            $detailData   = $this->prepareDetailsData($txnNumber, $postData); 
            $newbaseentry = $headerData['baseentry'] ?? $oldBaseEntry;
            $defaultWhs   = $headerData['whscode'] ?? '';
            $rawDocDate   = $headerData['docdate'] ?? date('Y-m-d');
            $docDate      = (strlen($rawDocDate) === 10) ? $rawDocDate . ' ' . date('H:i:s') : $rawDocDate;

            $saveResult = $this->model->saveedit($id, $headerData, $detailData);
            if (($saveResult['status'] ?? '') === 'error') {
                throw new \Exception($saveResult['message'] ?? 'Gagal memperbarui data.');
            }

            if ($status === 1) {
                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'RP');
                } else {
                    if (method_exists($this->mJournal, 'updateJournal')) {
                        $this->mJournal->updateJournal('RP', $txnNumber, $headerData, $detailData);
                    } elseif (method_exists($this->mJournal, 'createJournal')) {
                        $this->mJournal->createJournal('RP', $txnNumber, $headerData, $detailData);
                    }
                }

                foreach ($detailData as $item) {
                    $itemCode  = $item['itemcode'] ?? '';
                    $qty       = (float)($item['qty'] ?? 0);
                    $whsCode   = $item['whscode'] ?? $defaultWhs;
                    $areaCode  = $item['areacode'] ?? '';
                    $batchNum  = $item['batchnum'] ?? '';
                    $expDate   = $item['expdate'] ?? null;
                    $unitCost  = (float)($item['unitcost'] ?? 0);
                    if (!empty($itemCode) && $qty > 0) {
                        if (method_exists($this->mStockledger, 'recordEntry')) {
                            $this->mStockledger->recordEntry([
                                'docdate'  => $docDate,
                                'docnum'   => $txnNumber,
                                'source'   => 'Receipt Production',
                                'itemcode' => $itemCode,
                                'whscode'  => $whsCode,
                                'areacode' => $areaCode,
                                'batchnum' => $batchNum,
                                'expdate'  => $expDate,
                                'in_qty'   => $qty,
                                'out_qty'  => 0.0000,
                                'unitcost' => $unitCost
                            ]);
                        }
                        if (method_exists($this->mItemwarehouse, 'addStock')) {
                            $this->mItemwarehouse->addStock($itemCode, $whsCode, $qty);
                        }
                        if (method_exists($this->mItembatch, 'addBatchStock')) {
                            $this->mItembatch->addBatchStock($itemCode, $whsCode, $areaCode, $batchNum, $expDate, $unitCost, $qty);
                        }
                        if (!empty($newbaseentry)) {
                            $this->updateWorkOrderCompletedQty($newbaseentry, $itemCode, $qty, 'add');
                        }
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

            return $this->ajaxResponse('success', 'Receipt from Production berhasil diperbarui.', [
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
            $headerArray = is_object($header) && method_exists($header, 'toArray') ? $header->toArray() : (array)$header;

            if (!$headerArray) {
                return $this->ajaxResponse('error', 'Data transaksi Receipt from Production tidak ditemukan.');
            }

            if ((int)($headerArray['status'] ?? 0) === 1) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Active.');
            }

            $realId     = $headerArray['id'] ?? $txnNumber;
            $docNum     = $headerArray['docnum'] ?? $txnNumber;
            $defaultWhs = $headerArray['whscode'] ?? '';
            $docDate    = $headerArray['docdate'] ?? date('Y-m-d H:i:s');
            $baseentry  = $headerArray['baseentry'] ?? null;

            $details = $this->model->getWithDetails($docNum)['details'] ?? [];
            if (empty($details)) {
                return $this->ajaxResponse('error', 'Detail item transaksi kosong, tidak dapat diaktifkan.');
            }

            $updateData = [
                'status'       => 1,
                'approveddate' => date('Y-m-d H:i:s'),
                'approvedby'   => (int) (Session::get('usercode') ?? 1),
            ];

            $this->model->update($realId, $updateData);

            if (method_exists($this, 'handleJournal')) {
                $this->handleJournal($docNum, 1, $headerArray, $details, 'RP');
            } else {
                if (method_exists($this->mJournal, 'deleteByReference')) {
                    $this->mJournal->deleteByReference($docNum);
                }
                if (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('RP', $docNum, $headerArray, $details);
                }
            }

            $existingLedgers = DB::table('stockledger')
                ->where(['docnum' => $docNum, 'source' => 'Receipt Production'])
                ->get()
                ->toArray();

            $matchedLedgerIds = [];

            foreach ($details as $item) {
                $itemArray = is_object($item) ? (array)$item : $item;
                $itemCode = $itemArray['itemcode'] ?? '';
                $qty      = (float)($itemArray['qty'] ?? 0);
                $whsCode  = $itemArray['whscode'] ?? $defaultWhs;
                $areaCode = $itemArray['areacode'] ?? '';
                $batchNum = $itemArray['batchnum'] ?? '';
                $expDate  = $itemArray['expdate'] ?? null;

                $unitCost = (float)($itemArray['unitcost'] ?? 0);

                if (!empty($itemCode) && $qty > 0) {
                    $foundId = null;
                    foreach ($existingLedgers as $ledger) {
                        $ledgerArray = is_object($ledger) ? (array)$ledger : $ledger;
                        if (!in_array($ledgerArray['id'], $matchedLedgerIds) && 
                            $ledgerArray['itemcode'] === $itemCode && 
                            (($ledgerArray['batchnum'] ?? '') === $batchNum)) {
                            $foundId = $ledgerArray['id'];
                            $matchedLedgerIds[] = $foundId;
                            break;
                        }
                    }

                    if ($foundId) {
                        DB::table('stockledger')
                            ->where('id', $foundId)
                            ->update([
                                'in_qty'   => $qty,
                                'out_qty'  => 0.0000,
                                'unitcost' => $unitCost
                            ]);
                    } else {
                        if (method_exists($this->mStockledger, 'recordEntry')) {
                            $this->mStockledger->recordEntry([
                                'docdate'  => $docDate,
                                'docnum'   => $docNum,
                                'source'   => 'Receipt Production',
                                'itemcode' => $itemCode,
                                'whscode'  => $whsCode,
                                'areacode' => $areaCode,
                                'batchnum' => $batchNum,
                                'expdate'  => $expDate,
                                'in_qty'   => $qty,
                                'out_qty'  => 0.0000,
                                'unitcost' => $unitCost
                            ]);
                        }
                    }

                    if (method_exists($this->mItemwarehouse, 'addStock')) {
                        $this->mItemwarehouse->addStock($itemCode, $whsCode, $qty);
                    }
                    if (method_exists($this->mItembatch, 'addBatchStock')) {
                        $this->mItembatch->addBatchStock($itemCode, $whsCode, $areaCode, $batchNum, $expDate, $unitCost, $qty);
                    }

                    if (!empty($baseentry)) {
                        $this->updateWorkOrderCompletedQty($baseentry, $itemCode, $qty, 'add');
                    }
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'active',
                'fa_id'   => $docNum,
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'Receipt from Production berhasil diaktifkan, jurnal tercatat, stok bertambah, dan Work Order diperbarui.', [
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
            if (!$oldHeader) {
                return $this->ajaxResponse('error', 'Data transaksi Receipt from Production tidak ditemukan.');
            }
            $oldHeaderArray = is_object($oldHeader) && method_exists($oldHeader, 'toArray') ? $oldHeader->toArray() : (array)$oldHeader;

            $oldStatus = (int)($oldHeaderArray['status'] ?? 0);
            if ($oldStatus === 0) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Canceled.');
            }

            $oldDetails   = $this->model->getWithDetails($txnNumber)['details'] ?? [];
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;

            if ($oldStatus === 1 && !empty($oldDetails)) {
                $oldLedgers = DB::table('stockledger')->where('docnum', $txnNumber)->get()->toArray();
                
                $netLedgerMap = [];
                foreach ($oldLedgers as $l) {
                    $lArray = is_object($l) ? (array)$l : $l;
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
                    $netLedgerMap[$key]['net_qty'] += ((float)$lArray['in_qty'] - (float)$lArray['out_qty']);
                }
                    
                foreach ($netLedgerMap as $ledger) {
                    $netQty = $ledger['net_qty'];
                    if ($netQty <= 0.0001) continue; 

                    if (method_exists($this->mStockledger, 'recordEntry')) {
                        $this->mStockledger->recordEntry([
                            'docdate'  => date('Y-m-d H:i:s'),
                            'docnum'   => $txnNumber,
                            'source'   => 'Cancel Receipt Prod.',
                            'itemcode' => $ledger['itemcode'],
                            'whscode'  => $ledger['whscode'],
                            'batchnum' => $ledger['batchnum'],
                            'expdate'  => $ledger['expdate'],
                            'areacode' => $ledger['areacode'],
                            'in_qty'   => 0.0000, 
                            'out_qty'  => $netQty,
                            'unitcost' => $ledger['unitcost']
                        ]);
                    }

                    if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                        $this->mItemwarehouse->reduceStock($ledger['itemcode'], $ledger['whscode'], $netQty);
                    }
                    if (method_exists($this->mItembatch, 'reduceBatchStock')) {
                        $this->mItembatch->reduceBatchStock($ledger['itemcode'], $ledger['whscode'], $ledger['areacode'], $ledger['batchnum'], $netQty);
                    }
                    if (!empty($oldBaseEntry)) {
                        $this->updateWorkOrderCompletedQty($oldBaseEntry, $ledger['itemcode'], $netQty, 'sub');
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
                          . 'Nomor transaksi <strong>' . e($txnNumber) . '</strong> berhasil dibatalkan (Cancel) dan stok disesuaikan.'
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
            'whscode'     => $postData['whscode'] ?? '',
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

    protected function prepareHeaderData(string $txnNumber, array $postData, int $status = 1, ?array $oldHeader = null): array
    {
        $docDate = $postData['docdate'] ?? $oldHeader['docdate'] ?? date('Y-m-d H:i:s');

        if (strlen($docDate) === 10 && !empty($oldHeader['docdate']) && strlen($oldHeader['docdate']) > 10) {
            $timePart = substr($oldHeader['docdate'], 10);
            $docDate  = $docDate . $timePart;
        }

        $data = [
            'docnum'      => $txnNumber,
            'baseentry'   => !empty($postData['baseentry']) ? $postData['baseentry'] : ($oldHeader['baseentry'] ?? null),
            'docdate'     => $docDate,
            'whscode'     => !empty($postData['whscode']) ? $postData['whscode'] : ($oldHeader['whscode'] ?? ''),
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
            $detailsData[] = [
                'docnum'   => $txnNumber,
                'linenum'  => $index + 1,
                'baseline' => $item['baseline'] ?? $item['linenum'] ?? ($index + 1),
                'itemcode' => $item['itemcode'] ?? '',
                'uomcode'  => $item['uomcode'] ?? '',
                'qty'      => (float)($item['qty'] ?? 0),
                'batchnum' => $item['batchnum'] ?? '',
                'expdate'  => !empty($item['expdate']) ? $item['expdate'] : null,
                'unitcost' => (float)($item['unitcost'] ?? 0),
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
        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        if (empty($items)) {
            return 'Detail barang jadi tidak boleh kosong!';
        }

        $woDocNum   = $postData['baseentry'] ?? '';
        $woDetails  = [];
        $bomDetails = [];

        if (!empty($woDocNum)) {
            $woData = method_exists($this->mWorkorder, 'getWithDetails') 
                    ? $this->mWorkorder->getWithDetails($woDocNum) 
                    : null;
            $woHeader  = $woData['header'] ?? [];
            $woDetails = $woData['details'] ?? [];

            $bomDocNum = $woHeader['baseentry'] ?? ''; 
            if (!empty($bomDocNum)) {
                $bomData = method_exists($this->mBom, 'getWithDetails')
                         ? $this->mBom->getWithDetails($bomDocNum)
                         : null;
                $bomDetails = $bomData['details'] ?? [];
            }
        }

        $oldDetails = [];
        if (!empty($currentDocNum)) {
            $oldDetails = $this->model->getWithDetails($currentDocNum)['details'] ?? [];
        }

        foreach ($items as $index => $item) {
            $rowNum   = $index + 1;
            $itemCode = $item['itemcode'] ?? '';
            $inputQty = (float)($item['qty'] ?? 0);

            if (empty($itemCode)) {
                return "Item barang jadi pada baris ke-{$rowNum} belum dipilih.";
            }
            if ($inputQty <= 0) {
                return "Jumlah (Qty) penerimaan pada baris ke-{$rowNum} harus lebih besar dari 0.";
            }
            if (empty($item['batchnum'])) {
                return "Kode batch pada baris ke-{$rowNum} tidak boleh kosong.";
            }

            if (!empty($woDetails)) {
                $woItem = null;
                foreach ($woDetails as $wd) {
                    $wdArray = is_object($wd) ? (array)$wd : $wd;
                    if ($wdArray['itemcode'] === $itemCode && ($wdArray['itemtype'] ?? '') === 'OUTPUT') {
                        $woItem = $wdArray;
                        break;
                    }
                }

                if ($woItem) {
                    $qtyTarget    = (float)($woItem['qty'] ?? 0);
                    $qtyCompleted = (float)($woItem['qtycompleted'] ?? 0);
                    
                    if (!empty($oldDetails)) {
                        foreach ($oldDetails as $od) {
                            $odArray = is_object($od) ? (array)$od : $od;
                            if ($odArray['itemcode'] === $itemCode) {
                                $qtyCompleted -= (float)($odArray['qty'] ?? 0);
                            }
                        }
                    }

                    $cumulativeQty = $qtyCompleted + $inputQty;

                    $toleransi = 0;
                    if (!empty($bomDetails)) {
                        foreach ($bomDetails as $bd) {
                            $bdArray = is_object($bd) ? (array)$bd : $bd;
                            if ($bdArray['itemcode'] === $itemCode && ($bdArray['itemtype'] ?? '') === 'OUTPUT') {
                                $toleransi = (float)($bdArray['toleransi'] ?? 0);
                                break;
                            }
                        }
                    }

                    $maxQty = $qtyTarget + ($qtyTarget * $toleransi / 100);
                    $minQty = $qtyTarget - ($qtyTarget * $toleransi / 100);

                    if ($cumulativeQty > $maxQty) {
                        return "Total qty penerimaan kumulatif untuk item {$itemCode} ({$cumulativeQty}) MELEBIHI batas atas toleransi. Target WO: {$qtyTarget}, Max Allowable: {$maxQty} (+{$toleransi}%).";
                    }
                    
                    if ($cumulativeQty < $minQty) {
                        return "Total qty penerimaan kumulatif untuk item {$itemCode} ({$cumulativeQty}) KURANG DARI batas bawah toleransi. Target WO: {$qtyTarget}, Min Allowable: {$minQty} (-{$toleransi}%).";
                    }
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

        $listData = $this->mWorkorder->getOpenReceipt();

        $html = view('basetransaction.popcopyfromrows', [
            'listData'          => $listData,
            'copy_from_columns' => $this->getCopyFromColumns(),
        ])->render();

        return response()->json([
            'status' => true, 
            'html' => $html
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

        $docDate = date('Y-m-d'); 

        $processedDetails = [];
        if (!empty($woData['details'])) {
            foreach ($woData['details'] as $row) {
                $rowArray = is_object($row) ? (array)$row : $row;
                if (($rowArray['itemtype'] ?? '') !== 'OUTPUT') {
                    continue; 
                }

                $qtyTotal  = (float)($rowArray['qty'] ?? 0);
                $qtyDone   = (float)($rowArray['qtycompleted'] ?? 0);
                $qtyNeeded = $qtyTotal - $qtyDone;

                if ($qtyNeeded <= 0) continue; 
                
                $itemCode = $rowArray['itemcode'] ?? '';
                
                $toleransi = 0;
                foreach ($bomDetails as $bd) {
                    $bdArray = is_object($bd) ? (array)$bd : $bd;
                    if ($bdArray['itemcode'] === $itemCode && ($bdArray['itemtype'] ?? '') === 'OUTPUT') {
                        $toleransi = (float)($bdArray['toleransi'] ?? 0);
                        break;
                    }
                }

                $maxAllowableQty = $qtyNeeded + ($qtyNeeded * $toleransi / 100);

                $itemMaster = $this->mItemmaster->where('itemcode', $itemCode)->first();
                $itemMasterArray = is_object($itemMaster) ? (array)$itemMaster : $itemMaster;
                $monthED    = (int)($itemMasterArray['monthED'] ?? 0);
                $expDate    = date('Y-m-d', strtotime("+{$monthED} months", strtotime($docDate)));
                
                $batchNum = $this->model->generateBatchNumber($itemCode, $docDate);

                $processedDetails[] = [
                    'baseline'    => $rowArray['linenum'] ?? 1, 
                    'itemcode'    => $itemCode,
                    'itemname'    => $rowArray['itemname'] ?? '',
                    'uomcode'     => $rowArray['uomcode'] ?? '',
                    'qty'         => $qtyNeeded,        
                    'qtyrequest'  => $maxAllowableQty,  
                    'batchnum'    => $batchNum,   
                    'expdate'     => $expDate, 
                    'unitcost'    => 0,  
                ];
            }
        }

        if (empty($processedDetails)) {
            return response()->json([
                'status'  => false, 
                'message' => 'Semua item Output pada Work Order ini sudah selesai diterima.'
            ]);
        }

        return response()->json([
            'status'  =>  true,
            'header'  => $header,
            'details' => $processedDetails
        ]);
    }
}