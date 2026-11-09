<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\Journal;
use App\Models\Account;
use App\Models\Cardmaster;
use App\Models\Currency;
use App\Models\Paymenttype;

abstract class BasePaymentController extends BaseTransactionController
{
    protected Journal $mJournal;
    protected Account $mAccount;
    protected Cardmaster $mCardmaster;
    protected Currency $mCurrency;
    protected Paymenttype $mPaymenttype;
    protected $invoiceModel;

    protected bool $showImport = false;
    protected bool $showRemark  = true;
    protected bool $showSummary = true;
    protected bool $showFreight = false;

    abstract protected function getInvoiceModel();
    abstract protected function getInvoiceTableName(): string;
    abstract protected function getDetailTableName(): string;
    abstract protected function getSourceType(): string; 
    abstract protected function getBaseTypeName(): string; 
    abstract protected function getPartnerLabel(): string; 
    abstract protected function getInvoiceLabel(): string; 

    public function __construct()
    {
        parent::__construct();
        $this->mJournal     = new Journal();
        $this->mAccount     = new Account();
        $this->mCardmaster  = new Cardmaster();
        $this->mCurrency    = new Currency();
        $this->mPaymenttype = new Paymenttype();
        $this->invoiceModel = $this->getInvoiceModel();
    }

    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }

    protected function getExtraColumns($item): array 
    { 
        return [
            e($item['cardname'] ?? '-'),
            e($item['paymentname'] ?? '-'),
            number_format((float)($item['totalamount'] ?? 0), 2, ',', '.'),
        ]; 
    }

    protected function getExtraFields($row = null): array
    {
        return [            
            [
                'name'    => 'cardcode',
                'label'   => $this->getPartnerLabel(),
                'type'    => 'select',
                'value'   => $row['cardcode'] ?? '',
                'options' => $this->formatOptions($this->mCardmaster->filters([
                    'cardgroupcode' => $this->getCardGroupCode(), 
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
                'name'    => 'cashbankaccount',
                'label'   => 'Kas / Bank',
                'type'    => 'select',
                'value'   => $row['cashbankaccount'] ?? '',
                'options' => $this->formatOptions($this->mAccount->filters([
                    'status' => 1
                ]), 'acctcode', 'acctname'),
            ],
            [
                'name'    => 'paymentcode',
                'label'   => 'Tipe Pembayaran',
                'type'    => 'select',
                'value'   => $row['paymentcode'] ?? '',
                'options' => $this->formatOptions($this->mPaymenttype->filters([
                    'status' => 1
                ]), 'paymentcode', 'paymentname'),
            ]
        ];
    }

    protected function getDetailFields(): array
    {
        return [
            [
                'name'          => 'baseentry',
                'label'         => $this->getInvoiceLabel(),
                'type'          => 'text',
                'readonly'      => true,
                'required'      => true,
            ],
            [
                'name'          => 'grandtotal',
                'label'         => 'Total Tagihan',
                'type'          => 'number',
                'class'         => 'text-end',
                'readonly'      => true,
            ],
            [
                'name'          => 'paidamount_old',
                'label'         => 'Sudah Dibayar',
                'type'          => 'number',
                'class'         => 'text-end',
                'readonly'      => true,
            ],
            [
                'name'          => 'amountapplied',
                'label'         => 'Jumlah Bayar',
                'type'          => 'number',
                'class'         => 'text-end input-amountapplied',
                'required'      => true,
                'min'           => 0
            ],
            [
                'name'          => 'discountapplied',
                'label'         => 'Diskon',
                'type'          => 'number',
                'class'         => 'text-end input-amountapplied',
                'required'      => true,
                'min'           => 0
            ]
        ];
    }

    public function add()
    {
        if ($guard = $this->guardModuleAccess('isadd')) return $guard;
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
            'message'              => '',
            
            'copy_from_url'        => url($this->getBaseRoute() . '/getOpenList'),
            'copy_from_detail_url' => url($this->getBaseRoute() . '/getOpenDetail'),
            'copy_from_label'      => 'Copy From Invoice',
        ];

        return view('payments.add', $data);
    }

    public function edit($id = null)
    {
        if ($guard = $this->guardModuleAccess('isedit')) return $guard;

        $header = $this->model->find($id) ?? $this->model->where('docnum', $id)->first();
        if (!$header) {
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Data transaksi ' . $this->getTitle() . ' tidak ditemukan.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') 
            ? $header->toArray() 
            : (array)$header;

        $docNumber = $headerArray[$this->getTxnNumberField()] ?? $id;
        $status    = (int)($headerArray['status'] ?? 0);
        if (in_array($status, [0, 2], true)) { 
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Transaksi yang sudah selesai (Close) atau dibatalkan (Cancel) tidak dapat diubah.');
        }

        $details = $this->model->get_details($docNumber) ?? [];

        $data = [
            'title'                => 'Edit ' . $this->getTitle(),
            'base_route'           => $this->getBaseRoute(),
            'field_code'           => $this->getTxnNumberField(),
            'field_date'           => $this->getDateField(),
            'current_number'       => $docNumber,
            'header'               => $headerArray,
            'details'              => $details,
            'extra'                => $this->getExtraFields($headerArray),
            'detail_columns'       => $this->getDetailFields(),
            'action_url'           => $this->getBaseRoute() . '/saveedit/' . $id,
            'show_remark'          => $this->showRemark,
            'show_summary'         => $this->showSummary,
            'show_freight'         => $this->showFreight,
            
            'copy_from_url'        => url($this->getBaseRoute() . '/getOpenList'),
            'copy_from_detail_url' => url($this->getBaseRoute() . '/getOpenDetail'),
            'copy_from_label'      => 'Copy From',
            'message'              => Session::get('message') ?? Session::get('error_msg') ?? ''
        ];

        return view('payments.edit', $data);
    }

    public function view($id = null)
    {
        if ($guard = $this->guardModuleAccess('isread')) return $guard;

        $header = $this->model->find($id) ?? $this->model->where('docnum', $id)->first();
        if (!$header) {
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Data transaksi ' . $this->getTitle() . ' tidak ditemukan.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') 
            ? $header->toArray() 
            : (array)$header;

        $docNumber = $headerArray[$this->getTxnNumberField()] ?? $id;
        $details   = $this->model->get_details($docNumber) ?? [];

        $data = [
            'title'          => 'Detail ' . $this->getTitle(),
            'base_route'     => $this->getBaseRoute(),
            'field_code'     => $this->getTxnNumberField(),
            'field_date'     => $this->getDateField(),
            'current_number' => $docNumber,
            'header'         => $headerArray,
            'details'        => $details,
            'extra'          => $this->getExtraFields($headerArray),
            'detail_columns' => $this->getDetailFields(),
            'show_remark'    => $this->showRemark,
            'show_summary'   => $this->showSummary,
            'show_freight'   => $this->showFreight,
        ];

        return view('payments.view', $data);
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
            
            $detailData = $this->prepareDetailsOnCreate($items, $txnNumber, $request);

            if (method_exists($this->model, 'saveadd')) {
                $this->model->saveadd($headerData, $detailData, $txnNumberField);
            } else {
                $this->model->insert($headerData);
                DB::table($this->getDetailTableName())->insert($detailData);
            }

            if ($status === 1) {
                foreach ($detailData as $item) {
                    $invDocNum       = $item['baseentry'] ?? null;
                    $amountApplied   = (float)($item['amountapplied'] ?? 0);
                    $discountApplied = (float)($item['discountapplied'] ?? 0);
                    $totalApplied    = $amountApplied + $discountApplied;

                    if (!empty($invDocNum) && $totalApplied > 0) {
                        $invoice = DB::table($this->getInvoiceTableName())->where('docnum', $invDocNum)->first();
                        $invoice = json_decode(json_encode($invoice), true);
                        if ($invoice) {
                            $currentPaid = (float)($invoice['paidamount'] ?? 0);
                            $newPaid     = $currentPaid + $totalApplied;
                            $grandTotal  = (float)($invoice['grandtotal'] ?? 0);
                            
                            $updateInvoiceData = ['paidamount' => $newPaid];
                            if ($newPaid >= $grandTotal) {
                                $updateInvoiceData['status'] = 2; 
                            }

                            DB::table($this->getInvoiceTableName())
                               ->where('docnum', $invDocNum)
                               ->update($updateInvoiceData);
                        }
                    }
                }

                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, $this->getSourceType());
                } elseif (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal($this->getSourceType(), $txnNumber, $headerData, $detailData);
                }
            }

            DB::commit();

            Session::flash([
                'status'  => ($status === 9 ? 'draft' : 'add'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            $message = $status === 9 ? 'Draft ' . $this->getTitle() . ' berhasil disimpan.' : $this->getTitle() . ' berhasil disimpan dan di-approve.';

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
            $oldHeader  = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldDetails = $this->model->get_details($txnNumber);
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            $oldStatus  = (int)($oldHeaderArray['status'] ?? 9);

            if ($oldStatus === 1 && !empty($oldDetails)) {
                foreach ($oldDetails as $oldItem) {
                    $oldItemArray    = (array)$oldItem;
                    $invDocNum       = $oldItemArray['baseentry'] ?? null;
                    $amountApplied   = (float)($oldItemArray['amountapplied'] ?? 0);
                    $discountApplied = (float)($oldItemArray['discountapplied'] ?? 0);
                    $totalApplied    = $amountApplied + $discountApplied;

                    if (!empty($invDocNum) && $totalApplied > 0) {
                        $invoice = DB::table($this->getInvoiceTableName())->where('docnum', $invDocNum)->first();
                        $invoice = json_decode(json_encode($invoice), true);
                        if ($invoice) {
                            $currentPaid = (float)($invoice['paidamount'] ?? 0);
                            $newPaid     = max(0, $currentPaid - $totalApplied); 
                            
                            $updateInvoiceData = ['paidamount' => $newPaid];
                            if ((int)$invoice['status'] === 2) {
                                $updateInvoiceData['status'] = 1; 
                            }

                            DB::table($this->getInvoiceTableName())
                               ->where('docnum', $invDocNum)
                               ->update($updateInvoiceData);
                        }
                    }
                }
            }

            $headerData = $this->prepareHeaderData($txnNumber, $postData);
            $detailData = $this->prepareDetailsData($txnNumber, $postData);

            $saveResult = $this->model->saveedit($id, $headerData, $detailData);
            if (isset($saveResult['status']) && $saveResult['status'] === 'error') {
                throw new \Exception($saveResult['message'] ?? 'Gagal memperbarui data.');
            }

            if ($status === 1) {
                foreach ($detailData as $item) {
                    $invDocNum       = $item['baseentry'] ?? null;
                    $amountApplied   = (float)($item['amountapplied'] ?? 0);
                    $discountApplied = (float)($item['discountapplied'] ?? 0);
                    $totalApplied    = $amountApplied + $discountApplied;

                    if (!empty($invDocNum) && $totalApplied > 0) {
                        $invoice = DB::table($this->getInvoiceTableName())->where('docnum', $invDocNum)->first();
                        $invoice = json_decode(json_encode($invoice), true);
                        if ($invoice) {
                            $currentPaid = (float)($invoice['paidamount'] ?? 0);
                            $newPaid     = $currentPaid + $totalApplied;
                            $grandTotal  = (float)($invoice['grandtotal'] ?? 0);
                            
                            $updateInvoiceData = ['paidamount' => $newPaid];
                            if ($newPaid >= $grandTotal) {
                                $updateInvoiceData['status'] = 2; 
                            }

                            DB::table($this->getInvoiceTableName())
                               ->where('docnum', $invDocNum)
                               ->update($updateInvoiceData);
                        }
                    }
                }

                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, $this->getSourceType());
                } else {
                    if (method_exists($this->mJournal, 'updateJournal')) {
                        $this->mJournal->updateJournal($this->getSourceType(), $txnNumber, $headerData, $detailData);
                    } elseif (method_exists($this->mJournal, 'createJournal')) {
                        if (method_exists($this->mJournal, 'deleteByReference')) {
                            $this->mJournal->deleteByReference($txnNumber);
                        }
                        $this->mJournal->createJournal($this->getSourceType(), $txnNumber, $headerData, $detailData);
                    }
                }
            } else {
                if ($oldStatus === 1) {
                    if (method_exists($this, 'handleJournal')) {
                        $this->handleJournal($txnNumber, 0, (array)$oldHeader, (array)$oldDetails, $this->getSourceType());
                    } elseif (method_exists($this->mJournal, 'deleteByReference')) {
                        $this->mJournal->deleteByReference($txnNumber);
                    }
                }
            }

            DB::commit();

            Session::flash([
                'status'  => ($status === 9 ? 'draft' : 'edit'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', $this->getTitle() . ' berhasil diperbarui.', [
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
                return $this->ajaxResponse('error', 'Data transaksi ' . $this->getTitle() . ' tidak ditemukan.');
            }

            $currentStatus = (int)($headerArray['status'] ?? 0);
            if ($currentStatus === 1) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Active.');
            }

            $realId = $headerArray['id'] ?? $txnNumber;
            $docNum = $headerArray['docnum'] ?? $txnNumber;

            $details = $this->model->get_details($docNum);
            if (empty($details)) {
                return $this->ajaxResponse('error', 'Detail item pembayaran kosong, tidak dapat diaktifkan.');
            }

            $updateData = [
                'status'       => 1,
                'approveddate' => date('Y-m-d H:i:s'),
                'approvedby'   => Session::get('usercode') ?? Session::get('user_id') ?? 1
            ];

            if (method_exists($this->model, 'saveactive')) {
                $this->model->saveactive($realId, $updateData);
            } else {
                $this->model->update($realId, $updateData);
            }

            foreach ($details as $item) {
                $itemArray       = (array)$item;
                $invDocNum       = $itemArray['baseentry'] ?? null;
                $amountApplied   = (float)($itemArray['amountapplied'] ?? 0);
                $discountApplied = (float)($itemArray['discountapplied'] ?? 0);
                $totalApplied    = $amountApplied + $discountApplied;

                if (!empty($invDocNum) && $totalApplied > 0) {
                    $invoice = DB::table($this->getInvoiceTableName())->where('docnum', $invDocNum)->first();
                    $invoice = json_decode(json_encode($invoice), true);
                    if ($invoice) {
                        $currentPaid = (float)($invoice['paidamount'] ?? 0);
                        $newPaid     = $currentPaid + $totalApplied;
                        $grandTotal  = (float)($invoice['grandtotal'] ?? 0);
                        
                        $updateInvoiceData = ['paidamount' => $newPaid];
                        if ($newPaid >= $grandTotal) {
                            $updateInvoiceData['status'] = 2; 
                        }

                        DB::table($this->getInvoiceTableName())
                           ->where('docnum', $invDocNum)
                           ->update($updateInvoiceData);
                    }
                }
            }

            if (method_exists($this, 'handleJournal')) {
                $this->handleJournal($docNum, 1, $headerArray, $details, $this->getSourceType());
            } else {
                if (method_exists($this->mJournal, 'deleteByReference')) {
                    $this->mJournal->deleteByReference($docNum);
                }
                if (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal($this->getSourceType(), $docNum, $headerArray, $details);
                }
            }

            DB::commit();

            Session::flash([
                'status'  => 'active',
                'fa_id'   => $docNum,
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', $this->getTitle() . ' berhasil diaktifkan (Approved).', [
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
                return $this->ajaxResponse('error', 'Data transaksi ' . $this->getTitle() . ' tidak ditemukan.');
            }

            $oldStatus = (int)($oldHeaderArray['status'] ?? 0);
            if ($oldStatus === 0) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Canceled.');
            }

            $oldDetails = $this->model->get_details($txnNumber);

            if ($oldStatus === 1 && !empty($oldDetails)) {
                foreach ($oldDetails as $oldItem) {
                    $oldItemArray    = (array)$oldItem;
                    $invDocNum       = $oldItemArray['baseentry'] ?? null;
                    $amountApplied   = (float)($oldItemArray['amountapplied'] ?? 0);
                    $discountApplied = (float)($oldItemArray['discountapplied'] ?? 0);
                    $totalApplied    = $amountApplied + $discountApplied;

                    if (!empty($invDocNum) && $totalApplied > 0) {
                        $invoice = DB::table($this->getInvoiceTableName())->where('docnum', $invDocNum)->first();
                        $invoice = json_decode(json_encode($invoice), true);
                        if ($invoice) {
                            $currentPaid = (float)($invoice['paidamount'] ?? 0);
                            $newPaid     = max(0, $currentPaid - $totalApplied);
                            
                            $updateInvoiceData = ['paidamount' => $newPaid];
                            if ((int)$invoice['status'] === 2) {
                                $updateInvoiceData['status'] = 1; 
                            }

                            DB::table($this->getInvoiceTableName())
                               ->where('docnum', $invDocNum)
                               ->update($updateInvoiceData);
                        }
                    }
                }
            }

            $userCode = (int) (Session::get('usercode') ?? 1);
            $this->model->cancelDocument($oldHeaderArray['id'] ?? $id, $userCode);

            if (method_exists($this, 'handleJournal')) {
                $this->handleJournal($txnNumber, 0, $oldHeaderArray, (array)$oldDetails, $this->getSourceType());
            } else {
                if (method_exists($this->mJournal, 'updateStatusByReference')) {
                    $this->mJournal->updateStatusByReference($txnNumber, 0);
                }
            }

            DB::commit();

            Session::flash([
                'status'  => 'savecancel',
                'fa_id'   => $txnNumber,
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', $this->getTitle() . ' berhasil dibatalkan (Cancel).', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'System Error: ' . $e->getMessage());
        }
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request): array
    {
        $header   = parent::prepareHeaderOnCreate($postData, $status, $request);
        $computed = $this->calculateHeaderTotals($postData);

        $data = [
            'docdate'         => date('Y-m-d H:i:s'),
            'cardcode'        => $postData['cardcode'] ?? '',
            'currcode'        => $postData['currcode'] ?? 'IDR',
            'cashbankaccount' => $postData['cashbankaccount'] ?? '',
            'paymentcode'     => $postData['paymentcode'] ?? '',
            'totalamount'     => $computed['totalamount'],
            'remark'          => $postData['remark'] ?? '',
            'status'          => $status,
            'createddate'     => date('Y-m-d H:i:s'),
            'createdby'       => (int) (Session::get('usercode') ?? 1),
        ];

        if ($status === 1) {
            $data['approveddate'] = date('Y-m-d H:i:s');
            $data['approvedby']   = (int) (Session::get('usercode') ?? 1);
        }

        return array_merge($header, $data);
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, Request $request): array
    {
        $detailsData = [];

        foreach ($items as $index => $item) {
            $amountApplied   = $this->parseNumber($item['amountapplied'] ?? 0);
            $discountApplied = $this->parseNumber($item['discountapplied'] ?? 0);
            $baseEntry       = $item['baseentry'] ?? $item['docnum'] ?? '';

            $detailsData[] = [
                'docnum'            => $txnNumber,
                'linenum'           => $index + 1,
                'baseentry'         => $baseEntry,
                'basetype'          => $this->getBaseTypeName(),
                'amountapplied'     => $amountApplied,
                'discountapplied'   => $discountApplied,
            ];
        }

        return $detailsData;
    }

    protected function prepareHeaderData(string $txnNumber, array $postData): array
    {
        $computed = $this->calculateHeaderTotals($postData);

        $data = [
            'cardcode'        => $postData['cardcode'] ?? '',
            'currcode'        => $postData['currcode'] ?? 'IDR',
            'cashbankaccount' => $postData['cashbankaccount'] ?? '',
            'paymentcode'     => $postData['paymentcode'] ?? '',
            'totalamount'     => $computed['totalamount'],
            'remark'          => $postData['remark'] ?? '',
            'updateddate'     => date('Y-m-d H:i:s'),
            'updatedby'       => (int) (Session::get('usercode') ?? 1),
        ];

        return $data;
    }

    protected function prepareDetailsData(string $txnNumber, array $postData): array
    {
        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }
        return $this->prepareDetailsOnCreate($items, $txnNumber, request());
    }

    protected function parseNumber($val): float
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

    protected function calculateHeaderTotals(array $postData): array
    {
        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        $totalAmount = 0;
        if (is_array($items) && !empty($items)) {
            foreach ($items as $item) {
                $totalAmount += $this->parseNumber($item['amountapplied'] ?? 0);
            }
        }

        if ($totalAmount == 0 && !empty($postData['totalamount'])) {
            $totalAmount = $this->parseNumber($postData['totalamount']);
        }

        return [
            'totalamount' => (float) $totalAmount,
        ];
    }

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        if (empty($postData['cardcode'])) {
            return $this->getPartnerLabel() . ' wajib dipilih.';
        }
        if (empty($postData['cashbankaccount'])) {
            return 'Akun Kas / Bank wajib dipilih.';
        }
        if (empty($postData['paymentcode'])) {
            return 'Tipe pembayaran wajib dipilih.';
        }

        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        if (empty($items)) {
            return 'Daftar ' . $this->getBaseTypeName() . ' yang dibayar tidak boleh kosong!';
        }

        foreach ($items as $index => $item) {
            $rowNum          = $index + 1;
            $amountApplied   = $this->parseNumber($item['amountapplied'] ?? 0);
            $discountApplied = $this->parseNumber($item['discountapplied'] ?? 0);
            $baseEntry       = $item['baseentry'] ?? $item['docnum'] ?? '';

            if (empty($baseEntry)) {
                return "Nomor " . $this->getBaseTypeName() . " pada baris ke-{$rowNum} tidak valid.";
            }
            if ($amountApplied < 0 || $discountApplied < 0) {
                return "Jumlah bayar atau diskon pada baris ke-{$rowNum} tidak boleh bernilai negatif.";
            }
            if (($amountApplied + $discountApplied) <= 0) {
                return "Total pembayaran dan diskon pada baris ke-{$rowNum} harus lebih besar dari 0.";
            }

            $invoice = $this->invoiceModel->find($baseEntry);
            $invoiceArray = is_object($invoice) ? (array)$invoice : $invoice;
            if ($invoiceArray) {
                $grandTotal = (float)($invoiceArray['grandtotal'] ?? 0);
                $paidAmount = (float)($invoiceArray['paidamount'] ?? 0);

                if (!empty($currentDocNum)) {
                    $oldDetail = DB::table($this->getDetailTableName())
                        ->where('docnum', $currentDocNum)
                        ->where('baseentry', $baseEntry)
                        ->first();
                    $oldDetailArray = json_decode(json_encode($oldDetail), true);
                    
                    if ($oldDetailArray) {
                        $oldPaidApplied = (float)($oldDetailArray['amountapplied'] ?? 0) + (float)($oldDetailArray['discountapplied'] ?? 0);
                        $paidAmount = max(0, $paidAmount - $oldPaidApplied);
                    }
                }

                $balanceDue   = $grandTotal - $paidAmount;
                $totalApplied = $amountApplied + $discountApplied;

                if ($totalApplied > ($balanceDue + 1)) {
                    return "Total bayar dan diskon pada baris ke-{$rowNum} (" . number_format($totalApplied, 2, ',', '.') . ") melebihi sisa tagihan " . $this->getBaseTypeName() . " (" . number_format($balanceDue, 2, ',', '.') . ").";
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

        $cardcode = $request->input('cardcode') ?? $request->post('cardcode');
        
        if (empty($cardcode)) {
            return response()->json([
                'status'  => false,
                'message' => $this->getPartnerLabel() . ' belum dipilih pada header.'
            ]);
        }

        $list = method_exists($this->invoiceModel, 'getOpen') ? $this->invoiceModel->getOpen($cardcode) : [];

        $html = view('payments.popcopyfromrows', [
            'listData' => $list
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

        $invoice = $this->invoiceModel->find($docnum);
        $invoiceArray = is_object($invoice) ? (array)$invoice : $invoice;
        if (!$invoiceArray || (int)($invoiceArray['status'] ?? 0) !== 1) {
            return response()->json([
                'status'  => false, 
                'message' => $this->getBaseTypeName() . ' tidak ditemukan atau sudah tidak aktif.'
            ]);
        }

        $grandTotal = (float)($invoiceArray['grandtotal'] ?? 0);
        $paidAmount = (float)($invoiceArray['paidamount'] ?? 0);
        $balanceDue = $grandTotal - $paidAmount;

        $detailRow = [
            'baseentry'      => $invoiceArray['docnum'],
            'docdate'        => $invoiceArray['docdate'] ?? '',
            'grandtotal'     => $grandTotal,
            'paidamount_old' => $paidAmount,
            'amountapplied'  => $balanceDue, 
            'remark'         => $invoiceArray['remark'] ?? ''
        ];

        return response()->json([
            'status'  => true,
            'header'  => [],
            'details' => [$detailRow]
        ]);
    }
}