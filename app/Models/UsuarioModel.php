<?php

namespace App\Models;

class UsuarioModel extends BaseModel
{
    protected $table         = 'usuarios';
    protected $allowedFields = ['perfil_id', 'nombres', 'email', 'password', 'activo', 'ultimo_acceso'];

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', mb_strtolower(trim($email)))->first();
    }
}
