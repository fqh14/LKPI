<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MLoading;
use App\Models\MPacking;
use App\Models\MWarehousemaster;
use App\Models\MCardmaster;
use App\Models\MItemmaster;
use App\Models\MItemwarehouse;
use App\Models\MItembatch;
use App\Models\MStockledger;
use App\Models\MJournal;

class Loading extends BaseTransactionController
{
    protected $model;
    protected MPacking $mPacking;
    protected MWarehousemaster $mWarehousemaster;
    protected MCardmaster $mCardmaster;
    protected MItemmaster $mItemmaster;
    protected MItemwarehouse $mItemwarehouse;
    protected MItembatch $mItembatch;
    protected MStockledger $mStockledger;
    protected MJournal $mJournal;
    
    protected bool $showImport   = false;
    protected bool $showRemark   = false;
    protected bool $showSummary  = false; 
    protected bool $showFreight  = false;
    protected bool $showDpp      = false;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model            = new MLoading();
        $this->mPacking         = new MPacking();
        $this->mCardmaster      = new MCardmaster();
        $this->mWarehousemaster = new MWarehousemaster();
        $this->mItemmaster      = new MItemmaster();
        $this->mItemwarehouse   = new MItemwarehouse();
        $this->mItembatch       = new MItembatch();
        $this->mStockledger     = new MStockledger();
        $this->mJournal         = new MJournal();
    }

    protected function getTitle(): string { return 'Loading & Ekspedisi'; }
    protected function getBaseRoute(): string { return 'loading'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }

    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'LD-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'No Loading', 'Tanggal', 'Customer', 'Jenis Ekspedisi', 'Status', 'Aksi'];
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = (array)$item;
        return [
            e($itemArray['cardname'] ?? '-'),
            e(strtoupper($itemArray['expedition_type'] ?? '-')),
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
            
            'copy_from_url_multiple'        => url('loading/getOpenList'),
            'copy_from_detail_url_multiple' => url('loading/getOpenDetail'), 
            'copy_from_label'               => 'Copy From Packing',
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
            'title'            => 'Edit ' . $this->getTitle(),
            'base_route'       => $this->getBaseRoute(),
            'action_url'       => $this->getBaseRoute() . '/saveedit/' . $id,
            'field_code'       => $fieldCode,
            'field_date'       => $fieldDate,
            'docNumber'        => $docNumber,
            'docRemark'        => $docRemark,
            'header'           => $headerArray,
            'details'          => $details,
            'show_remark'      => $this->showRemark,
            'show_summary'     => $this->showSummary,
            'show_freight'     => $this->showFreight,
            'show_dpp'         => $this->showDpp,
            'show_btndraft'    => $this->showBtndraft,
            'extra'            => $this->getExtraFields($headerArray),
            'detail_columns'   => $this->getDetailFields() ?? [],
            'message'          => Session::get('message') ?? Session::get('error_msg') ?? '',
            'batch_allocation' => true 
        ]);
    }

    protected function getCopyFromColumns(): array
    {
        return [
            [
                'name'  => 'docnum', 
                'label' => 'No. Packing', 
                'class' => 'font-weight-bold'
            ],
            [
                'name'  => 'docdate', 
                'label' => 'Tanggal'
            ],
            [
                'name'  => 'cardname', 
                'label' => 'Customer'
            ],
            [
                'name'  => 'address', 
                'label' => 'Alamat'
            ]
        ];
    }

    protected function getExtraFields($row = null): array
    {
        $expeditionType = $row['expedition_type'] ?? 'internal';

        return [
            [
                'name'          => 'expedition_type',
                'label'         => 'Jenis Ekspedisi',
                'type'          => 'select',
                'value'         => $expeditionType,
                'options'       => ['internal' => 'Internal (Armada Perusahaan)', 'external' => 'Eksternal (3PL / Kargo)'],
                'required'      => true,
            ],
            [
                'name'          => 'cardcode',
                'label'         => 'Vendor Ekspedisi',
                'type'          => 'select',
                'value'         => $row['cardcode'] ?? '',
                'options'       => $this->formatOptions($this->mCardmaster->filters([
                    'cardgroupcode =' => 'VE', 
                    'status' => 1
                ]), 'cardcode', 'cardname'),
                'attributes'    => $expeditionType === 'internal' ? 'disabled' : '', 
            ],
            [
                'name'          => 'platnum',
                'label'         => 'Plat Nomor Kendaraan (Internal)',
                'type'          => 'text',
                'value'         => $row['platnum'] ?? '',
            ],
            [
                'name'          => 'drivername',
                'label'         => 'Nama Supir (Internal)',
                'type'          => 'text',
                'value'         => $row['drivername'] ?? '',
            ],
            [
                'name'          => 'awbnum',
                'label'         => 'Nomor Resi / AWB (Eksternal)',
                'type'          => 'text',
                'value'         => $row['awbnum'] ?? '',
            ],
            [
                'name'          => 'remark',
                'label'         => 'Catatan',
                'type'          => 'textarea',
                'value'         => $row['remark'] ?? '',
                'rows'          => 2,
            ],
        ];
    }

    protected function getDetailFields(): array
    {
        return [
            [
                'name'      => 'baseentry', 
                'label'     => 'No Packing', 
                'type'      => 'text', 
                'width'     => '12%', 
                'readonly'  => true
            ],
            [
                'name'      => 'itemcode', 
                'label'     => 'Kode Item', 
                'type'      => 'text', 
                'width'     => '12%', 
                'readonly'  => true
            ],
            [
                'name'      => 'itemname', 
                'label'     => 'Nama Produk', 
                'type'      => 'text', 
                'width'     => '25%', 
                'readonly'  => true
            ],
            [
                'name'      => 'qty', 
                'label'     => 'Jumlah', 
                'type'      => 'number', 
                'class'     => 'text-end input-qty', 
                'width'     => '10%', 
                'step'      => 'any', 
                'readonly'  => true
            ],
            [
                'name'      => 'uomcode', 
                'label'     => 'Uom', 
                'type'      => 'text', 
                'width'     => '7%',
                'readonly'  => true
            ],
            [
                'name'      => 'batchnum', 
                'label'     => 'Batch Number', 
                'type'      => 'text', 
                'width'     => '14%', 
                'readonly'  => true
            ],
            [
                'name'      => 'expdate', 
                'label'     => 'Exp Date', 
                'type'      => 'text', 
                'width'     => '10%', 
                'readonly'  => true
            ],
            [
                'name'      => 'kolinum', 
                'label'     => 'Nomor Koli', 
                'type'      => 'text', 
                'class'     => 'text-info font-weight-bold', 
                'width'     => '10%', 
                'readonly'  => true
            ],
            [
                'name'      => 'baseline', 
                'label'     => '', 
                'type'      => 'hidden', 
                'width'     => '0%'
            ]
        ];
    }

    private function processLoadingInventory($db, $itemCode, $whsCode, $qty, $batchNum, $expDate, $docNum)
    {
        if (empty($itemCode) || $qty <= 0) return;

        $batchInfo = $this->mItembatch->getBatchInfo($itemCode, $whsCode, $batchNum);
        $batchInfoArray = is_object($batchInfo) ? (array)$batchInfo : $batchInfo;
        $rawExpDate = $batchInfoArray['expdate'] ?? $expDate;
        $batchExp = (empty($rawExpDate) || $rawExpDate === '0000-00-00' || $rawExpDate === 'dd/mm/yyyy') ? null : $rawExpDate;

        if (method_exists($this->mItemwarehouse, 'reduceStock')) {
            $this->mItemwarehouse->reduceStock($itemCode, $whsCode, $qty);
        }
        if (!empty($batchNum) && method_exists($this->mItembatch, 'reduceBatchStock')) {
            $this->mItembatch->reduceBatchStock($itemCode, $whsCode, null, $batchNum, $qty);
        }

        if (method_exists($this->mStockledger, 'recordEntry')) {
            $this->mStockledger->recordEntry([
                'docnum'   => $docNum, 
                'docdate'  => date('Y-m-d H:i:s'), 
                'source'   => 'LOADING OUT',
                'itemcode' => $itemCode, 
                'batchnum' => $batchNum, 
                'expdate'  => $batchExp,
                'whscode'  => $whsCode, 
                'in_qty'   => 0, 
                'out_qty'  => $qty
            ]);
        }
    }

    private function revertLoadingInventory($db, $txnNumber, $whsCode)
    {
        $oldLedgers = $this->mStockledger->getStockLedgerByDoc($txnNumber);
        if (empty($oldLedgers)) return;

        $netLedgerMap = [];
        foreach ($oldLedgers as $l) {
            $lArray = (array)$l;
            $iCode  = $lArray['itemcode'];
            $bNum   = $lArray['batchnum'] ?? '';
            $source = $lArray['source'] ?? '';
            
            $key = $iCode . '|' . $bNum;
            if (!isset($netLedgerMap[$key])) {
                $netLedgerMap[$key] = [
                    'itemcode' => $iCode, 'batchnum' => $bNum,
                    'expdate'  => $lArray['expdate'], 'qty' => 0
                ];
            }

            if ($source === 'LOADING OUT') {
                $netLedgerMap[$key]['qty'] += (float)$lArray['out_qty'];
            } elseif ($source === 'CANCEL LOADING IN') {
                $netLedgerMap[$key]['qty'] -= (float)$lArray['in_qty'];
            }
        }

        foreach ($netLedgerMap as $ledger) {
            $itemCode = $ledger['itemcode'];
            $qty      = $ledger['qty'];
            $batchNum = $ledger['batchnum'];
            $expDate  = $ledger['expdate'];
            
            if ($qty <= 0.001) continue;

            if (method_exists($this->mItemwarehouse, 'addStock')) {
                $this->mItemwarehouse->addStock($itemCode, $whsCode, $qty);
            }
            if (!empty($batchNum) && method_exists($this->mItembatch, 'addBatchStock')) {
                $this->mItembatch->addBatchStock($itemCode, $whsCode, null, $batchNum, $expDate, 0, $qty);
            }

            if (method_exists($this->mStockledger, 'recordEntry')) {
                $this->mStockledger->recordEntry([
                    'docnum'   => $txnNumber, 'docdate' => date('Y-m-d H:i:s'), 'source' => 'CANCEL LOADING OUT',
                    'itemcode' => $itemCode, 'batchnum' => $batchNum, 'expdate' => $expDate,
                    'whscode'  => $whsCode, 'in_qty' => 0, 'out_qty' => 0
                ]);
                $this->mStockledger->recordEntry([
                    'docnum'   => $txnNumber, 'docdate' => date('Y-m-d H:i:s'), 'source' => 'CANCEL LOADING IN',
                    'itemcode' => $itemCode, 'batchnum' => $batchNum, 'expdate' => $expDate,
                    'whscode'  => $whsCode, 'in_qty' => $qty, 'out_qty' => 0
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

        if (($postData['expedition_type'] ?? 'internal') === 'internal') {
            $postData['cardcode'] = null;
        }

        $headerData = $this->prepareHeaderOnCreate($postData, $status, $request);
        $headerData[$txnNumberField] = $txnNumber;
        $detailData = $this->prepareDetailsOnCreate($items, $txnNumber);

        DB::beginTransaction();

        try {
            $saveResult = $this->model->saveadd($headerData, $detailData, $txnNumberField);
            if (is_array($saveResult) && ($saveResult['status'] ?? '') === 'error') {
                throw new \Exception($saveResult['message']);
            }

            if ($status === 1) {
                $stagingWhs = 'STP'; 
                foreach ($detailData as $item) {
                    $this->processLoadingInventory(
                        DB::getFacadeRoot(), 
                        $item['itemcode'] ?? '', 
                        $stagingWhs, 
                        (float)($item['qty'] ?? 0), 
                        $item['batchnum'] ?? '', 
                        $item['expdate'] ?? null, 
                        $txnNumber
                    );
                }
                $this->mJournal->createJournal('LOADING', $txnNumber, $headerData, $detailData);
            }

            DB::commit();

            $this->flashSession(['status' => ($status === 9 ? 'draft' : 'add'), 'fa_id' => $txnNumber, 'fa_name' => $this->getTitle()]);
            return $this->ajaxResponse('success', 'Dokumen Loading berhasil disimpan.', ['redirect' => url($this->getBaseRoute())]);

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

        if (($postData['expedition_type'] ?? 'internal') === 'internal') {
            $postData['cardcode'] = null;
        }

        DB::beginTransaction();

        try {
            $oldHeader = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            if (!$oldHeaderArray) return $this->ajaxResponse('error', 'Data loading tidak ditemukan.');

            $realId    = $oldHeaderArray['id'] ?? $id ?? $txnNumber;
            $oldStatus = (int)($oldHeaderArray['status'] ?? 9);
            $stagingWhs = 'STP';

            if ($oldStatus === 1) {
                $this->revertLoadingInventory(DB::getFacadeRoot(), $txnNumber, $stagingWhs);
            }

            $headerData = $this->prepareHeaderData($txnNumber, $postData, $status);
            $detailData = $this->prepareDetailsOnCreate($items, $txnNumber);
            
            $saveResult = $this->model->saveedit($realId, $headerData, $detailData);
            if (is_array($saveResult) && isset($saveResult['status']) && $saveResult['status'] === 'error') {
                throw new \Exception($saveResult['message']);
            }

            if ($status === 1) {
                foreach ($detailData as $item) {
                    $this->processLoadingInventory(
                        DB::getFacadeRoot(), 
                        $item['itemcode'] ?? '', 
                        $stagingWhs, 
                        (float)($item['qty'] ?? 0), 
                        $item['batchnum'] ?? '', 
                        $item['expdate'] ?? null, 
                        $txnNumber
                    );
                }
                $this->mJournal->updateJournal('LOADING', $txnNumber, $headerData, $detailData);
            } elseif ($oldStatus === 1 && $status !== 1) {
                $this->mJournal->updateStatusByReference($txnNumber, 0);
            }

            DB::commit();

            $this->flashSession(['status' => ($status === 9 ? 'draft' : 'edit'), 'fa_id' => $txnNumber, 'fa_name' => $this->getTitle()]);
            return $this->ajaxResponse('success', 'Dokumen Loading berhasil diperbarui.', ['redirect' => url($this->getBaseRoute())]);

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

            $realId     = $headerArray['id'] ?? $txnNumber;
            $docNum     = $headerArray['docnum'] ?? $txnNumber;
            $stagingWhs = 'STP';
            $details    = $this->model->get_details($docNum);
            
            if (empty($details)) return $this->ajaxResponse('error', 'Detail barang kosong.');

            $updateData = ['status' => 1, 'approveddate' => date('Y-m-d H:i:s'), 'approvedby' => (int) (Session::get('usercode') ?? 1)];
            $this->model->update($realId, $updateData);

            foreach ($details as $item) {
                $itemArray = (array)$item;
                $this->processLoadingInventory(
                    DB::getFacadeRoot(), 
                    $itemArray['itemcode'] ?? '', 
                    $stagingWhs, 
                    (float)($itemArray['qty'] ?? 0), 
                    $itemArray['batchnum'] ?? '', 
                    $itemArray['expdate'] ?? null, 
                    $docNum
                );
            }

            $this->mJournal->createJournal('LOADING', $docNum, $headerArray, $details);

            DB::commit();

            $this->flashSession(['status' => 'active', 'fa_id' => $docNum, 'fa_name' => $this->getTitle()]);
            return $this->ajaxResponse('success', 'Dokumen Loading berhasil diaktifkan.', ['redirect' => url($this->getBaseRoute())]);

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

            $stagingWhs = 'STP';
            if ($oldStatus === 1) {
                $this->revertLoadingInventory(DB::getFacadeRoot(), $txnNumber, $stagingWhs);
            }

            $userCode = (int) (Session::get('usercode') ?? 1);
            $this->model->update($oldHeaderArray['id'] ?? $id, [
                'status'       => 0,
                'canceleddate' => date('Y-m-d H:i:s'),
                'canceledby'   => $userCode
            ]);

            $this->mJournal->updateStatusByReference($txnNumber, 0);

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

    protected function prepareHeaderData(string $txnNumber, array $postData, int $status = 1): array
    {
        $expeditionType = $postData['expedition_type'] ?? 'internal';
        return [
            'docnum'          => $txnNumber,
            'expedition_type' => $expeditionType,
            'cardcode'        => $expeditionType === 'internal' ? null : ($postData['cardcode'] ?? ''),
            'platnum'         => $postData['platnum'] ?? '',
            'drivername'      => $postData['drivername'] ?? '',
            'awbnum'          => $postData['awbnum'] ?? '',
            'remark'          => $postData['remark'] ?? '',
            'status'          => $status,
            'updateddate'     => date('Y-m-d H:i:s'),
            'updatedby'       => (int) (Session::get('usercode') ?? 1),
        ];
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request = null): array
    {
        $header = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        $expeditionType = $postData['expedition_type'] ?? 'internal';

        return array_merge($header, [           
            'docdate'         => !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s'),
            'expedition_type' => $expeditionType,
            'cardcode'        => $expeditionType === 'internal' ? null : ($postData['cardcode'] ?? ''),
            'platnum'         => $postData['platnum'] ?? '',
            'drivername'      => $postData['drivername'] ?? '',
            'awbnum'          => $postData['awbnum'] ?? '',
            'remark'          => $postData['remark'] ?? '',
            'status'          => $status,
            'createddate'     => date('Y-m-d H:i:s'),
            'createdby'       => (int) (Session::get('usercode') ?? 1),
        ]); 
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

    public function getOpenList(Request $request): JsonResponse
    {
        if (!$request->ajax()) return response()->json([], 404);

        $listData = $this->model->getOpenPackingList();

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
        if (empty($docnums)) return response()->json(['status' => false, 'message' => 'Tidak ada dokumen Packing yang dipilih.']);
        if (!is_array($docnums)) $docnums = [$docnums];

        $processedDetails = [];
        $headerResponse   = [];

        foreach ($docnums as $docnum) {
            $packHeader = $this->mPacking->where('docnum', $docnum)->first();
            $packHeaderArray = is_object($packHeader) ? (array)$packHeader : $packHeader;
            $packDetails = $this->mPacking->get_details($docnum);

            if (!$packHeaderArray) continue;

            if (empty($headerResponse)) {
                $headerResponse = [
                    'address'  => $packHeaderArray['address'] ?? ''
                ];
            }

            if (!empty($packDetails)) {
                foreach ($packDetails as $row) {
                    $rowArray = (array)$row;
                    $baseLine = $rowArray['linenum'] ?? $rowArray['baseline'] ?? 1;
                    $reqQty   = (float)($rowArray['qty'] ?? 0);
                    
                    $loadedQty   = $this->model->getAlreadyLoadedQty($docnum, (int)$baseLine);
                    $remainingQty = $reqQty - $loadedQty;
                    
                    if ($remainingQty > 0) {
                        $processedDetails[] = [
                            'baseentry'  => $docnum,
                            'baseline'   => $baseLine,
                            'itemcode'   => $rowArray['itemcode'],
                            'itemname'   => $rowArray['itemname'] ?? '',
                            'qty'        => $remainingQty, 
                            'uomcode'    => $rowArray['uomcode'] ?? '',
                            'batchnum'   => $rowArray['batchnum'] ?? '',
                            'expdate'    => $rowArray['expdate'] ?? null,
                            'kolinum'    => $rowArray['kolinum'] ?? '',
                        ];
                    }
                }
            }
        }

        if (empty($processedDetails)) {
            return response()->json(['status' => false, 'message' => 'Semua item/koli pada dokumen Packing ini sudah dimuat sepenuhnya (tidak ada sisa).']);
        }

        return response()->json(['status' => true, 'header' => $headerResponse, 'details' => $processedDetails]);
    }

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        $expeditionType = $postData['expedition_type'] ?? 'internal';

        if ($expeditionType === 'external') {
            if (empty($postData['cardcode'])) return 'Vendor Ekspedisi wajib dipilih untuk jenis ekspedisi eksternal.';
            if (empty($postData['awbnum'])) return 'Nomor Resi / AWB wajib diisi untuk ekspedisi eksternal.';
        } else {
            if (empty($postData['platnum'])) return 'Plat nomor kendaraan wajib diisi untuk ekspedisi internal.';
            if (empty($postData['drivername'])) return 'Nama supir wajib diisi untuk ekspedisi internal.';
        }
        
        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) $items = json_decode($items, true) ?? [];
        if (empty($items)) return 'Detail barang atau koli yang dimuat tidak boleh kosong!';

        foreach ($items as $idx => $item) {
            $rowNum    = $idx + 1;
            $qty       = (float)($item['qty'] ?? 0);
            $baseEntry = $item['baseentry'] ?? '';
            $baseLine  = $item['baseline'] ?? 1;

            if (empty($item['itemcode'])) return "Item produk pada baris ke-{$rowNum} tidak valid.";
            if ($qty <= 0) return "Qty pada baris ke-{$rowNum} harus lebih dari 0.";
            if (empty($item['kolinum'])) return "Nomor koli pada baris ke-{$rowNum} wajib ada.";

            if (!empty($baseEntry)) {
                $packDetail = DB::table('packingdetail')
                    ->where('docnum', $baseEntry)
                    ->where('linenum', $baseLine)
                    ->first();
                $packDetailArray = is_object($packDetail) ? (array)$packDetail : $packDetail;
                
                $totalPackQty = (float)($packDetailArray['qty'] ?? 0);
                $alreadyLoaded = $this->model->getAlreadyLoadedQty($baseEntry, (int)$baseLine, $currentDocNum);                
                $remainingQty = $totalPackQty - $alreadyLoaded;

                if ($qty > $remainingQty) {
                    return "Qty pada baris ke-{$rowNum} melebihi sisa kuantitas Packing ({$remainingQty}).";
                }
            }
        }
        return null;
    }
}