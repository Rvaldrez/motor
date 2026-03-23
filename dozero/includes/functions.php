<?php
// ============================================================
//  MotorGo – Funções Utilitárias Gerais
// ============================================================

require_once __DIR__ . '/config.php';

// ── Sanitização ───────────────────────────────────────────────

/**
 * Escapa caracteres especiais para exibição segura em HTML.
 */
function sanitize(string $str): string
{
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// ── Formatação ────────────────────────────────────────────────

/**
 * Formata valor monetário: R$ 1.234,56
 */
function formatMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Formata número de telefone: (11) 99999-9999 ou (11) 9999-9999
 */
function formatPhone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);

    if (strlen($digits) === 11) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
    }

    if (strlen($digits) === 10) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6);
    }

    return $phone;
}

/**
 * Formata CPF: 123.456.789-00
 */
function formatCpf(string $cpf): string
{
    $digits = preg_replace('/\D/', '', $cpf);

    if (strlen($digits) !== 11) {
        return $cpf;
    }

    return substr($digits, 0, 3) . '.'
         . substr($digits, 3, 3) . '.'
         . substr($digits, 6, 3) . '-'
         . substr($digits, 9, 2);
}

// ── Validação ─────────────────────────────────────────────────

/**
 * Validação completa de CPF (dígitos verificadores).
 */
function validateCpf(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) !== 11) {
        return false;
    }

    // CPFs com todos os dígitos iguais são inválidos
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    // Primeiro dígito verificador
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int) $cpf[$i] * (10 - $i);
    }
    $remainder = $sum % 11;
    $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

    if ((int) $cpf[9] !== $digit1) {
        return false;
    }

    // Segundo dígito verificador
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += (int) $cpf[$i] * (11 - $i);
    }
    $remainder = $sum % 11;
    $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

    return (int) $cpf[10] === $digit2;
}

/**
 * Valida endereço de e-mail.
 */
function validateEmail(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

// ── Geração de Tokens ─────────────────────────────────────────

/**
 * Gera token hexadecimal criptograficamente seguro.
 * @param int $length Comprimento final (deve ser par)
 */
function generateToken(int $length = 32): string
{
    return bin2hex(random_bytes((int) ceil($length / 2)));
}

/**
 * Gera código numérico aleatório com zero-padding (ex: para SMS/e-mail).
 * Permite códigos com zeros à esquerda: '007341', '000189', etc.
 */
function generateCode(int $length = 6): string
{
    $max = (int) str_repeat('9', $length);
    return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
}

// ── E-mail ────────────────────────────────────────────────────

/**
 * Envia e-mail via PHPMailer + SMTP.
 *
 * @param string $to        E-mail destinatário
 * @param string $toName    Nome do destinatário
 * @param string $subject   Assunto
 * @param string $htmlBody  Corpo HTML
 * @param string $textBody  Corpo texto-puro (opcional; gerado automaticamente se vazio)
 * @return bool
 */
function sendEmail(
    string $to,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): bool {
    require_once __DIR__ . '/../../vendor/autoload.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Configuração do servidor SMTP
        $mail->isSMTP();
        $mail->Host       = EMAIL_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_SMTP_USER;
        $mail->Password   = EMAIL_SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = EMAIL_SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Remetente
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);

        // Destinatário
        $mail->addAddress($to, $toName);

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody !== ''
            ? $textBody
            : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

        $mail->send();
        return true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        if (APP_DEBUG) {
            error_log('[MotorGo] Falha ao enviar e-mail para ' . $to . ': ' . $e->getMessage());
        }
        return false;
    }
}

// ── Labels de Status ──────────────────────────────────────────

/**
 * Retorna label legível para status de veículo ou proposta.
 */
