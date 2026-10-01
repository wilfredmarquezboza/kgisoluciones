<?php

namespace App\Controllers;

use App\Models\ClienteModel;
use App\Models\ProyectoModel;
use CodeIgniter\Database\BaseBuilder;

class Proyectos extends CrudController
{
    public const DEPARTAMENTOS = [
        'Amazonas', 'Ancash', 'Apurímac', 'Arequipa', 'Ayacucho', 'Cajamarca', 'Callao', 'Cusco',
        'Huancavelica', 'Huánuco', 'Ica', 'Junín', 'La Libertad', 'Lambayeque', 'Lima', 'Loreto',
        'Madre de Dios', 'Moquegua', 'Pasco', 'Piura', 'Puno', 'San Martín', 'Tacna', 'Tumbes', 'Ucayali',
    ];

    protected string $slug = 'proyectos';
    protected string $title = 'Gestión de Proyectos';
    protected string $singular = 'proyecto';
    protected string $modelClass = ProyectoModel::class;
    protected array $searchColumns = ['proyectos.departamento', 'proyectos.nombre', 'clientes.nombre'];
    protected array $sortMap = [
        'departamento' => 'proyectos.departamento',
        'nombre'       => 'proyectos.nombre',
        'cliente'      => 'clientes.nombre',
    ];

    protected function selectList(BaseBuilder $b): BaseBuilder
    {
        return $b->select('proyectos.id, proyectos.cliente_id, proyectos.departamento, proyectos.nombre, proyectos.moneda, proyectos.monto, clientes.nombre AS cliente')
            ->join('clientes', 'clientes.id = proyectos.cliente_id', 'left');
    }

    protected function columns(): array
    {
        return [
            ['key' => 'departamento', 'label' => 'Departamento', 'sortable' => true],
            ['key' => 'nombre', 'label' => 'Proyecto', 'sortable' => true],
            ['key' => 'cliente', 'label' => 'Cliente', 'sortable' => true],
        ];
    }

    protected function fields(): array
    {
        $clientes = array_column((new ClienteModel())->orderBy('nombre')->findAll(), 'nombre', 'id');

        return [
            ['name' => 'departamento', 'label' => 'Departamento', 'type' => 'select', 'required' => true,
                'options' => array_combine(self::DEPARTAMENTOS, self::DEPARTAMENTOS),
                'rules' => 'required|in_list[' . implode(',', self::DEPARTAMENTOS) . ']'],
            ['name' => 'nombre', 'label' => 'Proyecto', 'type' => 'text', 'rules' => 'permit_empty|max_length[255]'],
            ['name' => 'cliente_id', 'label' => 'Cliente', 'type' => 'select', 'options' => $clientes, 'empty' => 'Sin cliente',
                'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[clientes.id]'],
            ['name' => 'moneda', 'label' => 'Moneda', 'type' => 'select', 'options' => ['PEN' => 'Soles (S/)', 'USD' => 'Dólares (US$)'],
                'help' => 'Solo para el Control de facturas.', 'rules' => 'permit_empty|in_list[PEN,USD]|required_with[monto]'],
            ['name' => 'monto', 'label' => 'Monto sin IGV', 'type' => 'number', 'help' => 'Si lo dejas vacío el proyecto no aparece en Control de facturas.',
                'rules' => 'permit_empty|decimal|greater_than[0]|less_than[1000000000]|required_with[moneda]'],
        ];
    }
}
