<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Datos ficticios para probar el Control de facturas: php spark db:seed FacturasDemoSeeder */
class FacturasDemoSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        // [empresa|null, departamento, proyecto, moneda, monto, [[etiqueta, %, estado, factura, fecha]...]]
        $demo = [
            [null, 'Lima', 'Loteo Los Álamos', 'USD', 480, [['Primer pago', 30], ['Pago final', 70]]],
            [null, 'Ica', 'PI+HU Santa Rosa', 'USD', 450, [['Primer pago', 30], ['Segundo pago', 30, 'facturado', 'FCT 51'], ['Tercer pago', 30], ['Cuarto pago', 10]]],
            [null, 'Junín', 'Catastro Fundo Alegría', 'PEN', 265.5, [['Primer pago', 50, 'facturado', 'FCT53'], ['Segundo pago', 50]]],
            ['CONSTRUYE SAC', 'Arequipa', 'TORRE NORTE (Conjunto Las Terrazas)', 'USD', 350, [['Primer pago', 25, 'pagado', 'FCT64', '2026-02-10'], ['Segundo pago', 50, 'pagado', 'FCT 65', '2026-04-02'], ['Pago final', 25]]],
            ['VERDEMAR', 'Ancash', 'Regularización de DF - Planta Norte', 'USD', 700, [['Primer pago', 30], ['Segundo pago', 40], ['Pago final', 30]]],
            ['GRUPO ANDINO', 'Cusco', 'RESIDENCIAL LAS FLORES', 'USD', 420, [['Primer pago', 29.4118, 'pagado', '', '2025-11-25'], ['Segundo pago', 29.4118, 'pagado', 'FCT 63', '2026-06-15'], ['Tercer pago', 41.1764]]],
        ];

        foreach ($demo as [$empresa, $dep, $nombre, $moneda, $monto, $cuotas]) {
            $clienteId = null;
            if ($empresa) {
                $c = $this->db->table('clientes')->where('nombre', $empresa)->get()->getRowArray();
                if (! $c) {
                    $this->db->table('clientes')->insert(['nombre' => $empresa, 'created_at' => $now, 'updated_at' => $now]);
                    $clienteId = $this->db->insertID();
                } else {
                    $clienteId = $c['id'];
                }
            }
            $this->db->table('proyectos')->insert([
                'cliente_id' => $clienteId, 'departamento' => $dep, 'nombre' => $nombre, 'moneda' => $moneda, 'monto' => $monto,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $pid = $this->db->insertID();
            foreach ($cuotas as $i => $c) {
                $this->db->table('cuotas')->insert([
                    'proyecto_id' => $pid, 'orden' => $i, 'etiqueta' => $c[0], 'porcentaje' => $c[1], 'estado' => $c[2] ?? 'pendiente',
                    'factura' => ($c[3] ?? '') ?: null, 'fecha_pago' => ($c[4] ?? '') ?: null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            if ($nombre === 'TORRE NORTE (Conjunto Las Terrazas)') {
                $this->db->table('actividades')->insertBatch([
                    ['proyecto_id' => $pid, 'nombre' => 'Levantamiento de campo', 'fecha' => '2026-02-01', 'estado' => 'Concluido', 'porcentaje' => 40, 'created_at' => $now, 'updated_at' => $now],
                    ['proyecto_id' => $pid, 'nombre' => 'Expediente técnico', 'fecha' => '2026-03-15', 'estado' => 'Presentado', 'porcentaje' => 60, 'created_at' => $now, 'updated_at' => $now],
                ]);
            }
        }
    }
}
