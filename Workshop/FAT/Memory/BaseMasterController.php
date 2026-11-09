<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

abstract class BaseMasterController extends BaseController
{
    protected $model;
    protected string $moduleCode = '';
    protected bool $hasStatus = true;

    protected abstract function getTitle(): string;
    protected abstract function getBaseRoute(): string;
    protected abstract function getFieldCode(): string;
    protected abstract function getFieldName(): string;
    protected abstract function getTableName(): string;
    protected abstract function getTableColumns(): array;

    protected function getExtraFields(): array { return []; }
    protected function getExtraColumns($item): array { return []; }

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

    protected function ajaxResponse($status, string $message, array $extra = []): JsonResponse
    {
        $statusValue = is_bool($status) ? ($status ? 'success' : 'error') : $status;

        return response()->json(array_merge([
            'status'  => $statusValue,
            'msg'     => $message,  
            'message' => $message    
        ], $extra));
    }

    protected function flashSession(array $data): void
    {
        foreach ($data as $key => $value) {
            Session::flash($key, $value);
        }
    }

    public function index(Request $request)
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

        return view('basemaster.index', [
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

        $list = $this->model->get_datatables();
        $data = [];
        $no   = (int)($request->post('start') ?? 0);

        foreach ($list as $item) {
            $itemArray = is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
            $code = $itemArray[$this->getFieldCode()] ?? $item->{$this->getFieldCode()} ?? '';
            $name = $itemArray[$this->getFieldName()] ?? $item->{$this->getFieldName()} ?? '';
            
            $row = [
                ++$no,
                e($code),
                e($name),
            ];

            $row = array_merge($row, $this->getExtraColumns($item));

            if ($this->hasStatus) {
                $isActive = (int)($itemArray['status'] ?? $item->status ?? 1) === 1;
                $row[] = $isActive ? '<span class="badge bg-success text-white"> AKTIF </span>' : '<span class="badge bg-danger text-white"> NON AKTIF </span>';
                $row[] = $this->renderActions($code, $isActive);
            } else {
                $row[] = $this->renderActions($code, true);
            }

            $data[] = $row;
        }

        return response()->json([
            "draw"            => (int)$request->post('draw'),
            "recordsTotal"    => (int)$this->model->count_all(),
            "recordsFiltered" => (int)$this->model->count_filtered(),
            "data"            => $data
        ]);
    }

    public function popadd(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        if (!checkModuleAccess($this->getModuleCode(), 'isadd')) {
            return response($this->renderHtmlError('Akses tambah data ditolak.'));
        }

        return view('basemaster.popadd', [
            'title'      => $this->getTitle(),
            'action_url' => $this->getBaseRoute() . '/saveadd',
            'field_code' => $this->getFieldCode(),
            'field_name' => $this->getFieldName(),
            'extra'      => $this->getExtraFields()
        ]);
    }

    public function popedit(Request $request, $id)
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        if (!checkModuleAccess($this->getModuleCode(), 'isedit')) {
            return response($this->renderHtmlError('Akses ubah data ditolak.'));
        }
        
        $data = $this->model->find($id);
        if (!$data) {
            return response($this->renderHtmlError());
        }

        $modelData = is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : (array)$data;

        return view('basemaster.popedit', [
            'title'      => $this->getTitle(),
            'action_url' => $this->getBaseRoute() . '/saveedit/' . $id,
            'field_code' => $this->getFieldCode(),
            'field_name' => $this->getFieldName(),
            'extra'      => $this->getExtraFields(),
            'modeldata'  => $modelData 
        ]);
    }

    public function popstatus(Request $request, $id, $targetStatus)
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        if (!checkModuleAccess($this->getModuleCode(), 'isactive')) {
            return response($this->renderHtmlError('Akses ubah status data ditolak.'));
        }
        
        $item = $this->model->find($id);
        if (!$item) {
            return response($this->renderHtmlError());
        }

        $itemData = is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : (array)$item;

