<?php

namespace App\Commands;

use App\Libraries\Avisos;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class FacturasAvisos extends BaseCommand
{
    protected $group       = 'KGI';
    protected $name        = 'facturas:avisos';
    protected $description = 'Envía por correo el resumen de pagos vencidos, por vencer y listos para facturar.';
    protected $usage       = 'facturas:avisos [--dry] [--force]';
    protected $options     = [
        '--dry'   => 'Muestra a quién se enviaría, sin enviar.',
        '--force' => 'Reenvía aunque ya se haya enviado hoy.',
    ];

    public function run(array $params)
    {
        [$ok, $msg] = (new Avisos())->enviar(CLI::getOption('dry') !== null, CLI::getOption('force') !== null);
        $ok ? CLI::write($msg, 'green') : CLI::error($msg);

        return $ok ? 0 : 1;
    }
}
