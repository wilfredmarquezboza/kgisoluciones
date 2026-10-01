<?php

namespace App\Controllers;

use App\Models\ClienteModel;

class Clientes extends CrudController
{
    protected string $slug = 'clientes';
    protected string $title = 'Gestión de Clientes';
    protected string $singular = 'cliente';
    protected string $modelClass = ClienteModel::class;
    protected array $searchColumns = ['clientes.nombre'];
    protected array $sortMap = ['nombre' => 'clientes.nombre'];

    protected function columns(): array
    {
        return [['key' => 'nombre', 'label' => 'Cliente', 'sortable' => true]];
    }

    protected function fields(): array
    {
        return [['name' => 'nombre', 'label' => 'Cliente', 'type' => 'text', 'required' => true, 'rules' => 'required|max_length[150]']];
    }
}
