<?php

namespace App\Controllers;

use App\Libraries\Auditoria;
use App\Libraries\Permisos;
use App\Models\PerfilModel;

class Perfiles extends CrudController
{
    protected string $slug = 'perfiles';
    protected string $title = 'Gestión de Perfiles';
    protected string $singular = 'perfil';
    protected string $modelClass = PerfilModel::class;
    protected array $searchColumns = ['perfiles.nombre', 'perfiles.descripcion'];
    protected array $sortMap = ['nombre' => 'perfiles.nombre', 'descripcion' => 'perfiles.descripcion'];

    public function __construct()
    {
        $this->rowLinks = [['icon' => 'fa-key', 'title' => 'Permisos', 'href' => site_url('perfiles/permisos')]];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'nombre', 'label' => 'Nombre', 'sortable' => true],
            ['key' => 'descripcion', 'label' => 'Descripción', 'sortable' => true],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true, 'rules' => 'required|max_length[80]'],
            ['name' => 'descripcion', 'label' => 'Descripción', 'type' => 'text', 'rules' => 'permit_empty|max_length[255]'],
        ];
    }

    protected function cannotDelete(array $row): ?string
    {
        if (! empty($row['es_admin'])) {
            return 'El perfil de administración no se puede eliminar.';
        }
        if (db_connect()->table('usuarios')->where('perfil_id', $row['id'])->countAllResults() > 0) {
            return 'No se puede eliminar: hay usuarios con este perfil.';
        }

        return null;
    }

    /** Matriz de permisos de un perfil. */
    public function permisos(int $id)
    {
        $perfil = (new PerfilModel())->find($id);
        if (! $perfil) {
            return redirect()->to('/perfiles');
        }
        $marcados = array_column(db_connect()->table('permisos')->select('permiso')->where('perfil_id', $id)->get()->getResultArray(), 'permiso');

        return view('perfiles/permisos', ['title' => 'Permisos del perfil', 'perfil' => $perfil, 'marcados' => array_flip($marcados)]);
    }

    public function guardarPermisos(int $id)
    {
        $model  = new PerfilModel();
        $perfil = $model->find($id);
        if (! $perfil) {
            return redirect()->to('/perfiles');
        }
        if ($perfil['es_admin']) {
            return redirect()->to('/perfiles')->with('error', 'El perfil de administración tiene acceso total.');
        }

        $enviados = array_intersect((array) $this->request->getPost('permisos'), Permisos::validos());
        // Cualquier acción exige poder ver el módulo.
        foreach ($enviados as $p) {
            $enviados[] = explode('.', $p)[0] . '.ver';
        }
        $nuevos = array_values(array_unique($enviados));
        $antes  = array_column(db_connect()->table('permisos')->select('permiso')->where('perfil_id', $id)->get()->getResultArray(), 'permiso');

        $db = db_connect();
        $db->transStart();
        $db->table('permisos')->where('perfil_id', $id)->delete();
        foreach ($nuevos as $p) {
            $db->table('permisos')->insert(['perfil_id' => $id, 'permiso' => $p]);
        }
        $db->transComplete();
        Permisos::olvidar();

        Auditoria::registrar('editar', 'perfiles', $id, 'Permisos del perfil: ' . $perfil['nombre'],
            ['permisos' => implode(', ', $antes) ?: '(ninguno)'], ['permisos' => implode(', ', $nuevos) ?: '(ninguno)']);

        return redirect()->to('/perfiles')->with('success', 'Permisos guardados para el perfil ' . $perfil['nombre'] . '.');
    }
}
