<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MPacking;
use App\Models\MDeliveryorder;
use App\Models\MWarehousemaster;
use App\Models\MCardmaster;
use App\Models\MItemmaster;
use App\Models\MItemwarehouse;
use App\Models\MItembatch;
use App\Models\MStockledger;

class Packing extends BaseTransactionController
{
    protected $model;
    protected MDeliveryorder $mDeliveryorder;
    protected MWarehousemaster $mWarehousemaster;
    protected MCardmaster $mCardmaster;
    protected MItemmaster $mItemmaster;
    protected MItemwarehouse $mItemwarehouse;
    protected MItembatch $mItembatch;
    protected MStockledger $mStockledger;
    
    protected bool $showImport   = false;
    protected bool $showRemark   = false;
    protected bool $showSummary  = false; 
    protected bool $showFreight  = false;
    protected bool $showDpp      = false;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model            = new MPacking();
        $this->mDeliveryorder   = new MDeliveryorder();
        $this->mCardmaster      = new MCardmaster();
        $this->mWarehousemaster = new MWarehousemaster();
        $this->mItemmaster      = new MItemmaster();
        $this->mItemwarehouse   = new MItemwarehouse();
        $this->mItembatch       = new MItembatch();
        $this->mStockledger     = new MStockledger();
    }

    protected function getTitle(): string { return 'Packing (Koli)'; }
    protected function getBaseRoute(): string { return 'packing'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }

    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'PK-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'No Packing', 'Tanggal', 'Customer', 'Alamat', 'Status', 'Aksi'];
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = is_object($item) ? (array)$item : $item;
        return [
            e($itemArray['cardname'] ?? '-'),
            e($itemArray['address'] ?? '-'),
        ]; 
    }

    public function add()
    {
        if ($guard = $this->guardModuleAccess('isadd')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        $data = [
            'title'                  => $this->getTitle(),
            'base_route'             => $this->getBaseRoute(),
            'field_code'             => $this->getTxnNumberField(),
            'field_date'             => $this->getDateField(),
            'current_number'         => $this->generateTxnNumber(),
            'extra'                  => $this->getExtraFields(),
            'detail_columns'         => $this->getDetailFields(),
            'action_url'             => $this->getBaseRoute() . '/saveadd',
            'show_remark'            => $this->showRemark,
            'show_summary'           => $this->showSummary,
            'show_dpp'               => $this->showDpp,
            'show_btndraft'          => $this->showBtndraft,
            'message'                => Session::get('message') ?? '',
            
            'copy_from_url_multiple'        => url('packing/getOpenList'),
            'copy_from_detail_url_multiple' => url('packing/getOpenDetail'), 
            'copy_from_label'               => 'Copy From DO',
            'copy_from_columns'             => $this->getCopyFromColumns(),
            'batch_allocation'              => true,
        ];

        return view('basetransaction.add', $data);
    }

    public function edit(Request $request, $id)
    {
        if ($guard = $this->guardModuleAccess('isedit')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        $header = $this->model->find($id);
        if (!$header) {
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Data transaksi tidak ditemukan.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') ? $header->toArray() : (array)$header;
        $fieldCode = $this->getTxnNumberField();
        $fieldDate = $this->getDateField();
        $docNumber = $headerArray[$fieldCode] ?? $id;
        $docRemark = $headerArray['remark'] ?? '';

        $status = (string)($headerArray['status'] ?? '');
        if (in_array($status, ['0', '2'], true)) { 
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Transaksi yang sudah selesai/batal tidak dapat diubah.');
        }

        $details = $this->model->get_details($docNumber) ?? [];

        return view('basetransaction.edit', [
            'title'                         => 'Edit ' . $this->getTitle(),
            'base_route'                    => $this->getBaseRoute(),
            'action_url'                    => $this->getBaseRoute() . '/saveedit/' . $id,
            'field_code'                    => $fieldCode,
            'field_date'                    => $fieldDate,
            'docNumber'                     => $docNumber,
            'docRemark'                     => $docRemark,
            'header'                        => $headerArray,
            'details'                       => $details,
            'show_remark'                   => $this->showRemark,
            'show_summary'                  => $this->showSummary,
            'show_freight'                  => $this->showFreight,
            'show_dpp'                      => $this->showDpp,
            'show_btndraft'                 => $this->showBtndraft,
            'extra'                         => $this->getExtraFields($headerArray),
            'detail_columns'                => $this->getDetailFields() ?? [],
            'message'                       => Session::get('message') ?? Session::get('error_msg') ?? '',
            
            'copy_from_url_multiple'        => url('packing/getOpenList'),
            'copy_from_detail_url_multiple' => url('packing/getOpenDetail'), 
            'copy_from_label'               => 'Copy From DO',
            'copy_from_columns'             => $this->getCopyFromColumns(),
            'batch_allocation'              => true,
        ]);
    }

    protected function getCopyFromColumns(): array
    {
        return [
            ['name' => 'docnum', 'label' => 'No. DO', 'class' => 'font-weight-bold'],
            ['name' => 'docdate', 'label' => 'Tanggal'],
            ['name' => 'cardname', 'label' => 'Customer'],
            ['name' => 'remark', 'label' => 'Catatan']
        ];
    }

    protected function getExtraFields($row = null): array
    {
        return [
            [
                'name'          => 'fromwhscode',
                'label'         => 'Gudang Asal',
                'type'          => 'select',
                'value'         => $row['fromwhscode'] ?? 'VSLS',
                'options'       => $this->formatOptions($this->mWarehousemaster->filters(['status' => 1]), 'whscode', 'whsname'),
                'required'      => true,
                'readonly'      => true
            ],
            [
                'name'          => 'towhscode',
                'label'         => 'Gudang Tujuan (Staging)',
                'type'          => 'select',
                'value'         => $row['towhscode'] ?? 'STP',
                'options'       => $this->formatOptions($this->mWarehousemaster->filters(['status' => 1]), 'whscode', 'whsname'),
                'required'      => true,
                'readonly'      => true
            ],
            [
                'name'          => 'cardcode',
                'label'         => 'Customer',
                'type'          => 'select',
                'value'         => $row['cardcode'] ?? '',
                'options'       => $this->formatOptions($this->mCardmaster->filters(['cardgroupcode =' => 'VC', 'status' => 1]), 'cardcode', 'cardname'),
                'required'      => true,
            ],
            [
                'name'          => 'address',
                'label'         => 'Tujuan Pengiriman',
                'type'          => 'textarea',
                'value'         => $row['address'] ?? '',
                'rows'          => 2,
                'required'      => true,
                'readonly'      => true 
            ],
            [
                'name'          => 'remark',
                'label'         => 'Catatan',
                'type'          => 'textarea',
                'value'         => $row['remark'] ?? '',
                'rows'          => 2,
                'required'      => true,
                'readonly'      => true 
            ],
        ];
    }

    protected function getDetailFields(): array
    {
        return [
            ['name' => 'baseentry', 'label' => 'No Referensi', 'type' => 'text', 'width' => '10%', 'readonly' => true],
            ['name' => 'itemcode', 'label' => 'Kode Item', 'type' => 'text', 'width' => '10%', 'readonly' => true],
            ['name' => 'itemname', 'label' => 'Nama Produk', 'type' => 'text', 'width' => '25%', 'readonly' => true],
            ['name' => 'qty', 'label' => 'Jumlah', 'type' => 'number', 'class' => 'text-end input-qty', 'width' => '10%', 'step' => 'any', 'required' => true],
            ['name' => 'uomcode', 'label' => 'Uom', 'type' => 'text', 'width' => '7%', 'readonly' => true],
            ['name' => 'batchnum', 'label' => 'Batch Alokasi', 'type' => 'batch_with_btn', 'class' => 'bg-light text-primary font-weight-bold', 'placeholder' => 'Hasil Alokasi', 'width' => '15%', 'readonly' => true, 'required' => true],
            ['name' => 'expdate', 'label' => 'Exp Date', 'type' => 'text', 'width' => '10%', 'readonly' => true],
            ['name' => 'kolinum', 'label' => 'Nomor Koli', 'type' => 'text', 'class' => 'text-info', 'placeholder' => 'Contoh: Koli 1', 'width' => '10%', 'required' => true],
            ['name' => 'baseline', 'label' => '', 'type' => 'hidden', 'width' => '0%']
        ];
    }

    private function processPackingInventory($db, $itemCode, $sourceWhsCode, $destWhsCode, $qty, $batchStr, $expDate, $docNum, ?string $batchAllocationsJson = null)
    {
        if (empty($itemCode) || $qty <= 0) return;

        $allocations = [];
        if (!empty($batchAllocationsJson) && $batchAllocationsJson !== 'null') {
            $allocations = json_decode($batchAllocationsJson, true);
        }

        if (!empty($allocations) && is_array($allocations)) {
            foreach ($allocations as $batchNum => $takeQty) {
                $takeQty = (float)$takeQty;
                if ($takeQty <= 0) continue;

                $batchInfo = $this->mItembatch->getBatchInfo($itemCode, $sourceWhsCode, $batchNum);
                $batchInfoArray = is_object($batchInfo) ? (array)$batchInfo : $batchInfo;

                $rawExpDate = $batchInfoArray['expdate'] ?? $expDate;
                $batchExp   = (empty($rawExpDate) || $rawExpDate === '0000-00-00' || $rawExpDate === 'dd/mm/yyyy') ? null : $rawExpDate;

                if (method_exists($this->mItemwarehouse, 'reduceCommitted')) {
                    $this->mItemwarehouse->reduceCommitted($itemCode, $sourceWhsCode, $takeQty);
                }
                if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                    $this->mItemwarehouse->reduceStock($itemCode, $sourceWhsCode, $takeQty);
                }
                if (method_exists($this->mItembatch, 'reduceBatchStock')) {
                    $this->mItembatch->reduceBatchStock($itemCode, $sourceWhsCode, null, $batchNum, $takeQty);
                }

                if (method_exists($this->mItemwarehouse, 'addStock')) {
                    $this->mItemwarehouse->addStock($itemCode, $destWhsCode, $takeQty);
                }
                if (method_exists($this->mItembatch, 'addBatchStock')) {
                    $this->mItembatch->addBatchStock($itemCode, $destWhsCode, null, $batchNum, $batchExp, 0, $takeQty);
                }

                if (method_exists($this->mStockledger, 'recordEntry')) {
                    $this->mStockledger->recordEntry([
                        'docnum'   => $docNum, 'docdate' => date('Y-m-d H:i:s'), 'source' => 'PACKING OUT',
                        'itemcode' => $itemCode, 'batchnum' => $batchNum, 'expdate' => $batchExp,
                        'whscode'  => $sourceWhsCode, 'in_qty' => 0, 'out_qty' => $takeQty
                    ]);
                    $this->mStockledger->recordEntry([
                        'docnum'   => $docNum, 'docdate' => date('Y-m-d H:i:s'), 'source' => 'PACKING IN',
                        'itemcode' => $itemCode, 'batchnum' => $batchNum, 'expdate' => $batchExp,
                        'whscode'  => $destWhsCode, 'in_qty' => $takeQty, 'out_qty' => 0
                    ]);
                }
            }
        } else {
            if (method_exists($this->mItemwarehouse, 'reduceCommitted')) {
                $this->mItemwarehouse->reduceCommitted($itemCode, $sourceWhsCode, $qty);
            }
            if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                $this->mItemwarehouse->reduceStock($itemCode, $sourceWhsCode, $qty);
            }
            if (!empty($batchStr) && method_exists($this->mItembatch, 'reduceBatchStock')) {
                $this->mItembatch->reduceBatchStock($itemCode, $sourceWhsCode, null, $batchStr, $qty);
            }

            if (method_exists($this->mItemwarehouse, 'addStock')) {
                $this->mItemwarehouse->addStock($itemCode, $destWhsCode, $qty);
            }
            if (!empty($batchStr) && method_exists($this->mItembatch, 'addBatchStock')) {
                $this->mItembatch->addBatchStock($itemCode, $destWhsCode, null, $batchStr, $expDate, 0, $qty);
            }

            if (method_exists($this->mStockledger, 'recordEntry')) {
                $this->mStockledger->recordEntry([
                    'docnum'   => $docNum, 'docdate' => date('Y-m-d H:i:s'), 'source' => 'PACKING OUT',
                    'itemcode' => $itemCode, 'batchnum' => $batchStr, 'expdate' => $expDate,
                    'whscode'  => $sourceWhsCode, 'in_qty' => 0, 'out_qty' => $qty
                ]);
                $this->mStockledger->recordEntry([
                    'docnum'   => $docNum, 'docdate' => date('Y-m-d H:i:s'), 'source' => 'PACKING IN',
                    'itemcode' => $itemCode, 'batchnum' => $batchStr, 'expdate' => $expDate,
                    'whscode'  => $destWhsCode, 'in_qty' => $qty, 'out_qty' => 0
                ]);
            }
        }
    }

    private function revertPackingInventory($db, $txnNumber, $sourceWhsCode, $destWhsCode)
    {
        $oldLedgers = $this->mStockledger->getStockLedgerByDoc($txnNumber);
        if (empty($oldLedgers)) return;

        $netLedgerMap = [];
        foreach ($oldLedgers as $l) {
            $lArray = is_object($l) ? (array)$l : $l;
            $iCode  = $lArray['itemcode'];
            $wCode  = $lArray['whscode'];
            $bNum   = $lArray['batchnum'] ?? '';
            $source = $lArray['source'] ?? '';
            
            $key = $iCode . '|' . $wCode . '|' . $bNum;
            if (!isset($netLedgerMap[$key])) {
                $netLedgerMap[$key] = [
                    'itemcode' => $iCode, 'whscode' => $wCode, 'batchnum' => $bNum,
                    'expdate'  => $lArray['expdate'], 'qty' => 0
                ];
            }

            if ($source === 'PACKING OUT') {
                $netLedgerMap[$key]['qty'] += (float)$lArray['out_qty'];
            } elseif ($source === 'CANCEL PACKING IN') {
                $netLedgerMap[$key]['qty'] -= (float)$lArray['in_qty'];
            }
        }

        foreach ($netLedgerMap as $ledger) {
            $itemCode = $ledger['itemcode'];
            $qty      = $ledger['qty'];
            $batchNum = $ledger['batchnum'];
            $expDate  = $ledger['expdate'];
            
            if ($qty <= 0.001) continue;

            if (method_exists($this->mItemwarehouse, 'addCommitted')) {
                $this->mItemwarehouse->addCommitted($itemCode, $sourceWhsCode, $qty);
            }
            if (method_exists($this->mItemwarehouse, 'addStock')) {
                $this->mItemwarehouse->addStock($itemCode, $sourceWhsCode, $qty);
            }
            if (!empty($batchNum) && method_exists($this->mItembatch, 'addBatchStock')) {
                $this->mItembatch->addBatchStock($itemCode, $sourceWhsCode, null, $batchNum, $expDate, 0, $qty);
            }

            if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                $this->mItemwarehouse->reduceStock($itemCode, $destWhsCode, $qty);
            }
            if (!empty($batchNum) && method_exists($this->mItembatch, 'reduceBatchStock')) {
                $this->mItembatch->reduceBatchStock($itemCode, $destWhsCode, null, $batchNum, $qty);
            }

            if (method_exists($this->mStockledger, 'recordEntry')) {
                $this->mStockledger->recordEntry([
                    'docnum'   => $txnNumber, 'docdate' => date('Y-m-d H:i:s'), 'source' => 'CANCEL PACKING OUT',
                    'itemcode' => $itemCode, 'batchnum' => $batchNum, 'expdate' => $expDate,
                    'whscode'  => $destWhsCode, 'in_qty' => 0, 'out_qty' => $qty
                ]);
                $this->mStockledger->recordEntry([
                    'docnum'   => $txnNumber, 'docdate' => date('Y-m-d H:i:s'), 'source' => 'CANCEL PACKING IN',
                    'itemcode' => $itemCode, 'batchnum' => $batchNum, 'expdate' => $expDate,
                    'whscode'  => $sourceWhsCode, 'in_qty' => $qty, 'out_qty' => 0
                ]);
            }
        }
    }

    public function saveadd(Request $request): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) return response()->json([], 405);

        $postData = $request->post();
        if ($valError = $this->validateTransaction($postData)) return $this->ajaxResponse('error', $valError);

        $txnNumberField = $this->getTxnNumberField();
        $txnNumber      = $postData[$txnNumberField] ?? $this->generateTxnNumber();
        $status         = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;
        
        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) $items = json_decode($items, true) ?? [];
        unset($postData['items'], $postData['details'], $postData['btn_submit']);

        $headerData = $this->prepareHeaderOnCreate($postData, $status, [], $request);
        $headerData[$txnNumberField] = $txnNumber;
        $detailData = $this->prepareDetailsOnCreate($items, $txnNumber);

        DB::beginTransaction();

        try {
            $saveResult = $this->model->saveadd($headerData, $detailData, $txnNumberField);
            if (is_array($saveResult) && ($saveResult['status'] ?? '') === 'error') {
                throw new \Exception($saveResult['message']);
            }

            if ($status === 1) {
                $sourceWhs = $headerData['fromwhscode'] ?? 'VSLS';
                $destWhs   = $headerData['towhscode'] ?? 'STP';

                foreach ($detailData as $index => $item) {
                    $itemCode       = $item['itemcode'] ?? '';
                    $qty            = (float)($item['qty'] ?? 0);
                    $batchNum       = $item['batchnum'] ?? '';
                    $expDate        = !empty($item['expdate']) ? $item['expdate'] : null;
                    $batchAllocJson = $items[$index]['batch_allocations'] ?? null;

                    $this->processPackingInventory(DB::getFacadeRoot(), $itemCode, $sourceWhs, $destWhs, $qty, $batchNum, $expDate, $txnNumber, $batchAllocJson);
                }
            }

            DB::commit();

            $this->flashSession(['status' => ($status === 9 ? 'draft' : 'add'), 'fa_id' => $txnNumber, 'fa_name' => $this->getTitle()]);
            return $this->ajaxResponse('success', 'Dokumen Packing berhasil disimpan.', ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function saveedit(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) return response()->json([], 405);

        $postData  = $request->post();
        $txnNumber = $id ?? $postData['docnum'] ?? null;
        if (empty($txnNumber)) return $this->ajaxResponse('error', 'Nomor transaksi tidak valid.');

        if ($valError = $this->validateTransaction($postData, $txnNumber)) return $this->ajaxResponse('error', $valError);

        $status = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;
        $items  = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) $items = json_decode($items, true) ?? [];
        unset($postData['items'], $postData['details'], $postData['btn_submit']);

        DB::beginTransaction();

        try {
            $oldHeader = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            if (!$oldHeaderArray) return $this->ajaxResponse('error', 'Data packing tidak ditemukan.');

            $realId     = $oldHeaderArray['id'] ?? $id ?? $txnNumber;
            $oldStatus  = (int)($oldHeaderArray['status'] ?? 9);
            $oldFromWhs = $oldHeaderArray['fromwhscode'] ?? 'VSLS';
            $oldToWhs   = $oldHeaderArray['towhscode'] ?? 'STP';

            if ($oldStatus === 1) {
                $this->revertPackingInventory(DB::getFacadeRoot(), $txnNumber, $oldFromWhs, $oldToWhs);
            }

            $headerData = $this->prepareHeaderData($txnNumber, $postData, $status);
            $detailData = $this->prepareDetailsOnCreate($items, $txnNumber);
            
            $saveResult = $this->model->saveedit($realId, $headerData, $detailData);
            if (is_array($saveResult) && isset($saveResult['status']) && $saveResult['status'] === 'error') {
                throw new \Exception($saveResult['message']);
            }

            if ($status === 1) {
                $newFromWhs = $headerData['fromwhscode'] ?? 'VSLS';
                $newToWhs   = $headerData['towhscode'] ?? 'STP';

                foreach ($detailData as $index => $item) {
                    $itemCode       = $item['itemcode'] ?? '';
                    $qty            = (float)($item['qty'] ?? 0);
                    $batchNum       = $item['batchnum'] ?? '';
                    $expDate        = !empty($item['expdate']) ? $item['expdate'] : null;
                    $batchAllocJson = $items[$index]['batch_allocations'] ?? null;

                    if (!empty($batchAllocJson) && $batchAllocJson !== 'null') {
                        $allocs = json_decode($batchAllocJson, true);
                        if (is_array($allocs) && count($allocs) > 0) {
                            $sumAlloc = array_sum($allocs);
                            if (abs($sumAlloc - $qty) > 0.001) {
                                reset($allocs);
                                $firstBatch = key($allocs);
                                $allocs = [$firstBatch => $qty]; 
                                $batchAllocJson = json_encode($allocs);
                            }
                        }
                    } elseif (!empty($batchNum)) {
                        $batchAllocJson = json_encode([$batchNum => $qty]);
                    }

                    $this->processPackingInventory(DB::getFacadeRoot(), $itemCode, $newFromWhs, $newToWhs, $qty, $batchNum, $expDate, $txnNumber, $batchAllocJson);
                }
            }

            DB::commit();

            $this->flashSession(['status' => ($status === 9 ? 'draft' : 'edit'), 'fa_id' => $txnNumber, 'fa_name' => $this->getTitle()]);
            return $this->ajaxResponse('success', 'Dokumen Packing berhasil diperbarui.', ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function saveactive(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) return response()->json([], 405);

        $txnNumber = $id ?? $request->post('docnum') ?? $request->post('id') ?? null;
        if (empty($txnNumber)) return $this->ajaxResponse('error', 'Nomor transaksi tidak valid.');

        DB::beginTransaction();

        try {
            $header = $this->model->find($txnNumber);
            if (!$header) $header = $this->model->where('docnum', $txnNumber)->first();
            $headerArray = is_object($header) ? (array)$header : $header;
            if (!$headerArray) return $this->ajaxResponse('error', 'Data tidak ditemukan.');
            if ((int)($headerArray['status'] ?? 0) === 1) return $this->ajaxResponse('error', 'Dokumen sudah Aktif.');

            $realId    = $headerArray['id'] ?? $txnNumber;
            $docNum    = $headerArray['docnum'] ?? $txnNumber;
            $sourceWhs = $headerArray['fromwhscode'] ?? 'VSLS';
            $destWhs   = $headerArray['towhscode'] ?? 'STP';
            $details   = $this->model->get_details($docNum);
            
            if (empty($details)) return $this->ajaxResponse('error', 'Detail barang kosong.');

            $updateData = ['status' => 1, 'approveddate' => date('Y-m-d H:i:s'), 'approvedby' => (int) (Session::get('usercode') ?? 1)];
            
            if (method_exists($this->model, 'saveactive')) {
                $this->model->saveactive($realId, $updateData);
            } else {
                $this->model->update($realId, $updateData);
            }

            foreach ($details as $item) {
                $itemArray = is_object($item) ? (array)$item : $item;
                $itemCode = $itemArray['itemcode'] ?? '';
                $qty      = (float)($itemArray['qty'] ?? 0);
                $batchNum = $itemArray['batchnum'] ?? '';
                $expDate  = !empty($itemArray['expdate']) ? $itemArray['expdate'] : null;

                $this->processPackingInventory(DB::getFacadeRoot(), $itemCode, $sourceWhs, $destWhs, $qty, $batchNum, $expDate, $docNum);
            }

            DB::commit();

            $this->flashSession(['status' => 'active', 'fa_id' => $docNum, 'fa_name' => $this->getTitle()]);
            return $this->ajaxResponse('success', 'Dokumen Packing berhasil diaktifkan.', ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Sistem Error: ' . $e->getMessage());
        }
    }

    public function savecancel(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) return response()->json([], 405);

        $txnNumber = $id ?? $request->post('docnum') ?? null;
        if (empty($txnNumber)) return $this->ajaxResponse('error', 'Nomor transaksi tidak valid.');

        DB::beginTransaction();

        try {
            $oldHeader = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            if (!$oldHeaderArray) return $this->ajaxResponse('error', 'Data tidak ditemukan.');
            
            $oldStatus = (int)($oldHeaderArray['status'] ?? 0);
            if ($oldStatus === 0) return $this->ajaxResponse('error', 'Sudah dibatalkan sebelumnya.');

            $oldFromWhs = $oldHeaderArray['fromwhscode'] ?? 'VSLS';
            $oldToWhs   = $oldHeaderArray['towhscode'] ?? 'STP';

            if ($oldStatus === 1) {
                $this->revertPackingInventory(DB::getFacadeRoot(), $txnNumber, $oldFromWhs, $oldToWhs);
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

            DB::commit();

            $this->flashSession(['status' => 'savecancel', 'fa_id' => $txnNumber, 'fa_name' => $this->getTitle()]);
            
            $alertMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                          . 'Nomor transaksi <strong>' . e($txnNumber) . '</strong> berhasil dibatalkan (Cancel).'
                          . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                          . '</div>';

            return $this->ajaxResponse('success', $alertMessage, ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Error: ' . $e->getMessage());
        }
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, array $items = [], Request $request = null): array
    {
        $header = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        return array_merge($header, [           
            'docdate'     => !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s'),
            'cardcode'    => $postData['cardcode'] ?? '',
            'address'     => $postData['address'] ?? '',
            'fromwhscode' => $postData['fromwhscode'] ?? 'VSLS',
            'towhscode'   => $postData['towhscode'] ?? 'STP',
            'status'      => $status,
            'remark'      => $postData['remark'] ?? '',
            'createddate' => date('Y-m-d H:i:s'),
            'createdby'   => (int) (Session::get('usercode') ?? 1),
            'approveddate'=> $status === 1 ? date('Y-m-d H:i:s') : null,
            'approvedby'  => $status === 1 ? (int) (Session::get('usercode') ?? 1) : null,
        ]); 
    }

    protected function prepareHeaderData(string $txnNumber, array $postData, int $status = 1, array $items = []): array
    {
        return [
            'docnum'      => $txnNumber,
            'cardcode'    => $postData['cardcode'] ?? '',
            'address'     => $postData['address'] ?? '',
            'fromwhscode' => $postData['fromwhscode'] ?? 'VSLS',
            'towhscode'   => $postData['towhscode'] ?? 'STP',
            'remark'      => $postData['remark'] ?? '',
            'status'      => $status,
            'updateddate' => date('Y-m-d H:i:s'),
            'updatedby'   => (int) (Session::get('usercode') ?? 1),
            'approveddate'=> $status === 1 ? date('Y-m-d H:i:s') : null,
            'approvedby'  => $status === 1 ? (int) (Session::get('usercode') ?? 1) : null,
        ];
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber): array
    {
        $detailsData = [];
        foreach ($items as $index => $item) {
            $detailsData[] = [
                'docnum'    => $txnNumber,
                'linenum'   => $index + 1,
                'baseentry' => $item['baseentry'] ?? '',
                'baseline'  => $item['baseline'] ?? 1,
                'itemcode'  => $item['itemcode'] ?? '',
                'uomcode'   => $item['uomcode'] ?? '',
                'qty'       => (float)($item['qty'] ?? 0),
                'batchnum'  => $item['batchnum'] ?? '',
                'expdate'   => !empty($item['expdate']) ? $item['expdate'] : null,
                'kolinum'   => $item['kolinum'] ?? '',
            ];
        }
        return $detailsData;
    }

    public function getBatchAllocation(Request $request): JsonResponse
    {
        if (!$request->ajax()) return response()->json([], 404);

        $itemCode = $request->post('itemcode');
        $whsCode  = $request->post('whscode');
        $docNum   = $request->post('docnum'); 

        if (empty($itemCode) || empty($whsCode)) {
            return response()->json(['status' => false, 'message' => 'Parameter item atau gudang tidak lengkap.']);
        }

        $rawBatches = method_exists($this->mItembatch, 'getStockByFEFO') ? $this->mItembatch->getStockByFEFO($itemCode, $whsCode) : [];

        $batchesMap = [];
        foreach ($rawBatches as $b) {
            $bArray = is_object($b) ? (array)$b : $b;
            $bNum = $bArray['batchnum'];
            if (!isset($batchesMap[$bNum])) {
                $batchesMap[$bNum] = [
                    'batchnum' => $bNum,
                    'expdate'  => $bArray['expdate'] ?? '',
                    'onhand'   => (float)($bArray['onhand'] ?? 0),
                    'unitcost' => (float)($bArray['unitcost'] ?? 0)
                ];
            } else {
                $batchesMap[$bNum]['onhand'] += (float)($bArray['onhand'] ?? 0);
            }
        }

        if (!empty($docNum)) {
            $oldLedgers = $this->mStockledger->getStockLedgerByDoc($docNum);
            $netLedger = [];
            
            foreach ($oldLedgers as $l) {
                $lArray = is_object($l) ? (array)$l : $l;
                if ($lArray['itemcode'] === $itemCode && $lArray['whscode'] === $whsCode) {
                    $lBatch = $lArray['batchnum'];
                    if (!isset($netLedger[$lBatch])) {
                        $netLedger[$lBatch] = ['qty' => 0, 'expdate' => $lArray['expdate']];
                    }
                    
                    if (($lArray['source'] ?? '') === 'PACKING OUT') {
                        $netLedger[$lBatch]['qty'] += (float)$lArray['out_qty'];
                    } elseif (($lArray['source'] ?? '') === 'CANCEL PACKING IN') {
                        $netLedger[$lBatch]['qty'] -= (float)$lArray['in_qty'];
                    }
                }
            }

            foreach ($netLedger as $lBatch => $data) {
                $lQty = $data['qty'];
                if ($lQty > 0.001) { 
                    if (isset($batchesMap[$lBatch])) {
                        $batchesMap[$lBatch]['onhand'] += $lQty; 
                    } else {
                        $batchesMap[$lBatch] = [
                            'batchnum' => $lBatch,
                            'expdate'  => $data['expdate'],
                            'onhand'   => $lQty,
                            'unitcost' => 0
                        ];
                    }
                }
            }
        }

        $batches = array_values($batchesMap);

        foreach ($batches as &$b) {
            if (empty($b['expdate']) || $b['expdate'] === '0000-00-00' || $b['expdate'] === 'dd/mm/yyyy') {
                $b['expdate'] = '2045-08-17';
            }
        }
        unset($b);

        return response()->json(['status' => true, 'data' => $batches]);
    }

    public function getEditBatchData(Request $request, $docnum = null): JsonResponse
    {
        if (!$request->ajax() || empty($docnum)) return response()->json([], 404);

        $details = $this->model->get_details($docnum) ?? [];
        $oldLedgers = $this->mStockledger->getStockLedgerByDoc($docnum);

        $ledgerMap = [];
        foreach ($oldLedgers as $l) {
            $lArray = is_object($l) ? (array)$l : $l;
            $lItem = $lArray['itemcode'];
            $lBatch = $lArray['batchnum'];
            
            if (!isset($ledgerMap[$lItem])) $ledgerMap[$lItem] = [];
            if (!isset($ledgerMap[$lItem][$lBatch])) $ledgerMap[$lItem][$lBatch] = 0;

            if (($lArray['source'] ?? '') === 'PACKING OUT') {
                $ledgerMap[$lItem][$lBatch] += (float)$lArray['out_qty'];
            } elseif (($lArray['source'] ?? '') === 'CANCEL PACKING IN') {
                $ledgerMap[$lItem][$lBatch] -= (float)$lArray['in_qty'];
            }
        }

        foreach ($ledgerMap as $iCode => $batches) {
            foreach ($batches as $bNum => $qty) {
                if ($qty <= 0.001) {
                    unset($ledgerMap[$iCode][$bNum]);
                }
            }
        }

        $result = [];
        foreach ($details as $index => $det) {
            $detArray = is_object($det) ? (array)$det : $det;
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

        return response()->json(['status' => true, 'data' => $result]);
    }

    public function getOpenList(Request $request): JsonResponse
    {
        if (!$request->ajax()) return response()->json([], 404);

        $cardcode = $request->query('cardcode');
        $listData = $this->mDeliveryorder->getOpen($cardcode);

        $html = view('basetransaction.popcopyfrommultiplerows', [
            'listData'          => $listData,
            'copy_from_columns' => $this->getCopyFromColumns(),
            'is_multi_select'   => true 
        ])->render();

        return response()->json(['status' => true, 'html' => $html]);
    }

    public function getOpenDetail(Request $request): JsonResponse
    {
        if (!$request->ajax()) return response()->json([], 404);

        $docnums = $request->post('docnum') ?? $request->post('id') ?? $request->post('ids'); 
        if (empty($docnums)) return response()->json(['status' => false, 'message' => 'Tidak ada DO yang dipilih.']);
        if (!is_array($docnums)) $docnums = [$docnums];

        $processedDetails = [];
        $headerResponse   = [];

        foreach ($docnums as $docnum) {
            $doData = $this->mDeliveryorder->getWithDetails($docnum); 
            if (!$doData || empty($doData['header'])) continue;

            $header = is_object($doData['header']) ? (array)$doData['header'] : $doData['header'];
            if (empty($headerResponse)) {
                $headerResponse = [
                    'cardcode' => $header['cardcode'] ?? '',
                    'whscode'  => $header['whscode'] ?? '',
                    'address'  => $header['address'] ?? 'Alamat Default Customer'
                ];
            }

            if (!empty($doData['details'])) {
                foreach ($doData['details'] as $row) {
                    $rowArray = is_object($row) ? (array)$row : $row;
                    $baseLine = $rowArray['linenum'] ?? $rowArray['baseline'] ?? 1;
                    $reqQty   = (float)($rowArray['qty'] ?? 0);
                    
                    $packedQty    = $this->model->getAlreadyPackedQty($docnum, $baseLine);
                    $remainingQty = $reqQty - $packedQty;
                    
                    if ($remainingQty > 0) {
                        $processedDetails[] = [
                            'baseentry'  => $docnum,
                            'baseline'   => $baseLine,
                            'itemcode'   => $rowArray['itemcode'],
                            'itemname'   => $rowArray['itemname'] ?? '',
                            'qty'        => $remainingQty, 
                            'qtyrequest' => $remainingQty, 
                            'uomcode'    => $rowArray['uomcode'] ?? '',
                            'batchnum'   => $rowArray['batchnum'] ?? '',
                            'expdate'    => $rowArray['expdate'] ?? null,
                            'kolinum'    => '',
                        ];
                    }
                }
            }
        }

        if (empty($processedDetails)) {
            return response()->json(['status' => false, 'message' => 'Semua item pada DO ini sudah dipacking sepenuhnya (tidak ada sisa).']);
        }

        return response()->json(['status' => true, 'header' => $headerResponse, 'details' => $processedDetails]);
    }

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        if (empty($postData['cardcode'])) return 'Customer wajib dipilih.';
        if (empty($postData['address'])) return 'Alamat tujuan wajib diisi.';
        
        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) $items = json_decode($items, true) ?? [];
        if (empty($items)) return 'Detail barang yang di-packing tidak boleh kosong!';

        foreach ($items as $idx => $item) {
            $rowNum    = $idx + 1;
            $qty       = (float)($item['qty'] ?? 0);
            $baseEntry = $item['baseentry'] ?? '';
            $baseLine  = $item['baseline'] ?? 1;

            if (empty($item['kolinum'])) return "Nomor Koli / Box pada baris ke-{$rowNum} wajib diisi.";
            if (empty($item['itemcode'])) return "Item produk pada baris ke-{$rowNum} tidak valid.";
            if ($qty <= 0) return "Qty pada baris ke-{$rowNum} harus lebih dari 0.";
            if (empty($item['batchnum'])) return "Kode batch pada bahan baris ke-{$rowNum} wajib dialokasikan.";

            $batchAllocJson = $items[$idx]['batch_allocations'] ?? null;
            if (!empty($batchAllocJson) && $batchAllocJson !== 'null') {
                $allocs = json_decode($batchAllocJson, true);
                if (is_array($allocs)) {
                    $sumAlloc = array_sum(array_map('floatval', $allocs));
                    if (abs($sumAlloc - $qty) > 0.001) {
                        return "Total alokasi batch (" . $sumAlloc . ") tidak sesuai dengan Qty Packing (" . $qty . ") pada baris ke-{$rowNum}.";
                    }
                }
            }

            if (!empty($baseEntry)) {
                $doRow = $this->mDeliveryorder->getDetailByLine($baseEntry, $baseLine);
                $doRowArray = is_object($doRow) ? (array)$doRow : $doRow;
                if ($doRowArray) {
                    $reqQty = (float)($doRowArray['qty'] ?? 0);
                    $alreadyPacked = $this->model->getAlreadyPackedQty($baseEntry, $baseLine, $currentDocNum);
                    $remainingQty  = $reqQty - $alreadyPacked;
    
                    if ($qty > ($remainingQty + 0.001)) {
                        return "Qty barang {$item['itemcode']} pada baris ke-{$rowNum} ({$qty}) melampaui sisa Qty DO yang belum di-packing (Sisa diizinkan: {$remainingQty}).";
                    }
                }
            }
        }
        return null;
    }
}