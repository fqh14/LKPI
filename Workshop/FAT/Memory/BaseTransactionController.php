<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Dompdf\Dompdf;
use Dompdf\Options;

abstract class BaseTransactionController extends BaseController
{
    protected $model;
    protected string $moduleCode = '';

    protected abstract function getTitle(): string;
    protected abstract function getBaseRoute(): string;
    protected abstract function getTxnNumberField(): string; 
    protected abstract function getTableColumns(): array;
    protected abstract function getDetailFields(): array;
    protected abstract function generateTxnNumber(): string;

    protected bool $allowDuplicate = true;

    protected bool $showBtnView   = true;
    protected bool $showBtnEdit   = true;
    protected bool $showBtnActive = true;
    protected bool $showBtnClose  = true;
    protected bool $showBtnCancel = true;
    protected bool $showBtnPrint  = true;

    protected function getDateField(): string { return ''; }
    protected function prepareHeaderData(string $txnNumber, array $postData): array { return []; }
    protected function prepareDetailsData(string $txnNumber, array $postData): array { return []; }
    protected function validateTransaction(array $postData): ?string { return null; }
    protected function getExtraColumns($item): array { return []; }
    protected function getExtraFields($row = null): array { return []; }
    protected function getExcelMapping(): array { return []; }

    protected function getModuleCode(): string 
    {
        return !empty($this->moduleCode) ? $this->moduleCode : strtoupper($this->getBaseRoute());
    }

    protected function guardModuleAccess(string $action = 'isread'): ?RedirectResponse
    {
        if (!Session::has('username')) {
            return redirect()->to(url('/'));
        }

        if (!checkModuleAccess($this->getModuleCode(), $action)) {
            return redirect()->to(url('dashboard'))->with('error_msg', 'Akses ditolak.');
        }
        return null;
    }

    protected function guardAuthenticatedView(): ?RedirectResponse
    {
        if (!Session::has('username')) {
            return redirect()->to(url('/'));
        }
        return null;
    }

    protected function getFieldValue($item, ?string $field)
    {
        if (empty($field)) {
            return null;
        }
        return is_object($item) ? ($item->{$field} ?? null) : ($item[$field] ?? null);
    }

    protected function ajaxResponse($status, string $message, array $extra = []): JsonResponse
    {
        $statusValue = is_bool($status) ? ($status ? 'success' : 'error') : $status;

        return response()->json(array_merge([
            'status'  => $statusValue,
            'msg'     => $message,  
            'message' => $message    
        ], $extra));
    }

