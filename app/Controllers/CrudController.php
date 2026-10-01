<?php

namespace App\Controllers;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

/**
 * Mantenedor genérico (listar / crear / editar / eliminar) vía AJAX.
 * Cada módulo solo declara título, modelo, columnas y campos.
 */
abstract class CrudController extends BaseController
{
    protected string $slug;          // segmento de URL
    protected string $title;         // "Gestión de ..."
    protected string $singular;      // "cliente"
    protected string $modelClass;
    /** Columnas SQL en las que busca el cuadro "Buscar". */
    protected array $searchColumns = [];
    /** Mapa clave de columna de la tabla => expresión SQL para ordenar. */
    protected array $sortMap = [];

    /** @return list<array{key:string,label:string,class?:string}> */
    abstract protected function columns(): array;

    /** @return list<array<string,mixed>> name,label,type,rules,options,help,required,col */
    abstract protected function fields(): array;

    protected function model(): Model
    {
        return new $this->modelClass();
    }

    protected function builder(): BaseBuilder
    {
        return db_connect()->table($this->model()->table);
    }

    protected function selectList(BaseBuilder $b): BaseBuilder
    {
        return $b;
    }

    public function index()
    {
        $config = [
            'slug'    => $this->slug,
            'singular' => $this->singular,
            'columns' => $this->columns(),
            'fields'  => $this->fields(),
            'urls'    => [
                'listar'   => site_url("{$this->slug}/listar"),
                'guardar'  => site_url("{$this->slug}/guardar"),
                'eliminar' => site_url("{$this->slug}/eliminar"),
            ],
        ];

        return view('crud/index', ['title' => $this->title, 'config' => $config, 'slug' => $this->slug]);
    }

    public function listar()
    {
        $req    = $this->request;
        $page   = max(1, (int) $req->getGet('page'));
        $per    = in_array((int) $req->getGet('per'), [10, 25, 50, 100], true) ? (int) $req->getGet('per') : 10;
        $search = trim((string) $req->getGet('search'));
        $sort   = (string) $req->getGet('sort');
        $dir    = strtolower((string) $req->getGet('dir')) === 'desc' ? 'DESC' : 'ASC';

        $total = $this->selectList($this->builder())->countAllResults();

        $apply = function (BaseBuilder $b) use ($search) {
            $b = $this->selectList($b);
            if ($search !== '' && $this->searchColumns) {
                $b->groupStart();
                foreach ($this->searchColumns as $i => $col) {
                    $i === 0 ? $b->like($col, $search) : $b->orLike($col, $search);
                }
                $b->groupEnd();
            }

            return $b;
        };

        $filtered = $apply($this->builder())->countAllResults();

        $b       = $apply($this->builder());
        $orderBy = $this->sortMap[$sort] ?? null;
        $b->orderBy($orderBy ?? $this->model()->table . '.id', $orderBy ? $dir : 'ASC');
        if ($orderBy) {
            $b->orderBy($this->model()->table . '.id', 'ASC');
        }
        $rows = $b->limit($per, ($page - 1) * $per)->get()->getResultArray();

        return $this->response->setJSON(['ok' => true, 'data' => $rows, 'total' => $total, 'filtered' => $filtered]);
    }

    public function guardar()
    {
        $id     = (int) $this->request->getPost('id');
        $model  = $this->model();
        $exists = $id > 0 ? $model->find($id) : null;
        if ($id > 0 && ! $exists) {
            return $this->fail('El registro ya no existe.', 404);
        }

        $rules = $labels = $data = [];
        foreach ($this->fields() as $f) {
            $name  = $f['name'];
            $spec  = $f['rules'] ?? 'permit_empty';
            $spec  = is_array($spec) ? ($exists ? $spec['edit'] : $spec['create']) : $spec;
            $rules[$name]  = ['label' => $f['label'], 'rules' => str_replace('{id}', (string) $id, $spec)];
            $data[$name]   = $this->request->getPost($name);
            if (is_string($data[$name])) {
                $data[$name] = trim($data[$name]);
            }
        }

        if (! $this->validateData($data, $rules)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Revisa los campos marcados.', 'errors' => $this->validator->getErrors()]);
        }

        $data = $this->beforeSave($data, $exists);
        if ($data instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $data;
        }
        // Los vacíos opcionales se guardan como NULL.
        foreach ($data as $k => $v) {
            if ($v === '') {
                $data[$k] = null;
            }
        }

        try {
            $exists ? $model->update($id, $data) : $model->insert($data);
        } catch (DatabaseException $e) {
            log_message('error', $e->getMessage());

            return $this->fail('No se pudo guardar el registro.', 500);
        }

        return $this->response->setJSON(['ok' => true, 'message' => $exists ? 'Registro actualizado.' : 'Registro creado.']);
    }

    public function eliminar(int $id)
    {
        $model = $this->model();
        $row   = $model->find($id);
        if (! $row) {
            return $this->fail('El registro ya no existe.', 404);
        }
        if ($msg = $this->cannotDelete($row)) {
            return $this->fail($msg, 409);
        }

        try {
            $ok = $model->delete($id);
        } catch (DatabaseException $e) {
            $ok = false;
        }

        return $ok
            ? $this->response->setJSON(['ok' => true, 'message' => 'Registro eliminado.'])
            : $this->fail('No se puede eliminar: tiene registros relacionados.', 409);
    }

    /** Hook: transforma los datos validados; puede devolver una respuesta de error. */
    protected function beforeSave(array $data, ?array $existing)
    {
        return $data;
    }

    /** Hook: devuelve un mensaje si el registro no puede eliminarse. */
    protected function cannotDelete(array $row): ?string
    {
        return null;
    }

    protected function fail(string $message, int $status)
    {
        return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $message]);
    }
}
