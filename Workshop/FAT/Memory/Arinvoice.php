<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MArinvoice;
use App\Models\MDeliveryOrder;
use App\Models\MWarehousemaster;
use App\Models\MItemmaster;
use App\Models\MCardmaster;
use App\Models\MSalesorder;
use App\Models\MCurrency;
use App\Models\MTax;
use App\Models\MTermin;
use App\Models\MAccount;
use App\Models\MItemgroup;
use App\Models\MJournal;

class Arinvoice extends BaseTransactionController
{
    protected $model;    
    protected MDeliveryOrder $mDeliveryOrder;
    protected MWarehousemaster $mWarehousemaster;
    protected MItemmaster $mItemmaster;
    protected MCardmaster $mCardmaster;
    protected MSalesorder $mSalesorder;
    protected MCurrency $mCurrency;
    protected MTax $mTax;
    protected MTermin $mTermin;
    protected MAccount $mAccount;
    protected MItemgroup $mItemgroup;
    protected MJournal $mJournal;
    
    protected bool $showImport   = false;
    protected bool $showRemark   = true;
    protected bool $showSummary  = true;
    protected bool $showFreight  = true;
    protected bool $showDpp      = true;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model            = new MArinvoice();
        $this->mDeliveryOrder   = new MDeliveryOrder();
        $this->mWarehousemaster = new MWarehousemaster();
        $this->mItemmaster      = new MItemmaster();
        $this->mCardmaster      = new MCardmaster();
        $this->mSalesorder      = new MSalesorder();
        $this->mCurrency        = new MCurrency();
        $this->mTax             = new MTax();
        $this->mTermin          = new MTermin();
        $this->mAccount         = new MAccount();
        $this->mItemgroup       = new MItemgroup();
        $this->mJournal         = new MJournal();
    }

    protected function getTitle(): string { return 'AR Invoice'; }
    protected function getBaseRoute(): string { return 'arinvoice'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'AR-' . date('ymdHis'); 
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
                'name'  => 'cardname',
                'label' => 'Customer'
            ],
            [
                'name'   => 'grandtotal',
                'label'  => 'Total',
                'class'  => 'text-end',
                'format' => 'number' 
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
            
            'copy_from_url'        => url('arinvoice/getOpenList'),
            'copy_from_detail_url' => url('arinvoice/getOpenDetail'),
            'copy_from_label'      => 'Copy From',
            'copy_from_columns'    => $this->getCopyFromColumns(),
        ];

        return view('basetransaction.add', $data);
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor', 'Tanggal', 'Nomor Ref', 'Customer', 'Total', 'Diskon', 'Tax', 'Freight', 'Grand Total', 'Status', 'Aksi'];
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = (array)$item;
        return [
            e($itemArray['baseentry'] ?? '-'),
            e($itemArray['cardname'] ?? '-'),
            number_format((float)($itemArray['dppamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['discountamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['taxamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['freighttotal'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['grandtotal'] ?? 0), 2, ',', '.'),
        ];
    }

    protected function getExtraFields($row = null): array
    {
        return [
            [
                'name'     => 'baseentry',
                'label'    => 'No. Referensi',
                'type'     => 'text',
                'value'    => $row['baseentry'] ?? '',
                'readonly' => true
            ],
            [
                'name'     => 'whscode',
                'label'    => 'Gudang',
                'type'     => 'text',
                'value'    => $row['whscode'] ?? '',
                'width'    => '10%',
                'readonly' => true,
            ],
            [
                'name'    => 'cardcode',
                'label'   => 'Customer',
                'type'    => 'select',
                'value'   => $row['cardcode'] ?? '',
                'options' => $this->formatOptions($this->mCardmaster->filters([
                    'cardgroupcode' => 'VC',
                    'status'        => 1
                ]), 'cardcode', 'cardname'),
            ],
            [
                'name'    => 'currcode',
                'label'   => 'Mata Uang',
                'type'    => 'select',
                'value'   => $row['currcode'] ?? 'IDR',
                'options' => $this->formatOptions($this->mCurrency->filters([
                    'status' => 1
                ]), 'currcode', 'currname'),
            ],
            [
                'name'    => 'taxclude',
                'label'   => 'Tipe Pajak',
                'type'    => 'select',
                'value'   => $row['taxclude'] ?? 0,
                'options' => [0 => 'Exclude', 1 => 'Include']
            ],
            [
                'name'        => 'taxcode',
                'label'       => 'Kode Pajak Global',
                'type'        => 'select',
                'value'       => $row['taxcode'] ?? '',
                'options'     => $this->formatOptions($this->mTax->filters([
                    'status' => 1
                ]), 'taxcode', 'taxname'),
                'placeholder' => 'Pilih Pajak'
            ],
            [
                'name'    => 'termcode',
                'label'   => 'Syarat Pembayaran',
                'type'    => 'select',
                'value'   => $row['termcode'] ?? '',
                'options' => $this->formatOptions($this->mTermin->filters([
                    'status' => 1
                ]), 'termcode', 'termname'),
            ],
            [
                'name'  => 'freighttotal',
                'label' => 'Biaya Kirim',
                'type'  => 'number',
                'value' => $row['freighttotal'] ?? 0,
                'class' => 'text-end input-freighttotal',
                'min'   => 0,
                'step'  => '0.01'
            ],
        ];
    }

    protected function getDetailFields(): array
    {
        $items = $this->mItemmaster->filters(['status' => 1]);
        $itemOptions = [];
        foreach ($items as $item) {
            $itemOptions[$item['itemcode']] = [
                'label' => $item['itemname'],
                'uom'   => $item['uomcode'] ?? $item['uom'] ?? '',
                'price' => $item['price'] ?? $item['unitprice'] ?? 0
            ];
        }

        return [
            [
                'name'        => 'itemcode',
                'label'       => 'Item Produk',
                'type'        => 'datalist',
                'options'     => $itemOptions,
                'required'    => true,
                'placeholder' => 'Pilih Item'
            ],
            [
                'name'     => 'uomcode',
                'label'    => 'Uom',
                'type'     => 'text',
                'width'    => '10%',
                'readonly' => true,
            ],
            [
                'name'     => 'qty',
                'label'    => 'Jumlah',
                'type'     => 'number',
                'class'    => 'text-end input-qty',
                'required' => true,
                'min'      => 0,
                'max'      => 9999999
            ],
            [
                'name'     => 'unitprice',
                'label'    => 'Harga',
                'type'     => 'number',
                'class'    => 'text-end input-price',
                'required' => true,
                'min'      => 0
            ],
            [
                'name'  => 'discount',
                'label' => 'Diskon',
                'type'  => 'number',
                'class' => 'text-end input-discount',
                'min'   => 0
            ],
            [
                'name'     => 'subtotal',
                'label'    => 'Sub Total',
                'type'     => 'number',
                'width'    => '20%',
                'class'    => 'text-end input-subtotal',
                'readonly' => true
            ],
        ];
    }

    private function extractItemsFromPost(array $postData): array
    {
        $items = $postData['items'] 
              ?? $postData['details'] 
              ?? $postData['detail'] 
              ?? $postData['arinvoicedetail'] 
              ?? [];

        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        return is_array($items) ? $items : [];
    }

    private function getSourceType($db, $baseEntry): string
    {
        if (empty($baseEntry)) return 'SO';
        $do = $db->table('doheader')->where('docnum', $baseEntry)->first();
        if ($do) return 'DO';
        return 'SO';
    }

    private function checkAndUpdateSourceStatus($db, $baseEntry, $sourceType)
    {
        if (empty($baseEntry)) return;

        if ($sourceType === 'SO') {
            $details = $db->table('sodetail')->where('docnum', $baseEntry)->get();
            if (empty($details)) return;

            $isFullyInvoiced = true;
            foreach ($details as $detail) {
                $detailArray = (array)$detail;
                $qtyTotal    = (float)($detailArray['qty'] ?? 0);
                $qtyInvoiced = (float)($detailArray['qtyinvoiced'] ?? 0);
                
                if ($qtyInvoiced < $qtyTotal) {
                    $isFullyInvoiced = false;
                    break;
                }
            }

            $newStatus = $isFullyInvoiced ? 2 : 1;
            $db->table('soheader')
               ->where('docnum', $baseEntry)
               ->update(['status' => $newStatus]);
        } else {
            $db->table('doheader')
               ->where('docnum', $baseEntry)
               ->update(['status' => 2]);
        }
    }

    private function resolveTargetSoInfo($db, string $baseEntry, string $sourceType, array $itemDetail, int $index = 0): ?array
    {
        if (empty($baseEntry)) return null;

        $itemBaseline = $itemDetail['baseline'] 
                     ?? $itemDetail['linenum'] 
                     ?? $itemDetail['line_num'] 
                     ?? $itemDetail['base_line'] 
                     ?? null;

        $itemCode = $itemDetail['itemcode'] 
                 ?? $itemDetail['item_code'] 
                 ?? $itemDetail['code'] 
                 ?? '';

        if ($sourceType === 'SO') {
            return [
                'soDocNum'  => $baseEntry,
                'soLineNum' => !empty($itemBaseline) ? $itemBaseline : ($index + 1),
                'itemCode'  => $itemCode
            ];
        } else if ($sourceType === 'DO') {
            $doHeader = $db->table('doheader')->where('docnum', $baseEntry)->first();
            $doHeaderArray = json_decode(json_encode($doHeader), true);
            $soDocNum   = $doHeaderArray['baseentry'] ?? null;
            if (empty($soDocNum)) return null;

            $soLineNum = null;
            $doQuery = $db->table('dodetail')->where('docnum', $baseEntry);
            if (!empty($itemBaseline)) {
                $doQuery->where('linenum', $itemBaseline);
            } else {
                $allDo = $doQuery->get();
                $allDoArray = json_decode(json_encode($allDo), true);
                $doRow = $allDoArray[$index] ?? $allDoArray[0] ?? null;
                $itemBaseline = $doRow['linenum'] ?? null;
                
                if ($itemBaseline) {
                    $doQuery = $db->table('dodetail')->where('docnum', $baseEntry)->where('linenum', $itemBaseline);
                }
            }

            $doDetailRow = $doQuery->first();
            $doDetailRowArray = json_decode(json_encode($doDetailRow), true);
            if (!empty($doDetailRowArray['baseline'])) {
                $soLineNum = $doDetailRowArray['baseline'];
            }
            if (empty($soLineNum)) {
                if (!empty($itemCode)) {
                    $fallbackSo = $db->table('sodetail')
                        ->where('docnum', $soDocNum)
                        ->where('itemcode', $itemCode)
                        ->first();
                    $fallbackSoArray = json_decode(json_encode($fallbackSo), true);
                    $soLineNum = $fallbackSoArray['linenum'] ?? ($index + 1);
                } else {
                    $soLineNum = $index + 1;
                }
            }

            return [
                'soDocNum'  => $soDocNum,
                'soLineNum' => $soLineNum,
                'itemCode'  => $doDetailRowArray['itemcode'] ?? $itemCode
            ];
        }

        return null;
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

        $status = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;

        DB::beginTransaction();

        try {
            $headerData = $this->prepareHeaderOnCreate($postData, $status, $request);
            $headerData[$txnNumberField] = $txnNumber;

            $baseEntry  = $headerData['baseentry'] ?? $postData['baseentry'] ?? null;
            $sourceType = $headerData['sourcetype'] ?? $postData['sourcetype'] ?? $this->getSourceType(DB::getFacadeRoot(), $baseEntry);

            $items = $this->extractItemsFromPost($postData);
            $detailData = $this->prepareDetailsOnCreate($items, $txnNumber, $request);

            if (method_exists($this->model, 'saveadd')) {
                $this->model->saveadd($headerData, $detailData, $txnNumberField);
            } else {
                $this->model->insert($headerData);
                DB::table($this->model->table . 'detail')->insert($detailData);
            }

            if ($status === 1) {
                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'ARINV');
                } elseif (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('ARINV', $txnNumber, $headerData, $detailData);
                }

                if (!empty($baseEntry)) {
                    foreach ($detailData as $index => $item) {
                        $qty = (float)($item['qty'] ?? $item['quantity'] ?? 0);
                        if ($qty > 0) {
                            $poInfo = $this->resolveTargetSoInfo(DB::getFacadeRoot(), $baseEntry, $sourceType, $item, $index);
                            if ($poInfo && !empty($poInfo['soDocNum'])) {
                                DB::table('sodetail')->where('docnum', $poInfo['soDocNum'])
                                    ->when(!empty($poInfo['soLineNum']), function($q) use ($poInfo) {
                                        return $q->where('linenum', $poInfo['soLineNum']);
                                    }, function($q) use ($poInfo) {
                                        return $q->where('itemcode', $poInfo['itemCode']);
                                    })
                                    ->increment('qtyinvoiced', $qty);
                            }
                        }
                    }

                    if ($sourceType === 'SO') {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $baseEntry, 'SO');
                    } else {
                        DB::table('doheader')->where('docnum', $baseEntry)->update(['status' => 2]);
                        $doHeader = DB::table('doheader')->where('docnum', $baseEntry)->first();
                        $doHeaderArray = json_decode(json_encode($doHeader), true);
                        if (!empty($doHeaderArray['baseentry'])) {
                            $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $doHeaderArray['baseentry'], 'SO');
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

            $message = $status === 9 ? 'Draft AR Invoice berhasil disimpan.' : 'AR Invoice berhasil disimpan dan di-approve.';

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
            $header = $this->model->find($txnNumber) ?? $this->model->where('docnum', $txnNumber)->first();
            $headerArray = is_object($header) ? (array)$header : $header;

            if (!$headerArray) {
                return $this->ajaxResponse('error', 'Data transaksi AR Invoice tidak ditemukan.');
            }

            $realId       = $headerArray['id'] ?? $txnNumber;
            $oldHeader    = $headerArray;
            $oldDetails   = $this->model->get_details($txnNumber);
            $oldStatus    = (int)($oldHeader['status'] ?? 9); 
            $oldBaseEntry = $oldHeader['baseentry'] ?? null;
            $oldSourceType= $oldHeader['sourcetype'] ?? $this->getSourceType(DB::getFacadeRoot(), $oldBaseEntry);
            
            if ($oldStatus === 1) {
                if (!empty($oldBaseEntry)) {
                    if (!empty($oldDetails)) {
                        foreach ($oldDetails as $index => $oldItem) {
                            $oldItemArray = (array)$oldItem;
                            $oldQty = (float)($oldItemArray['qty'] ?? $oldItemArray['quantity'] ?? 0);
                            if ($oldQty > 0) {
                                $poInfo = $this->resolveTargetSoInfo(DB::getFacadeRoot(), $oldBaseEntry, $oldSourceType, $oldItemArray, $index);
                                if ($poInfo && !empty($poInfo['soDocNum'])) {
                                    DB::table('sodetail')->where('docnum', $poInfo['soDocNum'])
                                        ->when(!empty($poInfo['soLineNum']), function($q) use ($poInfo) {
                                            return $q->where('linenum', $poInfo['soLineNum']);
                                        }, function($q) use ($poInfo) {
                                            return $q->where('itemcode', $poInfo['itemCode']);
                                        })
                                        ->decrement('qtyinvoiced', $oldQty);
                                }
                            }
                        }
                    }

                    if ($oldSourceType === 'SO') {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $oldBaseEntry, 'SO');
                    } else {
                        DB::table('doheader')->where('docnum', $oldBaseEntry)->update(['status' => 1]);
                        $doHeader = DB::table('doheader')->where('docnum', $oldBaseEntry)->first();
                        $doHeaderArray = json_decode(json_encode($doHeader), true);
                        if (!empty($doHeaderArray['baseentry'])) {
                            $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $doHeaderArray['baseentry'], 'SO');
                        }
                    }
                }
            }

            $headerData = $this->prepareHeaderData($txnNumber, $postData); 
            $baseEntry  = $headerData['baseentry'] ?? $postData['baseentry'] ?? $oldBaseEntry ?? null;
            $sourceType = $headerData['sourcetype'] ?? $postData['sourcetype'] ?? $oldSourceType ?? $this->getSourceType(DB::getFacadeRoot(), $baseEntry); 
            $headerData['baseentry']  = $baseEntry;
            $headerData['sourcetype'] = $sourceType;

            $detailData = $this->prepareDetailsData($txnNumber, $postData); 
            $saveResult = $this->model->saveedit($realId, $headerData, $detailData);
            if (isset($saveResult['status']) && $saveResult['status'] === 'error') {
                throw new \Exception($saveResult['message'] ?? 'Gagal memperbarui data.');
            } 
            
            if ($status === 1) {
                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'ARINV');
                } else {
                    if (method_exists($this->mJournal, 'updateJournal')) {
                        $this->mJournal->updateJournal('ARINV', $txnNumber, $headerData, $detailData);
                    } elseif (method_exists($this->mJournal, 'createJournal')) {
                        $this->mJournal->createJournal('ARINV', $txnNumber, $headerData, $detailData);
                    }
                }

                if (!empty($baseEntry)) {
                    foreach ($detailData as $index => $item) {
                        $qty = (float)($item['qty'] ?? $item['quantity'] ?? 0);
                        if ($qty > 0) {
                            $poInfo = $this->resolveTargetSoInfo(DB::getFacadeRoot(), $baseEntry, $sourceType, $item, $index);
                            if ($poInfo && !empty($poInfo['soDocNum'])) {
                                DB::table('sodetail')->where('docnum', $poInfo['soDocNum'])
                                    ->when(!empty($poInfo['soLineNum']), function($q) use ($poInfo) {
                                        return $q->where('linenum', $poInfo['soLineNum']);
                                    }, function($q) use ($poInfo) {
                                        return $q->where('itemcode', $poInfo['itemCode']);
                                    })
                                    ->increment('qtyinvoiced', $qty);
                            }
                        }
                    }

                    if ($sourceType === 'SO') {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $baseEntry, 'SO');
                    } else {
                        DB::table('doheader')->where('docnum', $baseEntry)->update(['status' => 2]);
                        $doHeader = DB::table('doheader')->where('docnum', $baseEntry)->first();
                        $doHeaderArray = json_decode(json_encode($doHeader), true);
                        if (!empty($doHeaderArray['baseentry'])) {
                            $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $doHeaderArray['baseentry'], 'SO');
                        }
                    }
                }
            } else {
                if ($oldStatus === 1 && method_exists($this->mJournal, 'deleteByReference')) {
                    $this->mJournal->deleteByReference($txnNumber);
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => ($status === 9 ? 'draft' : 'edit'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'AR Invoice berhasil diperbarui.', [
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
                return $this->ajaxResponse('error', 'Data transaksi AR Invoice tidak ditemukan.');
            }

            $currentStatus = (int)($headerArray['status'] ?? 0);
            if ($currentStatus === 1) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Active.');
            }

            $realId     = $headerArray['id'] ?? $txnNumber;
            $docNum     = $headerArray['docnum'] ?? $txnNumber;
            $baseEntry  = $headerArray['baseentry'] ?? null;
            $sourceType = $headerArray['sourcetype'] ?? $this->getSourceType(DB::getFacadeRoot(), $baseEntry);

            $details = $this->model->get_details($docNum);
            if (empty($details)) {
                return $this->ajaxResponse('error', 'Detail item transaksi kosong, tidak dapat diaktifkan.');
            }

            $updateData = [
                'status'       => 1,
                'approveddate' => date('Y-m-d H:i:s'),
                'approvedby'   => Session::get('usercode') ?? 1
            ];

            if (method_exists($this->model, 'saveactive')) {
                $this->model->saveactive($realId, $updateData);
            } else {
                $this->model->update($realId, $updateData);
            }

            if (method_exists($this, 'handleJournal')) {
                $this->handleJournal($docNum, 1, $headerArray, $details, 'ARINV');
            } else {
                if (method_exists($this->mJournal, 'deleteByReference')) {
                    $this->mJournal->deleteByReference($docNum);
                }
                if (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('ARINV', $docNum, $headerArray, $details);
                }
            }

            if (!empty($baseEntry)) {
                foreach ($details as $index => $item) {
                    $itemArray = (array)$item;
                    $qty = (float)($itemArray['qty'] ?? $itemArray['quantity'] ?? 0);
                    if ($qty > 0) {
                        $poInfo = $this->resolveTargetSoInfo(DB::getFacadeRoot(), $baseEntry, $sourceType, $itemArray, $index);
                        if ($poInfo && !empty($poInfo['soDocNum'])) {
                            DB::table('sodetail')->where('docnum', $poInfo['soDocNum'])
                                ->when(!empty($poInfo['soLineNum']), function($q) use ($poInfo) {
                                    return $q->where('linenum', $poInfo['soLineNum']);
                                }, function($q) use ($poInfo) {
                                    return $q->where('itemcode', $poInfo['itemCode']);
                                })
                                ->increment('qtyinvoiced', $qty);
                        }
                    }
                }

                if ($sourceType === 'SO') {
                    $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $baseEntry, 'SO');
                } else {
                    DB::table('doheader')->where('docnum', $baseEntry)->update(['status' => 2]);
                    $doHeader = DB::table('doheader')->where('docnum', $baseEntry)->first();
                    $doHeaderArray = json_decode(json_encode($doHeader), true);
                    if (!empty($doHeaderArray['baseentry'])) {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $doHeaderArray['baseentry'], 'SO');
                    }
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'active',
                'fa_id'   => $docNum,
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'AR Invoice berhasil diaktifkan (Approved).', [
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
            $oldHeader = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;

            if (!$oldHeaderArray) {
                return $this->ajaxResponse('error', 'Data transaksi AR Invoice tidak ditemukan.');
            }

            $oldStatus    = (int)($oldHeaderArray['status'] ?? 0);
            if ($oldStatus === 0) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Canceled.');
            }

            $oldDetails   = $this->model->get_details($txnNumber);
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;
            $oldSourceType= $oldHeaderArray['sourcetype'] ?? $this->getSourceType(DB::getFacadeRoot(), $oldBaseEntry);

            if ($oldStatus === 1 && !empty($oldBaseEntry)) {
                if (!empty($oldDetails)) {
                    foreach ($oldDetails as $index => $oldItem) {
                        $oldItemArray = (array)$oldItem;
                        $oldQty = (float)($oldItemArray['qty'] ?? $oldItemArray['quantity'] ?? 0);
                        if ($oldQty > 0) {
                            $poInfo = $this->resolveTargetSoInfo(DB::getFacadeRoot(), $oldBaseEntry, $oldSourceType, $oldItemArray, $index);
                            if ($poInfo && !empty($poInfo['soDocNum'])) {
                                DB::table('sodetail')->where('docnum', $poInfo['soDocNum'])
                                    ->when(!empty($poInfo['soLineNum']), function($q) use ($poInfo) {
                                        return $q->where('linenum', $poInfo['soLineNum']);
                                    }, function($q) use ($poInfo) {
                                        return $q->where('itemcode', $poInfo['itemCode']);
                                    })
                                    ->decrement('qtyinvoiced', $oldQty);
                            }
                        }
                    }
                }

                if ($oldSourceType === 'SO') {
                    $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $oldBaseEntry, 'SO');
                } else {
                    DB::table('doheader')->where('docnum', $oldBaseEntry)->update(['status' => 1]);
                    $doHeader = DB::table('doheader')->where('docnum', $oldBaseEntry)->first();
                    $doHeaderArray = json_decode(json_encode($doHeader), true);
                    if (!empty($doHeaderArray['baseentry'])) {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $doHeaderArray['baseentry'], 'SO');
                    }
                }
            }

            $userCode = (int) (Session::get('usercode') ?? 1);
            $this->model->cancelDocument($id ?? $oldHeaderArray['id'] ?? $txnNumber, $userCode); 

            if (method_exists($this, 'handleJournal')) {
                $this->handleJournal($txnNumber, 0, $oldHeaderArray, (array)$oldDetails, 'ARINV');
            } else {
                if (method_exists($this->mJournal, 'updateStatusByReference')) {
                    $this->mJournal->updateStatusByReference($txnNumber, 0);
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'savecancel',
                'fa_id'   => $txnNumber,
                'fa_name' => $this->getTitle()
            ]);

            $alertMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                          . 'Nomor transaksi <strong>' . e($txnNumber) . '</strong> berhasil dibatalkan (Cancel).'
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

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request = null): array
    {
        $header   = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        $computed = $this->calculateHeaderTotals($postData);

        $baseEntry  = !empty($postData['baseentry']) ? $postData['baseentry'] : null;
        $sourceType = !empty($baseEntry) ? $this->getSourceType(DB::getFacadeRoot(), $baseEntry) : '';

        $userCode   = Session::get('usercode') ?? 1;

        $data = array_merge($header, [
            'docdate'        => date('Y-m-d H:i:s'),
            'baseentry'      => $baseEntry,
            'sourcetype'     => $sourceType,
            'cardcode'       => $postData['cardcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'currcode'       => $postData['currcode'] ?? 'IDR',
            'taxclude'       => isset($postData['taxclude']) ? (int)$postData['taxclude'] : 0,
            'taxcode'        => $postData['taxcode'] ?? '',
            'termcode'       => $postData['termcode'] ?? '',
            'remark'         => $postData['remark'] ?? '',
            'status'         => $status,
            'subtotal'       => $computed['subtotal'],
            'discountamount' => $computed['discountamount'],
            'dppamount'      => $computed['dppamount'], 
            'taxtotal'       => $computed['taxtotal'],
            'freighttotal'   => $computed['freighttotal'],
            'grandtotal'     => $computed['grandtotal'],
            'createddate'    => date('Y-m-d H:i:s'),
            'createdby'      => $userCode,
        ]);

        if ($status === 1) {
            $data['approveddate'] = date('Y-m-d H:i:s');
            $data['approvedby']   = $userCode;
        }

        return $data;
    }

    protected function prepareHeaderData(string $txnNumber, array $postData): array
    {
        $computed = $this->calculateHeaderTotals($postData);

        $baseEntry  = !empty($postData['baseentry']) ? $postData['baseentry'] : null;
        $sourceType = !empty($baseEntry) ? $this->getSourceType(DB::getFacadeRoot(), $baseEntry) : '';

        $userCode   = Session::get('usercode') ?? 1;

        $data = [
            'baseentry'      => $baseEntry,
            'sourcetype'     => $sourceType,
            'cardcode'       => $postData['cardcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'currcode'       => $postData['currcode'] ?? 'IDR',
            'taxclude'       => isset($postData['taxclude']) ? (int)$postData['taxclude'] : 0,
            'taxcode'        => $postData['taxcode'] ?? '',
            'termcode'       => $postData['termcode'] ?? '',
            'subtotal'       => $computed['subtotal'],
            'discountamount' => $computed['discountamount'],
            'dppamount'      => $computed['dppamount'],
            'taxtotal'       => $computed['taxtotal'],
            'freighttotal'   => $computed['freighttotal'],
            'grandtotal'     => $computed['grandtotal'],
            'remark'         => $postData['remark'] ?? '',
            'updateddate'    => date('Y-m-d H:i:s'),
            'updatedby'      => $userCode,
        ];

        return $data;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, Request $request = null): array
    {
        $detailsData = [];

        foreach ($items as $index => $item) {
            $qty       = $this->parseNumber($item['qty'] ?? 0);
            $unitprice = $this->parseNumber($item['unitprice'] ?? 0);
            $discount  = $this->parseNumber($item['discount'] ?? 0);
            $subtotal  = ($qty * $unitprice) - (($discount / 100) * $qty * $unitprice);
            $baseLine  = $item['baseline'] ?? ($index + 1);

            $taxcode   = $item['taxcode'] ?? '';
            $taxamount = 0;

            if (!empty($taxcode)) {
                $taxData = $this->mTax->find($taxcode);
                $taxDataArray = is_object($taxData) ? (array)$taxData : $taxData;
                if ($taxDataArray) {
                    $taxRate = (float) ($taxDataArray['nilai'] ?? 0);
                } else {
                    $taxRate = (float) preg_replace('/[^0-9.]/', '', $taxcode);
                }
                $taxamount = $subtotal * ($taxRate / 100);
            } else {
                $taxamount = $this->parseNumber($item['taxamount'] ?? 0);
            }

            $detailsData[] = [
                'docnum'    => $txnNumber,
                'linenum'   => $index + 1,
                'baseline'  => $baseLine,
                'itemcode'  => $item['itemcode'] ?? '',
                'uomcode'   => $item['uomcode'] ?? '',
                'qty'       => $qty,
                'unitprice' => $unitprice,
                'discount'  => $discount,
                'subtotal'  => $subtotal,
            ];
        }

        return $detailsData;
    }

    protected function prepareDetailsData(string $txnNumber, array $postData): array
    {
        $items = $this->extractItemsFromPost($postData);
        return $this->prepareDetailsOnCreate($items, $txnNumber, request());
    }

    private function parseNumber($val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float) $val;

        $val = trim((string) $val);
        if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } else if (strpos($val, ',') !== false) {
            $val = str_replace(',', '.', $val);
        }

        return (float) $val;
    }

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        if (empty($postData['cardcode'])) {
            return 'Customer wajib dipilih.';
        }
        if (empty($postData['whscode'])) {
            return 'Gudang tujuan wajib dipilih.';
        }

        $freightTotal = $this->parseNumber($postData['freighttotal'] ?? 0);
        if ($freightTotal < 0) {
            return 'Biaya angkut (freight total) tidak boleh bernilai negatif.';
        }

        $items = $this->extractItemsFromPost($postData);
        if (empty($items)) {
            return 'Detail barang yang dipesan tidak boleh kosong!';
        }

        $baseEntry  = $postData['baseentry'] ?? null;
        $sourceType = !empty($baseEntry) ? $this->getSourceType(DB::getFacadeRoot(), $baseEntry) : ($postData['sourcetype'] ?? 'DO');

        $oldDetailsMap = [];
        if (!empty($currentDocNum)) {
            $oldDetails = $this->model->get_details($currentDocNum);
            if (!empty($oldDetails)) {
                foreach ($oldDetails as $old) {
                    $oldArray = (array)$old;
                    $key = !empty($oldArray['baseline']) ? $oldArray['baseline'] : $oldArray['itemcode'];
                    $oldDetailsMap[$key] = (float)($oldArray['qty'] ?? 0);
                }
            }
        }

        foreach ($items as $index => $item) {
            $rowNum   = $index + 1;
            $itemCode = $item['itemcode'] ?? '';
            $qtyInput = $this->parseNumber($item['qty'] ?? 0);
            $baseLine = $item['baseline'] ?? ($index + 1);
            
            if (empty($itemCode)) {
                return "Barang pada baris ke-{$rowNum} belum dipilih.";
            }
            if ($qtyInput <= 0) {
                return "Jumlah (Qty) pada baris ke-{$rowNum} harus lebih dari 0.";
            }
            if ($this->parseNumber($item['unitprice'] ?? 0) < 0) {
                return "Harga satuan pada baris ke-{$rowNum} tidak boleh bernilai negatif.";
            }

            if (!empty($baseEntry)) {
                if ($sourceType === 'SO') {
                    $builder = DB::table('sodetail')->where('docnum', $baseEntry);
                    if (!empty($baseLine)) {
                        $builder->where('linenum', $baseLine);
                    } else {
                        $builder->where('itemcode', $itemCode);
                    }
                    $refDetail = $builder->first();
                    $refDetailArray = json_decode(json_encode($refDetail), true);

                    if (!$refDetailArray) {
                        return "Baris ke-{$rowNum}: Item {$itemCode} tidak ditemukan pada referensi SO ({$baseEntry}).";
                    }

                    $qtyDoc      = (float)($refDetailArray['qty'] ?? 0);
                    $qtyInvoiced = (float)($refDetailArray['qtyinvoiced'] ?? 0);

                    $mapKey = !empty($baseLine) ? $baseLine : $itemCode;
                    $oldQty = $oldDetailsMap[$mapKey] ?? 0;
                    $effectiveInvoiced = $qtyInvoiced - $oldQty;

                    $remainingQty = $qtyDoc - $effectiveInvoiced;

                    if ($qtyInput > $remainingQty) {
                        return "Baris ke-{$rowNum}: Qty AR Invoice ({$qtyInput}) melebihi sisa Qty SO yang tersedia ({$remainingQty}) untuk item {$itemCode}.";
                    }
                } else {
                    $builder = DB::table('dodetail')->where('docnum', $baseEntry);
                    if (!empty($baseLine)) {
                        $builder->where('linenum', $baseLine);
                    } else {
                        $builder->where('itemcode', $itemCode);
                    }
                    $refDetail = $builder->first();
                    $refDetailArray = json_decode(json_encode($refDetail), true);

                    if (!$refDetailArray) {
                        return "Baris ke-{$rowNum}: Item {$itemCode} tidak ditemukan pada referensi DO ({$baseEntry}).";
                    }

                    $qtyDoc      = (float)($refDetailArray['qty'] ?? 0);
                    $qtyInvoiced = (float)($refDetailArray['qtyinvoiced'] ?? 0);

                    $mapKey = !empty($baseLine) ? $baseLine : $itemCode;
                    $oldQty = $oldDetailsMap[$mapKey] ?? 0;
                    $effectiveInvoiced = $qtyInvoiced - $oldQty;

                    $remainingQty = $qtyDoc - $effectiveInvoiced;

                    if ($qtyInput > $remainingQty) {
                        return "Baris ke-{$rowNum}: Qty AR Invoice ({$qtyInput}) melebihi sisa Qty DO ({$remainingQty}) untuk item {$itemCode}.";
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

        $cardcode = $request->input('cardcode');

        $soList   = $this->mSalesorder->getOpen($cardcode) ?? [];
        $doList   = $this->mDeliveryOrder->getOpenForInvoice($cardcode) ?? [];

        $listData = array_merge($soList, $doList);

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

        $sourceType = 'DO';
        $docData = $this->mDeliveryOrder->getWithDetails($docnum);

        if (!$docData || (isset($docData['header']['status']) && (int)$docData['header']['status'] !== 1)) {
            $docData = $this->mSalesorder->getWithDetails($docnum);
            $sourceType = 'SO';
        }

        if (!$docData || (isset($docData['header']['status']) && (int)$docData['header']['status'] !== 1)) {
            return response()->json([
                'status'  => false, 
                'message' => 'Data referensi (SO / DO) tidak ditemukan atau status sudah tidak aktif.'
            ]);
        }

        $header = $docData['header'];
        $header['baseentry']  = $header['docnum'] ?? $docnum;
        $header['sourcetype'] = $sourceType; 

        unset(
            $header['docnum'], 
            $header['docdate'], 
            $header['status'], 
            $header['createddate'], 
            $header['createdby'], 
            $header['updateddate'], 
            $header['updatedby']
        );

        if (!empty($header['requireddate'])) {
            $header['delivereddate'] = date('Y-m-d', strtotime($header['requireddate']));
            unset($header['requireddate']);
        }

        foreach ($header as $key => $val) {
            $valStr = (string)$val;
            if ($valStr !== '' && (strpos($key, 'date') !== false) && strtotime($valStr)) {
                $header[$key] = date('Y-m-d', strtotime($valStr));
            }
        }

        $processedDetails = [];
        if (!empty($docData['details'])) {
            foreach ($docData['details'] as $row) {
                $rowArray = (array)$row;
                $qtyDoc      = (float)($rowArray['qty'] ?? 0);
                $qtyinvoiced  = (float)($rowArray['qtyinvoiced'] ?? 0);
                $remainingQty = $qtyDoc - $qtyinvoiced; 

                if ($remainingQty > 0) {
                    $unitprice = (float)($rowArray['unitprice'] ?? 0);
                    $discount  = (float)($rowArray['discount'] ?? 0);
                    $subtotal  = ($remainingQty * $unitprice) - (($discount / 100) * $remainingQty * $unitprice);

                    $processedDetails[] = array_merge($rowArray, [
                        'baseline' => $rowArray['linenum'] ?? $rowArray['lineid'] ?? 1, 
                        'qty'      => $remainingQty,         
                        'subtotal' => $subtotal              
                    ]);
                }
            }
        }

        return response()->json([
            'status'  => true,
            'header'  => $header,
            'details' => $processedDetails
        ]);
    }
}