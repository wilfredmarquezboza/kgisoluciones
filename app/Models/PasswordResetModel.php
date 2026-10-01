<?php

namespace App\Models;

class PasswordResetModel extends BaseModel
{
    protected $table         = 'password_resets';
    protected $allowedFields = ['usuario_id', 'token_hash', 'expira_en', 'usado_en'];
    protected $updatedField  = '';
    public const TTL_MINUTOS = 60;

    /** Crea un token y devuelve el valor en claro (solo se guarda su hash). */
    public function issue(int $usuarioId): string
    {
        $this->where('usuario_id', $usuarioId)->where('usado_en', null)->set(['usado_en' => date('Y-m-d H:i:s')])->update();

        $token = bin2hex(random_bytes(32));
        $this->insert([
            'usuario_id' => $usuarioId,
            'token_hash' => hash('sha256', $token),
            'expira_en'  => date('Y-m-d H:i:s', time() + self::TTL_MINUTOS * 60),
        ]);

        return $token;
    }

    /** Devuelve el registro vigente (no usado, no expirado) o null. */
    public function findValid(string $token): ?array
    {
        return $this->where('token_hash', hash('sha256', $token))
            ->where('usado_en', null)
            ->where('expira_en >=', date('Y-m-d H:i:s'))
            ->first();
    }
}
