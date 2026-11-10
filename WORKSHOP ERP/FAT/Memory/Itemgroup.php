<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use App\Models\MItemgroup;
use App\Models\MAccount;

class Itemgroup extends BaseMasterController
{
    protected $model;
    protected MAccount $mAccount;

    protected function getTitle(): string { return 'Item Group'; }
    protected function getBaseRoute(): string { return 'itemgroup'; }
    protected function getFieldCode(): string { return 'itemgroupcode'; }
    protected function getFieldName(): string { return 'itemgroupname'; }
    protected function getTableName(): string { return 'itemgroup'; }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Kode', 'Nama', 'Status', 'Aksi']; 
    }

    private const ACCOUNT_FIELDS = [
        'expense-acct'          => 'Expense Account',
        'revenue-acct'          => 'Revenue Account',
        'inventory-acct'        => 'Inventory Account',
        'costgoodssold-acct'    => 'Cost of Goods Sold Account',
        'allocation-acct'       => 'Allocation Account',
        'variance-acct'         => 'Variance Account',
        'pricedifference-acct'  => 'Price Difference Account',
        'neginvenadjust-acct'   => 'Negative Inventory Account',
        'invenoffsetdec-acct'   => 'Inventory Offset Decrease Account',
        'invenoffsetinc-acct'   => 'Inventory Offset Increase Account',
        'salesreturn-acct'      => 'Sales Return Account',
        'exchangerate-acct'     => 'Exchange Rate Account',
        'goodsclearing-acct'    => 'Goods Clearing Account',
        'ledgerdec-acct'        => 'G/L Decrease Account',
        'ledgerinc-acct'        => 'G/L Increase Account',
        'wipinven-acct'         => 'WIP Inventory Account',
        'wipinvenvariance-acct' => 'WIP Inventory Variance Account',
        'wipoffset-acct'        => 'WIP Offset Account',
        'invenoffset-acct'      => 'Inventory Offset Account',
        'expenseclear-acct'     => 'Expense Clear Account',
        'stockintransit-acct'   => 'Stock in Transit Account',
        'shippedgoods-acct'     => 'Shipped Goods Account',
        'salescredit-acct'      => 'Sales Credit Account',
        'purchasecredit-acct'   => 'Purchase Credit Account',
        'vatin-acct'            => 'Vat In Account',
        'vatout-acct'           => 'Vat Out Account'
    ];

    public function __construct()
    {
        $this->model    = new MItemgroup();
        $this->mAccount = new MAccount();        
    }

    public function popadd(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        if (!checkModuleAccess($this->getModuleCode(), 'isadd')) {
            return response($this->renderHtmlError('Akses tambah data ditolak.'));
        }

        return view('itemgroup.popadd', array_merge($this->getMasterData(), [
            'title'      => $this->getTitle(),
            'action_url' => $this->getBaseRoute() . '/saveadd',
            'field_code' => $this->getFieldCode(),
            'field_name' => $this->getFieldName(),
            'extra'      => $this->getExtraFields()
        ]));
    }

    public function popedit(Request $request, $id)
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        if (!checkModuleAccess($this->getModuleCode(), 'isedit')) {
            return response($this->renderHtmlError('Akses ubah data ditolak.'));
        }
        
        $data = $this->model->get_by_id($id);
        if (!$data) {
            return response($this->renderHtmlError());
        }

        $dataArray = is_object($data) && method_exists($data, 'toArray') ? $data->toArray() : (array)$data;

        return view('itemgroup.popedit', array_merge($this->getMasterData(), [
            'title'      => $this->getTitle(),
            'action_url' => $this->getBaseRoute() . '/saveedit/' . $id,
            'field_code' => $this->getFieldCode(),
            'field_name' => $this->getFieldName(),
            'extra'      => $this->getExtraFields(),
            'modeldata'  => $dataArray,
            'itemgroup'  => $dataArray
        ]));
    }

    public function saveadd(Request $request): JsonResponse
    {
        if (!$request->isMethod('post')) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }
        
        $validated = $request->validate($this->getRules());

        $codeKey = $this->getFieldCode();
        $nameKey = $this->getFieldName();

        $data = array_merge($this->getInputData($request), [
            'status'      => 1,
            'createddate' => date('Y-m-d H:i:s'),
            'createdby'   => Session::get('usercode') ?? 1
        ]);

        if (method_exists($this->model, 'insert')) {
            $this->model->insert($data);
        } else {
            $this->model->simpan($data);
        }

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
        $data = array_merge($this->getInputData($request), [
            'updateddate' => date('Y-m-d H:i:s'),
            'updatedby'   => Session::get('usercode') ?? 1
        ]);

        $this->model->update_data($id, $data);

        $this->flashSession([
            'status'  => 'update',
            'fa_id'   => $id,
            'fa_name' => $data[$nameKey]
        ]);

        return $this->ajaxResponse('success', $this->buildAlert('update', $id, $data[$nameKey]));
    }

    private function getInputData(Request $request): array
    {
        $data = [
            $this->getFieldCode() => $request->post($this->getFieldCode()),
            $this->getFieldName() => $request->post($this->getFieldName()),
        ];

        foreach (array_keys(self::ACCOUNT_FIELDS) as $field) {
            $val = $request->post($field);
            $data[$field] = empty($val) ? null : $val;
        }

        return $data;
    }

    private function getMasterData(): array 
    { 
        $masterAccount = $this->mAccount->filters(['status' => 1]);
        $accountOptionsHtml = '';
        
        foreach ($masterAccount as $account) {
            $codeAccount = is_object($account) ? $account->acctcode : $account['acctcode'];
            $nameAccount = is_object($account) ? $account->acctname : $account['acctname'];
            
            $accountOptionsHtml .= sprintf(
                '<option value="%s">%s - %s</option>',
                e($codeAccount),
                e($codeAccount),
                e($nameAccount)
            );
        }

        return [
            'accountFields'      => self::ACCOUNT_FIELDS,
            'accountOptionsHtml' => $accountOptionsHtml 
        ]; 
    }
}