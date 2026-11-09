<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Request;

class BaseMasterModel extends Authenticatable 
{
    protected array $column_order  = [];
    protected array $column_search = [];
    protected array $order         = [];
    protected array|string $column_select = '*';
    protected string $tableAlias   = '';

    public $timestamps = false; 
    
    protected static function booted()
    {
        static::created(function ($model) {
            $model->logInsertActivity();
        });

        static::updated(function ($model) {
            $model->logUpdateActivity();
        });
    }

    protected function getModuleCodeFromUri(): string
    {
        $segment = Request::segment(1) ?? $this->table;
        return strtoupper($segment);
    }

    protected function logInsertActivity()
    {
        $id = $this->getKey();
        if (is_array($id)) {
            $id = implode(',', $id);
        }

        if (class_exists(\App\Models\MUseractivity::class)) {
            \App\Models\MUseractivity::log('ADD', (string)$id);
        }
    }

    protected function logUpdateActivity()
    {
        $id = $this->getKey();
        if (is_array($id)) {
            $id = implode(',', $id);
        }

        $postData = Request::all();
        $action = 'EDIT';
        if (isset($postData['status'])) {
            $action = ((int)$postData['status'] === 1) ? 'ACTIVE' : 'NONACTIVE';
        }

        if (class_exists(\App\Models\MUseractivity::class)) {
            \App\Models\MUseractivity::log($action, (string)$id);
        }
    }

    protected function queryDatatable($query)
    {
        // HIT
    }

    protected function get_datatables_query()
    {
        $query = !empty($this->tableAlias) 
            ? static::query()->from($this->table . ' as ' . $this->tableAlias) 
            : static::query();

        $query->select($this->column_select);
        $this->queryDatatable($query);
        
        $searchValue = Request::post('search')['value'] ?? null;

        if ($searchValue && !empty($this->column_search)) {
            $query->where(function ($q) use ($searchValue) {
                foreach ($this->column_search as $i => $item) {
                    if ($i === 0) {
                        $q->where($item, 'LIKE', "%{$searchValue}%");
                    } else {
                        $q->orWhere($item, 'LIKE', "%{$searchValue}%");
                    }
                }
            });
        }

        $orderPost = Request::post('order');
        if (isset($orderPost) && !empty($this->column_order)) {
            $targetColumn = $this->column_order[$orderPost['0']['column']] ?? null;
            if ($targetColumn) {
                $query->orderBy($targetColumn, $orderPost['0']['dir']);
            }
        } elseif (!empty($this->order)) {
            $key = array_key_first($this->order);
            $query->orderBy($key, $this->order[$key]);
        }

        return $query;
    }

    public function get_datatables()
    {
        $query = $this->get_datatables_query();
        $length = Request::post('length') ?? -1;
        $start  = Request::post('start') ?? 0;

        if ((int)$length !== -1) {
            $query->skip($start)->take($length);
        }
        
        return $query->get();
    }

    public function count_filtered(): int
    {
        return $this->get_datatables_query()->count();
    }

    public function count_all(): int
    {
        if (!empty($this->tableAlias)) {
            $query = static::query()->from($this->table . ' as ' . $this->tableAlias);
            $this->queryDatatable($query);
            return $query->count();
        }
        return static::count();
    }

    public function simpan($data)
    {
        return static::create($data);
    }

    public function update_data($id, $data)
    {
        $record = static::find($id);
        if ($record) {
            $record->update($data);
            return true;
        }
        return false;
    }

    public static function filters(array $conditions = []): array
    {
        $query = static::query();
        foreach ($conditions as $field => $value) {
            if ($value !== null && $value !== '') {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
        }
        return $query->get()->toArray();
    }
}