<?php

namespace App\Services;

use RuntimeException;

/**
 * A Meta recusou ou não respondeu ao conectar o WhatsApp do profissional.
 * A mensagem é mostrada ao profissional.
 */
class WhatsAppConnectionException extends RuntimeException {}