    protected function validateAjaxRequest(Request $request): ?JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }
        if (!Session::has('username')) {
            return $this->ajaxResponse('error', 'Sesi Anda telah berakhir, silakan login kembali.');
        }
        return null;
    }

    protected function getFilteredDetailFields(string $action): array
    {
        $fields = $this->getDetailFields();
        if (!is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, function ($col) use ($action) {
            if (isset($col['hide_in']) && is_array($col['hide_in']) && in_array($action, $col['hide_in'], true)) {
                return false;
            }

            if (isset($col['only']) && is_array($col['only']) && !in_array($action, $col['only'], true)) {
                return false;
            }

            return true;
        }));
    }

    public function index()
    {
        if ($guard = $this->guardModuleAccess('isread')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;
        
        $status   = Session::get('status');
        $errorMsg = Session::get('error_msg');

        if ($errorMsg) {
            $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . e($errorMsg) . "<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div>";
        } else {
            $msg = $status ? $this->buildAlert($status, Session::get('fa_id'), Session::get('fa_name')) : '';
        }

        return view('basetransaction.index', [
            'title'         => $this->getTitle(),
            'base_route'    => $this->getBaseRoute(),
            'table_columns' => $this->getTableColumns(),
            'message'       => $msg,
            'usercode'      => Session::get('usercode'),
            'name'          => Session::get('name'),
            'can_add'       => checkModuleAccess($this->getModuleCode(), 'isadd'), 
        ]);
    }

    public function ajax_list(Request $request): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        $list       = $this->model->get_datatables();
        $data       = [];
        $no         = (int)($request->post('start') ?? 0);
        $primaryKey = $this->model->primaryKey ?? $this->getTxnNumberField();
        $dateField  = $this->getDateField();

        foreach ($list as $item) {
            $id        = $this->getFieldValue($item, $primaryKey);
            $txnNumber = $this->getFieldValue($item, $this->getTxnNumberField());
            $status    = $this->getFieldValue($item, 'status');
            $tanggal   = $this->getFieldValue($item, $dateField);

            $row = [
                ++$no,
                e($txnNumber),
                e($tanggal),
            ];

            $row   = array_merge($row, $this->getExtraColumns($item));
            $row[] = $this->renderStatusBadge($status);
            $row[] = $this->renderActions($id, $status);

            $data[] = $row;
        }

        return response()->json([
            "draw"            => (int)$request->post('draw'),
            "recordsTotal"    => (int)$this->model->count_all(),
            "recordsFiltered" => (int)$this->model->count_filtered(),
            "data"            => $data
        ]);
    }

    public function add()
    {
        if ($guard = $this->guardModuleAccess('isadd')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        return view('basetransaction.add', [
            'title'             => $this->getTitle(),
            'base_route'        => $this->getBaseRoute(),
            'allow_duplicate'   => $this->allowDuplicate,
            'action_url'        => $this->getBaseRoute() . '/saveadd',
            'field_code'        => $this->getTxnNumberField(),
            'field_date'        => $this->getDateField(),
            'show_import'       => $this->showImport ?? true,
            'show_remark'       => $this->showRemark ?? true,
            'show_summary'      => $this->showSummary ?? true,
            'show_freight'      => $this->showFreight ?? true,
            'show_dpp'          => $this->showDpp ?? true,
            'show_btndraft'     => $this->showBtndraft ?? true,
            'current_number'    => $this->generateTxnNumber(),
            'extra'             => $this->getExtraFields(null),
            'detail_columns'    => $this->getFilteredDetailFields('add'),
            'message'           => Session::get('message') ?? ''
        ]);
    }

    public function saveadd(Request $request): JsonResponse
    {
        if (!$request->isMethod('post')) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        $validated = $request->validate($this->getRules());

        $postData = $request->post();
        if ($valError = $this->validateTransaction($postData)) {
            return $this->ajaxResponse('error', $valError);
        }

        $txnNumberField = $this->getTxnNumberField(); 
        $txnNumber      = $postData[$txnNumberField] ?? '';

        if (empty($txnNumber)) {
            return $this->ajaxResponse('error', 'Nomor Transaksi tidak boleh kosong.');
        }

        $status      = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;
        $items       = $postData['items'] ?? [];
        
        unset($postData['items'], $postData['btn_submit']);

        $headerData  = $this->prepareHeaderOnCreate($postData, $status, $request);
        $detailsData = $this->prepareDetailsOnCreate($items, $txnNumber, $request);
        $saveResult  = $this->model->saveadd($headerData, $detailsData, $txnNumberField);

        if (($saveResult['status'] ?? '') === 'success') {
            $this->flashSession([
                'status'  => ($status === 9 ? 'draft' : 'add'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);
            return $this->ajaxResponse('success', 'Berhasil disimpan', ['redirect' => url($this->getBaseRoute())]);
        }

        return $this->ajaxResponse('error', $saveResult['message'] ?? 'Gagal menyimpan data.');
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

        $fieldCode = $this->getTxnNumberField() ?? 'po_code';
        $fieldDate = $this->getDateField() ?? 'po_date';

        $docNumber = $headerArray[$fieldCode] 
            ?? $headerArray['po_code'] 
            ?? $headerArray['docnum'] 
            ?? $headerArray['txn_number'] 
            ?? $headerArray['no_po'] 
            ?? $id;

        $docRemark = $headerArray['remark'] 
            ?? $headerArray['catatan'] 
            ?? $headerArray['notes'] 
            ?? $headerArray['description'] 
            ?? '';

        $status = (string)($headerArray['status'] ?? '');
        if (in_array($status, ['0', '2'], true)) { 
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Transaksi yang sudah selesai/batal tidak dapat diubah.');
        }

        $details = $this->model->get_details($docNumber) ?? [];

        return view('basetransaction.edit', [
            'title'             => 'Edit ' . $this->getTitle(),
            'base_route'        => $this->getBaseRoute(),
            'allow_duplicate'   => $this->allowDuplicate,
            'action_url'        => $this->getBaseRoute() . '/saveedit/' . $id,
            'field_code'        => $fieldCode,
            'field_date'        => $fieldDate,
            'docNumber'         => $docNumber,
            'docRemark'         => $docRemark,
            'header'            => $headerArray,
            'details'           => $details,
            'show_remark'       => $this->showRemark ?? true,
            'show_summary'      => $this->showSummary ?? true,
            'show_freight'      => $this->showFreight ?? true,
            'show_dpp'          => $this->showDpp ?? true,
            'show_btndraft'     => $this->showBtndraft ?? true,
            'extra'             => $this->getExtraFields($headerArray),
            'detail_columns'    => $this->getFilteredDetailFields('edit') ?? [],
            'message'           => Session::get('message') ?? Session::get('error_msg') ?? ''
        ]);
    }

    public function saveedit(Request $request, $id): JsonResponse
    {
        if (!checkModuleAccess($this->getModuleCode(), 'isedit')) {
            return $this->ajaxResponse('error', 'Akses memperbarui data ditolak.');
        }

        if (!$request->isMethod('post')) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        $header = $this->model->find($id);
        if (!$header) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        $validated = $request->validate($this->getRules());

        $postData = $request->post();
        if ($valError = $this->validateTransaction($postData)) {
            return $this->ajaxResponse('error', $valError);
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') 
            ? $header->toArray() 
            : (array)$header;

        $fieldCode = $this->getTxnNumberField();
        $txnNumber = $headerArray[$fieldCode] 
            ?? $headerArray['docnum'] 
            ?? $postData[$fieldCode] 
            ?? $id;

        $btnSubmit   = $postData['btn_submit'] ?? $postData['btn_submit_action'] ?? 'save';
        
        $headerData  = $this->prepareHeaderData($txnNumber, $postData);
        $detailsData = $this->prepareDetailsData($txnNumber, $postData);

        unset($headerData['docdate'], $headerData['createddate'], $headerData['createdby']);

        $headerData['updateddate'] = date('Y-m-d H:i:s');
        $headerData['updatedby']   = Session::get('usercode');

        if ((int)$headerArray['status'] === 9) {
            $headerData['status'] = ($btnSubmit === 'draft') ? 9 : 1;
            if ($headerData['status'] === 1) {
                $headerData['approveddate'] = date('Y-m-d H:i:s');
                $headerData['approvedby']   = Session::get('usercode');
            }
        }
        
        $editResult = $this->model->saveedit($id, $headerData, $detailsData);
        if (($editResult['status'] ?? '') === 'error') {
            return $this->ajaxResponse('error', $editResult['message'] ?? 'Gagal memperbarui data.');
        }

        $newStatus = $headerData['status'] ?? $headerArray['status'];
        $this->flashSession([
            'status'  => ((int)$newStatus === 9 ? 'draft' : 'edit'), 
            'fa_id'   => $txnNumber, 
            'fa_name' => $this->getTitle()
        ]);

        return $this->ajaxResponse('success', 'Berhasil diperbarui', ['redirect' => url($this->getBaseRoute())]);
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
        $details   = $this->model->get_details($docNumber) ?? [];

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
            'detail_columns' => $this->getFilteredDetailFields('view'),
            'summary' => [
                'subtotal'   => $headerArray['subtotal'] ?? 0,
                'discount'   => $headerArray['discountamount'] ?? 0,
                'dpp'        => $headerArray['dppamount'] ?? 0, 
                'tax'        => $headerArray['taxamount'] ?? 0, 
                'freight'    => $headerArray['freighttotal'] ?? 0,
                'grandtotal' => $headerArray['grandtotal'] ?? 0,
            ]
        ]);
    }

    protected function renderStatusModal($id, string $viewPath, array $config, Request $request): Response
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }
        
        $item = $this->model->find($id);
        if (!$item) {
            return response($this->renderHtmlError());
        }

        return response(view($viewPath, [
            'title'            => $this->getTitle(),
            'txn_number_field' => $this->getTxnNumberField(),
            'modeldata'        => (array)$item,
            'config'           => $config
        ]));
    }

    protected function processStatusChange(
        $id, 
        int $newStatus, 
        int $forbiddenStatus, 
        string $alreadyErrorMsg, 
        string $successMsgTemplate, 
        string $dbErrorMsg, 
        ?string $customModelMethod = null,
        string $requiredAccess = 'isedit',
        Request $request
    ): JsonResponse {
        
        if ($validation = $this->validateAjaxRequest($request)) {
            return $validation;
        }

        if (!checkModuleAccess($this->getModuleCode(), $requiredAccess)) {
            return $this->ajaxResponse('error', 'Akses ditolak untuk tindakan ini.');
        }

        $item = $this->model->find($id);
        if (!$item) {
            return $this->ajaxResponse('error', 'Data transaksi tidak ditemukan.');
        }

        $itemArray = (array)$item;
        if ((int)($itemArray['status'] ?? -1) === $forbiddenStatus) {
            return $this->ajaxResponse('error', $alreadyErrorMsg);
        }

        $updateHeader = $this->prepareHeaderOnStatusChange($itemArray, $newStatus, $request);

        try {
            $isSuccess = $customModelMethod 
                ? $this->model->{$customModelMethod}($id, $updateHeader) 
                : $this->model->update($id, $updateHeader);

            if ($isSuccess) {
                $fieldCode = $this->getTxnNumberField();
                $txnNo = $itemArray[$fieldCode] 
                    ?? $itemArray['docnum'] 
                    ?? $itemArray['txn_number'] 
                    ?? $itemArray['po_code'] 
                    ?? $id;

                $formattedMessage = sprintf($successMsgTemplate, e($txnNo));
                return $this->ajaxResponse('success', $formattedMessage);
            }

            return $this->ajaxResponse('error', $dbErrorMsg);
        } catch (\Exception $e) {
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function popactive(Request $request, $id): Response
    {
        if ($guard = $this->guardModuleAccess('isactive')) return $guard;
        return $this->renderStatusModal($id, 'basetransaction.popactive', [
            'action_url'  => url($this->getBaseRoute() . "/saveactive/$id"),
            'title'       => "Aktifkan " . $this->getTitle(),
            'btn_class'   => "btn-success",
            'btn_text'    => "Aktif",
            'icon_class'  => "fa-check-circle text-success",
            'action_word' => "mengaktifkan"
        ], $request);
    }

    public function popclose(Request $request, $id): Response
    {
        if ($guard = $this->guardModuleAccess('isclose')) return $guard;
        return $this->renderStatusModal($id, 'basetransaction.popclose', [
            'action_url'  => url($this->getBaseRoute() . "/saveclose/$id"),
            'title'       => "Tutup " . $this->getTitle(),
            'btn_class'   => "btn-warning", 
            'btn_text'    => "Close",
            'icon_class'  => "fa-times-circle text-warning",
            'action_word' => "menutup"
        ], $request);
    }

    public function popcancel(Request $request, $id): Response
    {
        if ($guard = $this->guardModuleAccess('iscancel')) return $guard;
        return $this->renderStatusModal($id, 'basetransaction.popcancel', [
            'action_url'  => url($this->getBaseRoute() . "/savecancel/$id"),
            'title'       => "Batalkan " . $this->getTitle(),
            'btn_class'   => "btn-danger",
            'btn_text'    => "Cancel",
            'icon_class'  => "fa-times-circle text-danger",
            'action_word' => "membatalkan"
        ], $request);
    }

    public function popstart(Request $request, $id): Response
    {
        if ($guard = $this->guardModuleAccess('isedit')) return $guard;
        return $this->renderStatusModal($id, 'basetransaction.popstart', [
            'action_url'  => url($this->getBaseRoute() . "/savestart/$id"),
            'title'       => "Mulai " . $this->getTitle(),
            'btn_class'   => "btn-success",
            'btn_text'    => "Mulai",
            'icon_class'  => "fa-check-circle text-success",
            'action_word' => "memulai"
        ], $request);
    }

    public function popend(Request $request, $id): Response
    {
        if ($guard = $this->guardModuleAccess('isedit')) return $guard;
        return $this->renderStatusModal($id, 'basetransaction.popend', [
            'action_url'  => url($this->getBaseRoute() . "/saveend/$id"),
            'title'       => "Selesaikan " . $this->getTitle(),
            'btn_class'   => "btn-warning",
            'btn_text'    => "Close",
            'icon_class'  => "fa-times-circle text-warning",
            'action_word' => "menyelesaikan"
        ], $request);
    }

    public function saveactive(Request $request, $id): JsonResponse
    {
        return $this->processStatusChange(
            $id, 1, 1, 
            'Transaksi ini sudah dalam status aktif.',
            '<div class="alert alert-success alert-dismissible fade show" role="alert">Nomor Transaksi: <strong>%s</strong> berhasil diaktifkan kembali.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>',
            'Gagal mengaktifkan kembali status transaksi di sistem.',
            'saveactive', 
            'isactive',
            $request
        );
    }

    public function saveclose(Request $request, $id): JsonResponse
    {
        return $this->processStatusChange(
            $id, 2, 2, 
            'Transaksi ini sudah diselesaikan/ditutup sebelumnya.',
            '<div class="alert alert-warning alert-dismissible fade show" role="alert">Nomor Transaksi: <strong>%s</strong> berhasil ditutup (Close).<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>',
            'Gagal memperbarui status close transaksi di sistem.',
            'saveclose', 
            'isclose',
            $request
        );
    }

    public function savecancel(Request $request, $id): JsonResponse
    {
        return $this->processStatusChange(
            $id, 0, 0, 
            'Transaksi ini sudah dibatalkan sebelumnya.',
            '<div class="alert alert-danger alert-dismissible fade show" role="alert">Nomor Transaksi: <strong>%s</strong> berhasil dibatalkan (Cancel).<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>',
            'Gagal memproses pembatalan transaksi di database.',
            'savecancel',
            'iscancel',
            $request
        );
    }

    public function savestart(Request $request, $id): JsonResponse
    {
        if (!$request->isMethod('post') && !$request->ajax()) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        $header = $this->model->find($id);
        if (!$header) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') ? $header->toArray() : (array)$header;
        $status      = (int)($headerArray['status'] ?? 0);
        $startDate   = $headerArray['startdate'] ?? '';
        $today       = date('Y-m-d');

        if ($status === 9 && !empty($startDate) && $startDate === $today) {
            $updateData = [
                'status'       => 1,
                'approveddate' => date('Y-m-d H:i:s'),
                'approvedby'   => (int) (Session::get('usercode') ?? 1),
            ];
        } else {
            return $this->ajaxResponse('error', 'Syarat tanggal mulai tidak sama dengan hari ini atau status bukan Draft.');
        }

        DB::beginTransaction();

        try {
            $updateStatus = $this->model->update($id, $updateData);
            if (!$updateStatus) {
                throw new \Exception('Gagal memperbarui status transaksi.');
            }

            DB::commit();

            return $this->ajaxResponse('success', 'Transaksi berhasil dimulai dan diaktifkan.', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'DB Error: ' . $e->getMessage());
        }
    }

    public function saveend(Request $request, $id): JsonResponse
    {
        if (!$request->isMethod('post') && !$request->ajax()) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        $header = $this->model->find($id);
        if (!$header) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') ? $header->toArray() : (array)$header;
        $status      = (int)($headerArray['status'] ?? 0);
        $qtyTarget   = (float)($headerArray['qtytarget'] ?? 0);
        $postData    = $request->post();
        
        $qtyProses   = isset($postData['qtyproses']) ? (float)$postData['qtyproses'] : (float)($headerArray['qtyproses'] ?? 0);
        $endDate     = !empty($postData['enddate']) ? $postData['enddate'] : ($headerArray['enddate'] ?? date('Y-m-d'));

        if ($status === 1 && $qtyProses >= $qtyTarget) {
            $updateData = [
                'status'     => 2,
                'qtyproses'  => $qtyProses,
                'enddate'    => $endDate,
                'closeddate' => date('Y-m-d H:i:s'),
                'closedby'   => (int) (Session::get('usercode') ?? 1),
            ];
        } else {
            return $this->ajaxResponse('error', 'Qty Proses belum memenuhi syarat (harus >= Target) atau status dokumen bukan Aktif.');
        }

        DB::beginTransaction();

        try {
            $updateStatus = $this->model->update($id, $updateData);
            if (!$updateStatus) {
                throw new \Exception('Gagal menutup dokumen transaksi.');
            }

            DB::commit();

            return $this->ajaxResponse('success', 'Transaksi berhasil ditutup (Close) karena Qty Proses mencapai/melebihi Target.', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'DB Error: ' . $e->getMessage());
        }
    }

    protected function getPrintViewPath(): string
    {
        return 'print.' . $this->getBaseRoute();
    }

    public function printdoc($id)
    {
        if ($guard = $this->guardModuleAccess('isprint')) return $guard;

        if ($guard = $this->guardAuthenticatedView()) {
            return $guard;
        }

        $header = $this->model->find($id);
        if (!$header) {
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Data transaksi tidak ditemukan.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') 
            ? $header->toArray() 
            : (array)$header;

        $docNumber = $headerArray[$this->getTxnNumberField()] ?? $headerArray['docnum'] ?? $id;

        if (method_exists($this->model, 'getWithDetails')) {
            $dataResult = $this->model->getWithDetails($docNumber);
            if (!$dataResult) {
                return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Detail data transaksi tidak ditemukan.');
            }
            $headerArray = $dataResult['header'];
            $details     = $dataResult['details'];
        } else {
            $details = method_exists($this->model, 'setPrint') 
                ? ($this->model->setPrint($docNumber) ?? []) 
                : ($this->model->get_details($docNumber) ?? []);
        }

        $html = view($this->getPrintViewPath(), [
            'title'    => 'Print ' . $this->getTitle(),
            'header'   => $headerArray,
            'details'  => $details,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true); 
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();
        
        $fileName = $docNumber . '.pdf';
        $dompdf->stream($fileName, ["Attachment" => false]);
    }

    public function gettemplates(): array
    {
        return [
            'default' => $this->getBaseRoute() . '.xlsx'
        ];
    }

    public function downloadtemplate(string $type = 'default')
    {
        $templates = $this->getTemplates();
        if (!array_key_exists($type, $templates)) {
            return redirect()->back()->with('error_msg', "Tipe template '{$type}' tidak ditemukan.");
        }

        $fileName = $templates[$type];
        $filePath = public_path('assets/template/' . $fileName);
        if (!file_exists($filePath)) {
            return redirect()->back()->with('error_msg', "File template '{$fileName}' belum di-upload di server.");
        }
        return response()->download($filePath, $fileName);
    }

    public function importexcel(Request $request): JsonResponse
    {
        if (!$request->isMethod('post')) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        $file = $request->file('excel_file');
        if (!$file || !$file->isValid()) {
            return $this->ajaxResponse('error', 'File tidak valid atau tidak ditemukan.');
        }

        if (!in_array($file->getClientOriginalExtension(), ['xls', 'xlsx'], true)) {
            return $this->ajaxResponse('error', 'Format file wajib .xls atau .xlsx');
        }

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray();

            if (count($rows) < 2) {
                return $this->ajaxResponse('error', 'File Excel kosong atau tidak memiliki data baris.');
            }

            $excelHeaders = array_map('trim', $rows[0]);
            $mapping      = $this->getExcelMapping();

            if (empty($mapping)) {
                return $this->ajaxResponse('error', 'Konfigurasi mapping Excel belum diset di Controller ini.');
            }

            $items = [];
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row))) {
                    continue;
                }

                $item = [];
                foreach ($mapping as $excelHeaderName => $dbKey) {
                    $colIndex = array_search($excelHeaderName, $excelHeaders, true);

                    if ($colIndex !== false) {
                        $val = $row[$colIndex];
                        if (str_contains($dbKey, 'date') && !empty($val)) {
                            $val = is_numeric($val) 
                                ? ExcelDate::excelToDateTimeObject($val)->format('Y-m-d H:i:s')
                                : date('Y-m-d H:i:s', strtotime($val));
                        }
                        $item[$dbKey] = $val;
                    } else {
                        $item[$dbKey] = null;
                    }
                }
                $items[] = $item;
            }

            $postData = [
                $this->getDateField() => $request->post($this->getDateField()) ?? date('Y-m-d H:i:s'),
                'items'               => $items
            ];

            if ($valError = $this->validateTransaction($postData)) {
                return $this->ajaxResponse('error', 'Gagal Validasi Excel: ' . $valError);
            }

            $txnNumberField = $this->getTxnNumberField();
            $txnNumber      = $this->generateTxnNumber();

            $headerData  = $this->prepareHeaderOnCreate($postData, 9, $request); 
            $headerData[$txnNumberField] = $txnNumber;

            $detailsData = $this->prepareDetailsOnCreate($items, $txnNumber, $request);
            $saveResult  = $this->model->saveadd($headerData, $detailsData, $txnNumberField);
            
            if (($saveResult['status'] ?? '') === 'success') {
                $this->flashSession([
                    'status'  => 'add', 
                    'fa_id'   => $txnNumber, 
                    'fa_name' => $this->getTitle()
                ]);

                return $this->ajaxResponse(
                    'success', 
                    "Berhasil mengimpor " . count($items) . " baris data ke dokumen: {$txnNumber}", 
                    ['redirect' => url($this->getBaseRoute())]
                );
            }
            return $this->ajaxResponse('error', $saveResult['message'] ?? 'Gagal menyimpan transaksi ke database.');

        } catch (\Exception $e) {
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    protected function saveDetails(array $detailsData): void
    {
        DB::table($this->model->table . 'detail')->insert($detailsData);
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request): array
    {
        $postData['status']      = $status;
        $postData['createdby']   = Session::get('usercode') ?? 99;
        $postData['createddate'] = date('Y-m-d H:i:s');
        if (!empty($postData['docdate']) && strlen($postData['docdate']) === 10) {
            $postData['docdate'] .= ' ' . date('H:i:s');
        }
        return $postData;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, Request $request): array
    {
        $formattedDetails = [];
        $fk               = $this->model->detailForeignKey;
        $usercode         = Session::get('usercode') ?? 'System';
        $now              = date('Y-m-d H:i:s');

        foreach ($items as $item) {
            $item[$fk]           = $txnNumber;
            $item['createdby']   = $usercode;
            $item['createddate'] = $now;
            $formattedDetails[]  = $item;
        }
        return $formattedDetails;
    }

    protected function prepareHeaderOnStatusChange(array $currentHeader, int $newStatus, Request $request): array
    {
        $usercode = Session::get('usercode') ?? Session::get('username') ?? 'System';
        $now      = date('Y-m-d H:i:s');

        return match ($newStatus) {
            1 => [
                'status'         => 1, 
                'approveddate'   => $now, 
                'approvedby'     => $usercode,
                'approvedremark' => $request->post('approvedremark') ?? '',
                'closeddate'     => null, 
                'closedby'       => null,
                'closedremark'   => null
            ],
            2 => [
                'status'       => 2, 
                'closeddate'   => $now, 
                'closedby'     => $usercode,
                'closedremark' => $request->post('closedremark') ?? ''
            ],
            0 => [
                'status'         => 0, 
                'canceleddate'   => $now, 
                'canceledby'     => $usercode,
                'canceledremark' => $request->post('canceledremark') ?? ''
            ],
            default => []
        };
    }

    protected function renderStatusBadge($status): string
    {
        $statusMap = [
            '0' => ['class' => 'badge-danger text-white',    'text' => ' CANCEL '],
            '1' => ['class' => 'badge-success text-white',   'text' => ' AKTIF '],
            '2' => ['class' => 'badge-info text-white',      'text' => ' CLOSE '],
            '9' => ['class' => 'badge-secondary text-white', 'text' => ' DRAFT '],
        ];

        $badge = $statusMap[(string)$status] ?? ['class' => 'badge-light text-dark', 'text' => ' UNKNOWN '];
        return sprintf('<span class="badge %s font-weight-bold px-2 py-1">%s</span>', $badge['class'], $badge['text']);
    }

    protected function renderActions($id, $currentStatus): string
    {
        $route     = $this->getBaseRoute();
        $statusStr = (string)$currentStatus;
        
        $canRead   = checkModuleAccess($this->getModuleCode(), 'isread');
        $canEdit   = checkModuleAccess($this->getModuleCode(), 'isedit');
        $canActive = checkModuleAccess($this->getModuleCode(), 'isactive');
        $canCancel = checkModuleAccess($this->getModuleCode(), 'iscancel'); 
        $canClose  = checkModuleAccess($this->getModuleCode(), 'isclose'); 
        $canPrint  = checkModuleAccess($this->getModuleCode(), 'isprint'); 
        $buttons   = ''; 
        
        if ($canRead && $this->showBtnView) {
            $buttons .= sprintf(
                '<a href="%s" class="btn btn-circle btn-sm btn-primary mr-1" title="View"><i class="fas fa-eye"></i></a>',
                url("$route/view/$id")
            );
        }

        if ($canEdit && in_array($statusStr, ['1', '9'], true) && $this->showBtnEdit) {
            $buttons .= sprintf(
                '<a href="%s" class="btn btn-circle btn-sm btn-info mr-1" title="Edit"><i class="fas fa-pencil-alt"></i></a>',
                url("$route/edit/$id")
            );
        }

        if ($canActive && $statusStr === '9' && $this->showBtnActive) {
            $buttons .= sprintf(
                '<button type="button" class="btn btn-circle btn-sm btn-success btn-trigger-modal mr-1" title="Active" data-url="%s"><i class="fas fa-check"></i></button>',
                url("$route/popactive/$id")
            );
        }
        
        if ($canClose && $statusStr === '1' && $this->showBtnClose) {
            $buttons .= sprintf(
                '<button type="button" class="btn btn-circle btn-sm btn-warning btn-trigger-modal mr-1" title="Close" data-url="%s"><i class="fas fa-ban"></i></button>',
                url("$route/popclose/$id")
            );
        }

        if ($canCancel && in_array($statusStr, ['1', '9'], true) && $this->showBtnCancel) {
            $buttons .= sprintf(
                '<button type="button" class="btn btn-circle btn-sm btn-danger btn-trigger-modal mr-1" title="Cancel" data-url="%s"><i class="fas fa-times"></i></button>',
                url("$route/popcancel/$id")
            );
        }

        if ($canPrint && $statusStr !== '9' && $this->showBtnPrint) {
            $buttons .= sprintf(
                '<a href="%s" target="_blank" class="btn btn-circle btn-sm btn-secondary" title="Print"><i class="fas fa-print"></i></a>',
                url("$route/printdoc/$id")
            );
        }   

        $buttons .= $this->renderCustomActions($id, $currentStatus);

        return $buttons !== '' ? $buttons : '<span class="text-muted">-</span>';
    }

    protected function renderCustomActions($id, $currentStatus): string
    {
        return '';
    }

    protected function buildAlert($action, $id, $name): string
    {
        $cfg = [
            'add'    => ['class' => 'success',   'text' => 'berhasil diproses dan disimpan'],
            'draft'  => ['class' => 'secondary', 'text' => 'berhasil disimpan sebagai Draft'],
            'edit'   => ['class' => 'info',      'text' => 'berhasil diperbarui'],
            'active' => ['class' => 'success',   'text' => 'berhasil diaktifkan'],
            'cancel' => ['class' => 'danger',    'text' => 'berhasil dibatalkan (Cancel)'],
            'close'  => ['class' => 'warning',   'text' => 'berhasil ditutup (Close)'] 
        ];
        $c = $cfg[$action] ?? ['class' => 'secondary', 'text' => ''];
        
        return "<div class='alert alert-{$c['class']} alert-dismissible fade show' role='alert'>Nomor Transaksi: <b>" . e($id) . "</b> {$c['text']}<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div>";
    }

    protected function redirectWithMessage($type, $id, $name): RedirectResponse
    {
        $this->flashSession(['status' => $type, 'fa_id' => $id, 'fa_name' => $name]);
        return redirect()->to(url($this->getBaseRoute()));
    }

    protected function formatOptions(?array $list, string $codeKey, string $nameKey): array
    {
        if (empty($list)) {
            return [];
        }

        $options = [];
        foreach ($list as $item) {
            $itemObj = (object)$item;
            $code    = $itemObj->{$codeKey} ?? '';
            $name    = $itemObj->{$nameKey} ?? '';

            if ($code !== '') {
                $options[$code] = $name;
            }
        }
        return $options;
    }

    protected function renderHtmlError(string $message = 'Data tidak ditemukan atau tautan tidak valid.'): string
    {
        return sprintf(
            '<div class="modal-body text-center p-4"><i class="fas fa-exclamation-circle text-danger fa-3x"></i><p class="mt-2 mb-0">%s</p></div>',
            e($message)
        );
    }

    protected function getRules(): array
    {
        return [
            'docdate' => 'required|date_format:Y-m-d',
        ];
    }

    protected function calculateHeaderTotals(array $postData): array
    {
        $items = $postData['items'] 
            ?? $postData['details'] 
            ?? $postData['detail'] 
            ?? $postData['sodetail'] 
            ?? [];

        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        $sumGrossItems     = 0;
        $sumDetailSubtotal = 0;

        if (is_array($items) && !empty($items)) {
            foreach ($items as $item) {
                $qty         = $this->parseNumber($item['qty'] ?? $item['quantity'] ?? 0);
                $unitprice   = $this->parseNumber($item['unitprice'] ?? $item['price'] ?? 0);
                $discPercent = $this->parseNumber($item['discount'] ?? $item['disc'] ?? 0); 

                $itemGross    = $qty * $unitprice;
                $discNominal  = $itemGross * ($discPercent / 100); 
                $itemSubtotal = $itemGross - $discNominal;

                $sumGrossItems     += $itemGross;
                $sumDetailSubtotal += $itemSubtotal; 
            }
        }

        $subtotal       = $sumGrossItems;
        $netSubtotal    = $sumDetailSubtotal; 
        $discountamount = $subtotal - $netSubtotal; 

        $taxcode = $postData['taxcode'] ?? '';

        if (!empty($taxcode)) {
            $dppamount  = (11 / 12) * $netSubtotal;
            $taxamount  = (12 / 100) * $dppamount;
            $grandtotal = $dppamount + $taxamount;
        } else {
            $dppamount  = $netSubtotal;
            $taxamount  = 0;
            $grandtotal = $netSubtotal;
        }

        $freightTotal = $this->parseNumber($postData['freighttotal'] ?? 0);
        $grandtotal  += $freightTotal;

        if ($subtotal == 0 && !empty($postData['subtotal'])) {
            $subtotal = $this->parseNumber($postData['subtotal']);
        }
        if ($discountamount == 0 && !empty($postData['discountamount'])) {
            $discountamount = $this->parseNumber($postData['discountamount']);
        }
        if ($dppamount == 0 && !empty($postData['dppamount'])) {
            $dppamount = $this->parseNumber($postData['dppamount']);
        }
        if ($taxamount == 0 && !empty($postData['taxamount'] ?? $postData['taxtotal'])) {
            $taxamount = $this->parseNumber($postData['taxamount'] ?? $postData['taxtotal']);
        }
        if ($grandtotal == 0 && !empty($postData['grandtotal'])) {
            $grandtotal = $this->parseNumber($grandtotal);
        }

        return [
            'subtotal'       => (float) $subtotal,
            'discountamount' => (float) $discountamount,
            'dppamount'      => (float) $dppamount,
            'taxamount'      => (float) $taxamount,     
            'taxtotal'       => (float) $taxamount,     
            'freighttotal'   => (float) $freightTotal,
            'grandtotal'     => (float) $grandtotal,
        ];
    }
    
    protected function handleJournal(string $txnNumber, int $status, array $headerData, array $detailData, string $sourceType): void
    {
        if (property_exists($this, 'mJournal') && $this->mJournal !== null) {
            try {
                if ($status === 1) {
                    $this->mJournal->updateJournal($sourceType, $txnNumber, $headerData, $detailData);
                } 
                else {
                    if (method_exists($this->mJournal, 'deleteByReference')) {
                        $this->mJournal->deleteByReference($txnNumber);
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception("Gagal memproses jurnal untuk '{$txnNumber}': " . $e->getMessage());
            }
        }
    }

    protected function flashSession(array $data): void
    {
        foreach ($data as $key => $value) {
            Session::flash($key, $value);
        }
    }
}