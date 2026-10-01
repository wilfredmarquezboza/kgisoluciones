<?php

namespace App\Controllers;

use App\Models\ConfiguracionModel;

class Configuraciones extends CrudController
{
    protected string $slug = 'configuraciones';
    protected string $title = 'Gestión de Configuraciones';
    protected string $singular = 'configuración';
    protected string $modelClass = ConfiguracionModel::class;
    protected array $searchColumns = ['configuraciones.clave', 'configuraciones.valor'];
    protected array $sortMap = ['clave' => 'configuraciones.clave', 'valor' => 'configuraciones.valor'];

    protected function columns(): array
    {
        return [
            ['key' => 'clave', 'label' => 'Clave', 'sortable' => true],
            ['key' => 'valor', 'label' => 'Valor', 'sortable' => true],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'clave', 'label' => 'Clave', 'type' => 'text', 'required' => true,
                'rules' => 'required|max_length[80]|is_unique[configuraciones.clave,id,{id}]'],
            ['name' => 'valor', 'label' => 'Valor', 'type' => 'textarea', 'rules' => 'permit_empty'],
        ];
    }

    /** Las claves sensibles (contraseñas, tokens) no se envían en claro al listado. */
    public function listar()
    {
        $res  = parent::listar();
        $body = json_decode($res->getBody(), true);
        foreach ($body['data'] as &$row) {
            if (preg_match('/PASS|SECRET|TOKEN|KEY/i', $row['clave']) && $row['valor'] !== null && $row['valor'] !== '') {
                $row['valor']  = '••••••••';
                $row['masked'] = true;
            }
        }

        return $res->setJSON($body);
    }

    protected function beforeSave(array $data, ?array $existing)
    {
        // Si se edita una clave sensible y se deja el valor enmascarado, se conserva el original.
        if ($existing && $data['valor'] === '••••••••') {
            $data['valor'] = $existing['valor'];
        }

        return $data;
    }
}
