<?php

namespace App\Libraries;

use App\Models\ConfiguracionModel;

/** Envía correos usando los valores SMTP_* guardados en la tabla `configuraciones`. */
class Mailer
{
    public function send(string $to, string $subject, string $html): bool
    {
        $cfg = array_column((new ConfiguracionModel())->findAll(), 'valor', 'clave');

        $host = trim($cfg['SMTP_HOST'] ?? '');
        $user = trim($cfg['SMTP_USER'] ?? '');
        if ($host === '' || $user === '') {
            log_message('error', 'Mailer: SMTP_HOST / SMTP_USER no configurados.');

            return false;
        }

        // Cifrado: "ssl://host" o puerto 465 => SSL directo; si no, STARTTLS salvo SMTP_CRYPTO = none.
        $port   = (int) ($cfg['SMTP_PORT'] ?? 465);
        $crypto = strtolower(trim($cfg['SMTP_CRYPTO'] ?? ''));
        if (stripos($host, 'ssl://') === 0) {
            $host   = substr($host, 6);
            $crypto = 'ssl';
        } elseif (stripos($host, 'tls://') === 0) {
            $host   = substr($host, 6);
            $crypto = 'tls';
        } elseif ($crypto === '') {
            $crypto = $port === 465 ? 'ssl' : 'tls';
        }
        $crypto = $crypto === 'none' ? '' : $crypto;

        $email = service('email');
        $email->initialize([
            'protocol'   => 'smtp',
            'SMTPHost'   => $host,
            'SMTPPort'   => $port,
            'SMTPCrypto' => $crypto,
            'SMTPUser'   => $user,
            'SMTPPass'   => $cfg['SMTP_PASS'] ?? '',
            'SMTPTimeout' => 10,
            'mailType'   => 'html',
            'charset'    => 'UTF-8',
            'newline'    => "\r\n",
            'CRLF'       => "\r\n",
        ]);
        $email->setFrom($user, $cfg['SMTP_FROM'] ?? 'KGI Soluciones');
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($html);

        if (! $email->send(false)) {
            log_message('error', 'Mailer: ' . strip_tags($email->printDebugger(['headers'])));

            return false;
        }

        return true;
    }
}
