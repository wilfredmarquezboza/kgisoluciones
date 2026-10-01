<?php

namespace App\Controllers;

use App\Models\PerfilModel;

class Perfiles extends CrudController
{
    protected string $slug = 'perfiles';
    protected string $title = 'Gestión de Perfiles';
    protected string $singular = 'perfil';
    protected string $modelClass = PerfilModel::class;
    protected array $searchColumns = ['perfiles.nombre', 'perfiles.descripcion'];
    protected array $sortMap = ['nombre' => 'perfiles.nombre', 'descripcion' => 'perfiles.descripcion'];

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
        if (db_connect()->table('usuarios')->where('perfil_id', $row['id'])->countAllResults() > 0) {
            return 'No se puede eliminar: hay usuarios con este perfil.';
        }

        return null;
    }
}
