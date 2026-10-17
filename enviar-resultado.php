<?php
// enviar-resultado.php
// Recebe o resultado do teste de nível (teste.html) e envia por e-mail para a escola.
// Precisa ficar na MESMA pasta do teste.html.

header('Content-Type: application/json; charset=utf-8');

// ====== CONFIGURE AQUI ======
$DESTINO   = 'contato@meuinglesclub.com.br'; // quem recebe o resultado
$REMETENTE = 'contato@meuinglesclub.com.br'; // precisa ser um e-mail do seu domínio (existente no cPanel)
// ============================

function resp($ok, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok]);
    exit;
}
function corta($v, $n) {
    return function_exists('mb_substr') ? mb_substr($v, 0, $n) : substr($v, 0, $n);
}
function limpa($v, $max = 200) {
    $v = preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)$v));
    return corta(trim($v), $max);
}
function fmt($v) {
    if (is_array($v)) return corta(json_encode($v, JSON_UNESCAPED_UNICODE), 1500);
    return limpa($v, 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 405);

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 30000) resp(false, 413);
$d = json_decode($raw, true);
if (!is_array($d)) resp(false, 400);

// Campo-isca anti-robô: pessoas não preenchem, robôs sim
if (!empty($d['site'])) resp(true);

$nome  = limpa($d['nome'] ?? '', 120);
$wa    = limpa($d['whatsapp'] ?? '', 40);
$email = limpa($d['email'] ?? '', 120);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
if ($nome === '' || $wa === '' || empty($d['consent'])) resp(false, 400);

// Limite simples: 1 envio a cada 20 segundos por visitante
$arq = sys_get_temp_dir() . '/tn_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x');
if (file_exists($arq) && time() - filemtime($arq) < 20) resp(false, 429);
@touch($arq);

$nivel = limpa($d['nivel'] ?? '', 20);
$linhas = [
    'Novo resultado do teste de nivelamento',
    '',
    'Nome: ' . $nome,
    'WhatsApp: ' . $wa,
    'E-mail: ' . ($email !== '' ? $email : '-'),
    'Consentimento (LGPD): sim',
    '',
    'Nível: ' . $nivel,
    'Gramática: ' . fmt($d['gramatica'] ?? ''),
    'Listening: ' . fmt($d['listening'] ?? '') . ' (nível: ' . fmt($d['listeningNivel'] ?? '') . ')',
    'Ponto de partida: ' . fmt($d['partida'] ?? ''),
    'Errou: ' . fmt($d['errou'] ?? ''),
    'Não sabia: ' . fmt($d['naoSabia'] ?? ''),
    '',
    'Enviado em ' . date('d/m/Y H:i') . ' (horário do servidor)',
];
$corpo = implode("\n", $linhas);

$assunto = '=?UTF-8?B?' . base64_encode('Teste de nível: ' . $nome . ' (' . $nivel . ')') . '?=';
$h  = "From: Inglês Club <$REMETENTE>\r\n";
if ($email !== '') $h .= "Reply-To: $email\r\n";
$h .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

$ok = @mail($DESTINO, $assunto, $corpo, $h, '-f' . $REMETENTE);
resp($ok, $ok ? 200 : 500);