function getStatusLabel(string $status): string
{
    $labels = [
        // Veículos
        'disponivel'      => 'Disponível',
        'em_negociacao'   => 'Em Negociação',
        'vendido'         => 'Vendido',
        'pausado'         => 'Pausado',
        'pendente'        => 'Pendente',
        'reprovado'       => 'Reprovado',
        'aprovado'        => 'Aprovado',

        // Propostas
        'aguardando'      => 'Aguardando',
        'aceita'          => 'Aceita',
        'recusada'        => 'Recusada',
        'cancelada'       => 'Cancelada',
        'contraproposta'  => 'Contraproposta',
        'finalizada'      => 'Finalizada',
        'expirada'        => 'Expirada',

        // Usuários
        'ativo'           => 'Ativo',
        'inativo'         => 'Inativo',
        'bloqueado'       => 'Bloqueado',
        'aguardando_verificacao' => 'Aguardando Verificação',
    ];

    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

// ── Tempo Relativo ────────────────────────────────────────────

/**
 * Retorna string legível de tempo decorrido: "há X horas", "há 2 dias", etc.
 */
function timeAgo(string $datetime): string
{
    $now  = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
    $past = new \DateTime($datetime, new \DateTimeZone('America/Sao_Paulo'));
    $diff = $now->diff($past);

    if ($diff->y > 0) {
        return $diff->y === 1 ? 'há 1 ano' : "há {$diff->y} anos";
    }
    if ($diff->m > 0) {
        return $diff->m === 1 ? 'há 1 mês' : "há {$diff->m} meses";
    }
    if ($diff->d > 0) {
        return $diff->d === 1 ? 'há 1 dia' : "há {$diff->d} dias";
    }
    if ($diff->h > 0) {
        return $diff->h === 1 ? 'há 1 hora' : "há {$diff->h} horas";
    }
    if ($diff->i > 0) {
        return $diff->i === 1 ? 'há 1 minuto' : "há {$diff->i} minutos";
    }

    return 'agora mesmo';
}

// ── Resolução de URLs de Foto ─────────────────────────────────

/**
 * Resolve the full URL for a vehicle photo path.
 * Old system: caminho_foto starts with 'uploads/' → use SITE_URL (photos on motorgo.co).
 * New system: caminho_foto is relative (e.g. 'fotos_veiculos/file.jpg') → use UPLOAD_URL.
 */
function resolvePhotoUrl(string $path): string
{
    if ($path === '') return '';
    if (strncmp($path, 'uploads/', 8) === 0) {
        return SITE_URL . '/' . $path;
    }
    return UPLOAD_URL . $path;
}

// ── Imagens ───────────────────────────────────────────────────

/**
 * Redimensiona imagem usando GD mantendo proporção.
 *
 * @param string $sourcePath Caminho da imagem original
 * @param string $destPath   Caminho de destino
 * @param int    $maxWidth   Largura máxima em px
 * @param int    $maxHeight  Altura máxima em px
 * @return bool
 */
function resizeImage(
    string $sourcePath,
    string $destPath,
    int $maxWidth = 800,
    int $maxHeight = 600
): bool {
    if (!file_exists($sourcePath)) {
        return false;
    }

    $info = @getimagesize($sourcePath);
    if ($info === false) {
        return false;
    }

    [$origWidth, $origHeight, $type] = $info;

    // Criar recurso GD conforme o tipo
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $source = @imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if ($source === false) {
        return false;
    }

    // Calcular novas dimensões mantendo aspect ratio
    $ratio      = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1.0);
    $newWidth   = (int) round($origWidth  * $ratio);
    $newHeight  = (int) round($origHeight * $ratio);

    $dest = imagecreatetruecolor($newWidth, $newHeight);

    if ($dest === false) {
        imagedestroy($source);
        return false;
    }

    // Preservar transparência para PNG e WebP
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Salvar no formato adequado
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($dest, $destPath, 85);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($dest, $destPath, 8);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($dest, $destPath, 85);
            break;
    }

    imagedestroy($source);
    imagedestroy($dest);

    return $result;
}

/**
 * Remove arquivo do disco com segurança.
 */
function deleteFile(string $path): bool
{
    $realPath = realpath($path);

    // Verifica se o arquivo existe e está dentro do diretório de uploads
    if ($realPath === false || !file_exists($realPath)) {
        return false;
    }

    $uploadDir = realpath(UPLOAD_DIR);
    if ($uploadDir === false || strpos($realPath, $uploadDir) !== 0) {
        // Não permite deletar arquivos fora do diretório de uploads
        return false;
    }

    return @unlink($realPath);
}

// ── Paginação ─────────────────────────────────────────────────

/**
 * Retorna dados de paginação para consultas.
 *
 * @param int $total     Total de registros
 * @param int $perPage   Registros por página
 * @param int $current   Página atual (1-based)
 * @return array{total: int, per_page: int, current: int, last: int, offset: int}
 */
function paginate(int $total, int $perPage = 10, int $current = 1): array
{
    $last    = (int) ceil($total / $perPage);
    $current = max(1, min($current, $last ?: 1));
    $offset  = ($current - 1) * $perPage;

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'current'  => $current,
        'last'     => $last,
        'offset'   => $offset,
    ];
}

// ── Redirecionamento ──────────────────────────────────────────

/**
 * Redireciona com mensagem flash armazenada na sessão.
 */
function redirectWithMessage(string $url, string $type, string $message): never
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $url);
    exit;
}

/**
 * Recupera e remove mensagem flash da sessão.
 */
function getFlashMessage(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── Formatação de Tamanho de Arquivo ─────────────────────────

/**
 * Formata tamanho em bytes para leitura humana (KB, MB, GB).
 */
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// ── Máscara de CPF/Telefone para exibição parcial ─────────────

/**
 * Mascara parte do CPF para exibição segura: 123.***.***-00
 */
function maskCpf(string $cpf): string
{
    $digits = preg_replace('/\D/', '', $cpf);
    if (strlen($digits) !== 11) {
        return $cpf;
    }
    return substr($digits, 0, 3) . '.***.***-' . substr($digits, 9, 2);
}

/**
 * Mascara parte do e-mail: jo***@example.com
 */
function maskEmail(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $visible = substr($local, 0, min(2, strlen($local)));
    return $visible . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
}
