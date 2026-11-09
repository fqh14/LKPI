<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class MItemgroup extends BaseMasterModel
{
    protected $table           = 'itemgroup';
    protected $primaryKey      = 'itemgroupcode';
    public $incrementing       = false;
    protected $keyType         = 'string';

    protected $fillable   = [
        'itemgroupcode','itemgroupname','status','createddate','updateddate', 'createdby', 'updateby',
        'expense-acct','revenue-acct','inventory-acct','costgoodssold-acct','allocation-acct',
        'variance-acct','pricedifference-acct','neginvenadjust-acct','invenoffsetdec-acct',
        'invenoffsetinc-acct','salesreturn-acct','exchangerate-acct','goodsclearing-acct',
        'ledgerdec-acct','ledgerinc-acct','wipinven-acct','wipinvenvariance-acct',
        'wipoffset-acct','invenoffset-acct','expenseclear-acct','stockintransit-acct',
        'shippedgoods-acct','salescredit-acct','purchasecredit-acct','vatin-acct','vatout-acct'
    ];

    protected array|string $column_select = ['itemgroupcode', 'itemgroupname', 'status'];
    protected array $column_order   = [null, 'itemgroupcode', 'itemgroupname', 'status', null];
    protected array $column_search  = ['itemgroupcode', 'itemgroupname'];
    protected array $order          = ['itemgroupcode' => 'asc'];

    public function getItemmaster()
    {
        return self::all()->toArray();
    }

    public function get_by_id($id)
    {
        return DB::table($this->table . ' as a')
            ->select([
                'a.*', 
                'a1.acctname as expense-acct', 
                'a2.acctname as revenue-acct', 
                'a3.acctname as inventory-acct', 
                'a4.acctname as costgoodssold-acct', 
                'a5.acctname as allocation-acct', 
                'a6.acctname as variance-acct',
                'a7.acctname as neginvenadjust-acct',
                'a8.acctname as pricedifference-acct',
                'a9.acctname as invenoffsetdec-acct',
                'a10.acctname as invenoffsetinc-acct',
                'a11.acctname as salesreturn-acct',
                'a12.acctname as exchangerate-acct',
                'a13.acctname as goodsclearing-acct',
                'a14.acctname as ledgerdec-acct',
                'a15.acctname as ledgerinc-acct',
                'a16.acctname as wipinven-acct',
                'a17.acctname as wipinvenvariance-acct',
                'a18.acctname as wipoffset-acct',
                'a19.acctname as invenoffset-acct',
                'a20.acctname as expenseclear-acct',
                'a21.acctname as stockintransit-acct',
                'a22.acctname as shippedgoods-acct',
                'a23.acctname as salescredit-acct',
                'a24.acctname as purchasecredit-acct',
                'a25.acctname as vatin-acct',
                'a26.acctname as vatout-acct'
            ])
            ->leftJoin('account as a1', 'a1.acctcode', '=', 'a.expense-acct')
            ->leftJoin('account as a2', 'a2.acctcode', '=', 'a.revenue-acct')
            ->leftJoin('account as a3', 'a3.acctcode', '=', 'a.inventory-acct')
            ->leftJoin('account as a4', 'a4.acctcode', '=', 'a.costgoodssold-acct')
            ->leftJoin('account as a5', 'a5.acctcode', '=', 'a.allocation-acct')
            ->leftJoin('account as a6', 'a6.acctcode', '=', 'a.variance-acct')
            ->leftJoin('account as a7', 'a7.acctcode', '=', 'a.neginvenadjust-acct')
            ->leftJoin('account as a8', 'a8.acctcode', '=', 'a.pricedifference-acct')
            ->leftJoin('account as a9', 'a9.acctcode', '=', 'a.invenoffsetdec-acct')
            ->leftJoin('account as a10', 'a10.acctcode', '=', 'a.invenoffsetinc-acct')
            ->leftJoin('account as a11', 'a11.acctcode', '=', 'a.salesreturn-acct')
            ->leftJoin('account as a12', 'a12.acctcode', '=', 'a.exchangerate-acct')
            ->leftJoin('account as a13', 'a13.acctcode', '=', 'a.goodsclearing-acct')
            ->leftJoin('account as a14', 'a14.acctcode', '=', 'a.ledgerdec-acct')
            ->leftJoin('account as a15', 'a15.acctcode', '=', 'a.ledgerinc-acct')
            ->leftJoin('account as a16', 'a16.acctcode', '=', 'a.wipinven-acct')
            ->leftJoin('account as a17', 'a17.acctcode', '=', 'a.wipinvenvariance-acct')
            ->leftJoin('account as a18', 'a18.acctcode', '=', 'a.wipoffset-acct')
            ->leftJoin('account as a19', 'a19.acctcode', '=', 'a.invenoffset-acct')
            ->leftJoin('account as a20', 'a20.acctcode', '=', 'a.expenseclear-acct')
            ->leftJoin('account as a21', 'a21.acctcode', '=', 'a.stockintransit-acct')
            ->leftJoin('account as a22', 'a22.acctcode', '=', 'a.shippedgoods-acct')
            ->leftJoin('account as a23', 'a23.acctcode', '=', 'a.salescredit-acct')
            ->leftJoin('account as a24', 'a24.acctcode', '=', 'a.purchasecredit-acct')
            ->leftJoin('account as a25', 'a25.acctcode', '=', 'a.vatin-acct')
            ->leftJoin('account as a26', 'a26.acctcode', '=', 'a.vatout-acct')
            ->where('a.itemgroupcode', $id) 
            ->first();
    }
}