        return view('basemaster.popstatus', [
            'title'      => $this->getTitle(),
            'field_code' => $this->getFieldCode(),
            'field_name' => $this->getFieldName(),
            'modeldata'  => $itemData,
            'config'     => [
                'target_status' => $targetStatus,
                'action_url'    => $this->getBaseRoute() . "/savestatus/$id/$targetStatus",
                'title'         => ((int)$targetStatus === 1 ? "Aktifkan " : "Nonaktifkan ") . $this->getTitle(),
                'btn_class'     => ((int)$targetStatus === 1 ? "btn-success" : "btn-danger"),
                'btn_text'      => ((int)$targetStatus === 1 ? "Aktifkan" : "Nonaktifkan"),
                'icon_class'    => ((int)$targetStatus === 1 ? "fas fa-check-circle text-success" : "fas fa-times-circle text-danger"),
                'action_word'   => ((int)$targetStatus === 1 ? "mengaktifkan" : "menonaktifkan")
            ]
        ]);
    }

    public function saveadd(Request $request): JsonResponse
    {
        if (!$request->isMethod('post')) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }
        
        $validated = $request->validate($this->getRules());

        $codeKey = $this->getFieldCode();
        $nameKey = $this->getFieldName();

        $data = [
            $codeKey      => $request->post($codeKey),
            $nameKey      => $request->post($nameKey),
            'status'      => (int)($request->post('action') ?? 1),
            'createddate' => date('Y-m-d H:i:s'),
            'createdby'   => Session::get('usercode') ?? 1
        ];

        foreach ($this->getExtraFields() as $field) {
            $fieldName = is_array($field) ? ($field['name'] ?? '') : $field;
            if (!empty($fieldName)) {
                $data[$fieldName] = $request->post($fieldName);
            }
        }

        $this->model->simpan($data);
        
        $this->flashSession([
            'status'  => 'add',
            'fa_id'   => $data[$codeKey],
            'fa_name' => $data[$nameKey]
        ]);

        return $this->ajaxResponse('success', $this->buildAlert('add', $data[$codeKey], $data[$nameKey]));
    }

    public function saveedit(Request $request, $id): JsonResponse
    {
        if (!checkModuleAccess($this->getModuleCode(), 'isedit')) {
            return $this->ajaxResponse('error', 'Akses ubah data ditolak.');
        }       
        if (!$request->isMethod('post')) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }
        
        $validated = $request->validate($this->getRules(true));

        $nameKey = $this->getFieldName();
        $data = [
            $nameKey      => $request->post($nameKey),
            'updateddate' => date('Y-m-d H:i:s'),
            'updatedby'   => Session::get('usercode') ?? 1
        ];

        foreach ($this->getExtraFields() as $field) {
            $fieldName = is_array($field) ? ($field['name'] ?? '') : $field;
            if (!empty($fieldName)) {
                $data[$fieldName] = $request->post($fieldName);
            }
        }

        $this->model->update_data($id, $data);

        $item = $this->model->find($id);
        $itemArray = is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
        $nameVal = $itemArray[$nameKey] ?? $request->post($nameKey);

        $this->flashSession([
            'status'  => 'update',
            'fa_id'   => $id,
            'fa_name' => $nameVal
        ]);

        return $this->ajaxResponse('success', $this->buildAlert('update', $id, $nameVal));
    }

    public function savestatus($id, $target): JsonResponse
    {
        if (!checkModuleAccess($this->getModuleCode(), 'isactive')) {
            return $this->ajaxResponse('error', 'Akses ubah status data ditolak.');
        }        
        
        $item = $this->model->find($id);
        if (!$item) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan.');
        }

        $this->model->update_data($id, [
            'updateddate' => date('Y-m-d H:i:s'),
            'updatedby'   => Session::get('usercode') ?? 1,
            'status'      => (int)$target
        ]);

        $itemArray = is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
        $nameVal = $itemArray[$this->getFieldName()] ?? '';
        
        $actionType = (int)$target === 1 ? 'enable' : 'disable';

        $this->flashSession([
            'status'  => $actionType,
            'fa_id'   => $id,
            'fa_name' => $nameVal
        ]);

        return $this->ajaxResponse('success', $this->buildAlert($actionType, $id, $nameVal));
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
                $options[$code] = $code . ' - ' . $name;
            }
        }
        return $options;
    }

    protected function renderActions($code, $isActive): string
    {
        $route     = $this->getBaseRoute();
        $canEdit   = checkModuleAccess($this->getModuleCode(), 'isedit');
        $canActive = checkModuleAccess($this->getModuleCode(), 'isactive'); 

        $buttons = '';

        if ($canEdit) {
            $buttons .= sprintf(
                '<button type="button" class="btn btn-circle btn-sm btn-info btn-trigger-modal mr-1" title="Edit" data-url="%s"><i class="fas fa-pencil-alt"></i></button>',
                url("$route/popedit/$code")
            );
        }
        
        if ($this->hasStatus && $canActive) {
            $buttons .= sprintf(
                '<button type="button" class="btn btn-circle btn-sm %s btn-trigger-modal mr-1" title="%s" data-url="%s"><i class="fas %s"></i></button>',
                $isActive ? 'btn-danger' : 'btn-success',
                $isActive ? 'Nonaktifkan' : 'Aktifkan',
                url("$route/popstatus/$code/" . ($isActive ? 0 : 1)),
                $isActive ? 'fa-ban' : 'fa-check'
            );
        }

        $buttons .= $this->renderCustomActions($code, $isActive);

        return $buttons !== '' ? $buttons : '<span class="text-muted">-</span>';
    }

    protected function renderCustomActions($code, $isActive): string
    {
        return '';
    }

    protected function getRules($isEdit = false): array
    {
        $code = $this->getFieldCode();
        $unique = $isEdit ? "" : "|unique:" . $this->getTableName() . "," . $code;
        
        return [
            $code => "required|min:1|max:20{$unique}",
            $this->getFieldName() => 'required|min:1|max:50'
        ];
    }

    protected function buildAlert($action, $id, $name): string
    {
        $cfg = [
            'add'     => ['class' => 'success',   'text' => 'berhasil disimpan'],
            'update'  => ['class' => 'info',      'text' => 'berhasil diperbarui'],
            'reset'   => ['class' => 'info',      'text' => 'password berhasil diperbarui'],
            'enable'  => ['class' => 'primary',   'text' => 'berhasil diaktifkan'],
            'disable' => ['class' => 'danger',    'text' => 'berhasil dinonaktifkan']
        ];
        $c = $cfg[$action] ?? ['class' => 'secondary', 'text' => ''];
        
        return "<div class='alert alert-{$c['class']} alert-dismissible fade show' role='alert'>Kode: <b>" . e($id) . "</b> - <b>" . e($name) . "</b> {$c['text']}<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div>";
    }

    protected function renderHtmlError(string $message = 'Data tidak ditemukan atau tautan tidak valid.'): string 
    { 
        return sprintf(
            '<div class="modal-body text-center p-4"><i class="fas fa-exclamation-circle text-danger fa-3x"></i><p class="mt-2 mb-0">%s</p></div>',
            e($message)
        );
    }
}