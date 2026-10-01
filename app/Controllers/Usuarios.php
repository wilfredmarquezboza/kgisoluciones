<?php

namespace App\Controllers;

use App\Libraries\Permisos;
use App\Models\PerfilModel;
use App\Models\UsuarioModel;
use CodeIgniter\Database\BaseBuilder;

class Usuarios extends CrudController
{
    protected string $slug = 'usuarios';
    protected string $title = 'Gestión de Usuarios';
    protected string $singular = 'usuario';
    protected string $modelClass = UsuarioModel::class;
    protected array $searchColumns = ['usuarios.nombres', 'usuarios.email', 'perfiles.nombre'];
    protected array $sortMap = [
        'nombres' => 'usuarios.nombres', 'email' => 'usuarios.email', 'perfil' => 'perfiles.nombre',
        'activo' => 'usuarios.activo', 'ultimo_acceso' => 'usuarios.ultimo_acceso',
    ];

    protected function selectList(BaseBuilder $b): BaseBuilder
    {
        // Nunca se expone la columna password.
        return $b->select('usuarios.id, usuarios.perfil_id, usuarios.nombres, usuarios.email, usuarios.activo, usuarios.ultimo_acceso, perfiles.nombre AS perfil')
            ->join('perfiles', 'perfiles.id = usuarios.perfil_id');
    }

    protected function columns(): array
    {
        return [
            ['key' => 'nombres', 'label' => 'Nombres', 'sortable' => true, 'type' => 'avatar'],
            ['key' => 'email', 'label' => 'Correo', 'sortable' => true],
            ['key' => 'perfil', 'label' => 'Perfil', 'sortable' => true, 'type' => 'tag'],
            ['key' => 'activo', 'label' => 'Estado', 'sortable' => true, 'type' => 'estado'],
            ['key' => 'ultimo_acceso', 'label' => 'Último acceso', 'sortable' => true, 'type' => 'datetime'],
        ];
    }

    protected function fields(): array
    {
        $perfiles = array_column((new PerfilModel())->orderBy('nombre')->findAll(), 'nombre', 'id');

        return [
            ['name' => 'nombres', 'label' => 'Nombres', 'type' => 'text', 'required' => true, 'rules' => 'required|max_length[120]'],
            ['name' => 'email', 'label' => 'Correo', 'type' => 'email', 'required' => true,
                'rules' => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,{id}]'],
            ['name' => 'perfil_id', 'label' => 'Perfil', 'type' => 'select', 'required' => true, 'options' => $perfiles,
                'rules' => 'required|is_natural_no_zero|is_not_unique[perfiles.id]'],
            ['name' => 'password', 'label' => 'Contraseña', 'type' => 'password', 'required' => 'create',
                'help' => 'Mínimo 8 caracteres. Al editar, déjala vacía para no cambiarla.',
                'rules' => ['create' => 'required|min_length[8]|max_length[72]', 'edit' => 'permit_empty|min_length[8]|max_length[72]']],
            ['name' => 'activo', 'label' => 'Estado', 'type' => 'select', 'required' => true, 'default' => '1',
                'options' => ['1' => 'Activo', '0' => 'Inactivo'], 'rules' => 'required|in_list[0,1]'],
        ];
    }

    protected function beforeSave(array $data, ?array $existing)
    {
        $data['email'] = mb_strtolower($data['email']);

        if ($existing && (int) $existing['id'] === usuario_actual()['id'] && $data['activo'] === '0') {
            return $this->fail('No puedes desactivar tu propio usuario.', 409);
        }
        // Siempre debe quedar al menos un administrador activo.
        if ($existing && $this->esAdminActivo($existing) && (! Permisos::esAdmin((int) $data['perfil_id']) || $data['activo'] === '0')
            && ! $this->quedaOtroAdmin((int) $existing['id'])) {
            return $this->fail('Debe quedar al menos un administrador activo.', 409);
        }

        if ($data['password'] === '') {
            unset($data['password']);
        } else {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $data['activo'] = (int) $data['activo'];

        return $data;
    }

    protected function cannotDelete(array $row): ?string
    {
        if ((int) $row['id'] === usuario_actual()['id']) {
            return 'No puedes eliminar tu propio usuario.';
        }

        return $this->esAdminActivo($row) && ! $this->quedaOtroAdmin((int) $row['id']) ? 'Debe quedar al menos un administrador activo.' : null;
    }

    private function esAdminActivo(array $u): bool
    {
        return (int) $u['activo'] === 1 && Permisos::esAdmin((int) $u['perfil_id']);
    }

    private function quedaOtroAdmin(int $excluirId): bool
    {
        return db_connect()->table('usuarios')->join('perfiles', 'perfiles.id = usuarios.perfil_id')
            ->where('perfiles.es_admin', 1)->where('usuarios.activo', 1)->where('usuarios.id !=', $excluirId)->countAllResults() > 0;
    }
}
