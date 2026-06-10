<?php
// aprovar_pedido.php - Aprovação de pedido com envio de e‑mail (PHPMailer) - Layout profissional
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();

// ========== CARREGAR DEPENDÊNCIAS ==========
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de conexão: ' . $conn->connect_error]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pedido_id = isset($input['pedido_id']) ? (int)$input['pedido_id'] : 0;
$professor_id = isset($input['professor_id']) ? (int)$input['professor_id'] : 0;

if (!$pedido_id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID do pedido inválido']);
    exit;
}

// Buscar pedido
$stmt = $conn->prepare("SELECT * FROM pedidos_explicadores WHERE id = ? AND status = 'pendente'");
$stmt->bind_param("i", $pedido_id);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido não encontrado ou já processado']);
    exit;
}

// Verificar se email já existe
$check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$check->bind_param("s", $pedido['email']);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Email já registado no sistema']);
    $check->close();
    exit;
}
$check->close();

// Gerar senha aleatória
$senha = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

$conn->begin_transaction();

try {
    // 1. Inserir utilizador
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, ativo, data_criacao) VALUES (?, ?, ?, 'aluno', 1, NOW())");
    $stmt->bind_param("sss", $pedido['nome'], $pedido['email'], $senha_hash);
    if (!$stmt->execute()) throw new Exception("Erro ao criar utilizador: " . $stmt->error);
    $usuario_id = $stmt->insert_id;
    $stmt->close();

    // 2. Buscar nome do professor
    $prof_nome = '';
    if ($professor_id) {
        $stmt2 = $conn->prepare("SELECT u.nome FROM professores p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?");
        $stmt2->bind_param("i", $professor_id);
        $stmt2->execute();
        $res = $stmt2->get_result();
        if ($row = $res->fetch_assoc()) $prof_nome = $row['nome'];
        $stmt2->close();
    }

    // 3. Preparar dados para a tabela fichas
    $regime_presencial = ($pedido['tipo_aula'] == 'presencial') ? 1 : 0;
    $regime_domicilio = ($pedido['tipo_aula'] == 'domicilio') ? 1 : 0;
    $dias_semana_json = json_encode(['dias' => explode(', ', $pedido['dias_semana'])]);
    $horarios_json = json_encode(['preferencial' => $pedido['horario']]);
    $nivel_cambridge = $pedido['nivel_cambridge'];
    $pacote_nome = $pedido['pacote'];
    $valor_total = $pedido['preco_total'];
    $valor_mensal = $pedido['preco_base'];
    $observacoes = $pedido['observacoes'];
    $contacto = $pedido['contacto'];
    $localizacao = $pedido['localizacao'];

    // 4. Inserir ficha
    $sql = "INSERT INTO fichas 
            (usuario_id, nome, email, contacto_encarregado, localizacao, nivel, nivel_cambridge, 
             pacote, regime_presencial, regime_domicilio, dias_semana, horarios_json, dificuldade, 
             valor_total, valor_mensal, pagamento_status, professor_id, professor_atribuido, data_submissao)
            VALUES (?, ?, ?, ?, ?, 'cambridge', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, NOW())";
    
    $stmt3 = $conn->prepare($sql);
    $stmt3->bind_param("isssssiisssddiis", 
        $usuario_id,
        $pedido['nome'],
        $pedido['email'],
        $contacto,
        $localizacao,
        $nivel_cambridge,
        $pacote_nome,
        $regime_presencial,
        $regime_domicilio,
        $dias_semana_json,
        $horarios_json,
        $observacoes,
        $valor_total,
        $valor_mensal,
        $professor_id,
        $prof_nome
    );
    if (!$stmt3->execute()) throw new Exception("Erro ao criar ficha: " . $stmt3->error);
    $ficha_id = $stmt3->insert_id;
    $stmt3->close();

    // 5. Inserir horários na tabela horarios_aulas
    $dias_array = explode(', ', $pedido['dias_semana']);
    $horario_padrao = $pedido['horario'];
    $mapa_dias = [
        'Segunda' => 'segunda', 'Segunda-feira' => 'segunda',
        'Terça' => 'terca', 'Terça-feira' => 'terca', 'Terca' => 'terca',
        'Quarta' => 'quarta', 'Quarta-feira' => 'quarta',
        'Quinta' => 'quinta', 'Quinta-feira' => 'quinta',
        'Sexta' => 'sexta', 'Sexta-feira' => 'sexta',
        'Sábado' => 'sabado', 'Sabado' => 'sabado',
        'Domingo' => 'domingo'
    ];
    foreach ($dias_array as $dia) {
        $dia_limpo = trim($dia);
        $dia_normalizado = $mapa_dias[$dia_limpo] ?? strtolower($dia_limpo);
        $stmt_h = $conn->prepare("INSERT INTO horarios_aulas (ficha_id, dia_semana, horario) VALUES (?, ?, ?)");
        $stmt_h->bind_param("iss", $ficha_id, $dia_normalizado, $horario_padrao);
        $stmt_h->execute();
        $stmt_h->close();
    }

    // 6. Actualizar pedido
    $stmt4 = $conn->prepare("UPDATE pedidos_explicadores SET status = 'aprovado', ficha_id = ?, usuario_id = ? WHERE id = ?");
    $stmt4->bind_param("iii", $ficha_id, $usuario_id, $pedido_id);
    $stmt4->execute();
    $stmt4->close();

    $conn->commit();

    // ========== ENVIAR E-MAIL COM A SENHA (layout profissional) ==========
    $email_enviado = false;
    if (!empty($pedido['email'])) {
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($pedido['email'], $pedido['nome']);

            $mail->isHTML(true);
            $mail->Subject = "Bem‑vindo à Active Learning Academy – Credenciais de acesso";

            // Template HTML profissional
            $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bem‑vindo à Active Learning Academy</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f7fc;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f7fc; padding: 20px;">
        <tr>
            <td align="center">
                <table width="550" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <!-- Cabeçalho -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1f3c88, #2c3e50); padding: 30px 25px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 24px; margin: 0; font-weight: 600;">Active Learning Academy</h1>
                            <p style="color: #d4e6ff; margin: 8px 0 0; font-size: 14px;">Expanding Minds. Elevating Futures.</p>
                        </td>
                    </tr>
                    <!-- Conteúdo -->
                    <tr>
                        <td style="padding: 30px 25px 25px;">
                            <h2 style="color: #1f3c88; font-size: 22px; margin: 0 0 15px; font-weight: 500;">Olá ' . htmlspecialchars($pedido['nome']) . ',</h2>
                            <p style="color: #2c3e50; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                                O seu pedido foi aprovado! Já pode aceder à nossa plataforma com as credenciais abaixo:
                            </p>
                            <table width="100%" cellpadding="10" cellspacing="0" style="background-color: #f8fafc; border-radius: 10px; margin: 20px 0;">
                                <tr>
                                    <td width="30%" style="color: #1f3c88; font-weight: bold;">Email:</td>
                                    <td>' . htmlspecialchars($pedido['email']) . '</td>
                                </tr>
                                <tr>
                                    <td style="color: #1f3c88; font-weight: bold;">Senha temporária:</td>
                                    <td><strong style="color: #e67e22; font-size: 18px;">' . htmlspecialchars($senha) . '</strong></td>
                                </tr>
                            </table>
                            <p style="text-align: center; margin: 25px 0 20px;">
                                <a href="http://localhost/Active_Learning_Academy/login.php" style="display: inline-block; background-color: #27ae60; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 30px; font-size: 16px; font-weight: bold;">🔐 Aceder à Plataforma</a>
                            </p>
                            <p style="color: #2c3e50; font-size: 14px; line-height: 1.5; margin: 20px 0 0;">
                                Recomendamos que altere a sua senha após o primeiro acesso.
                            </p>
                            <hr style="border: none; border-top: 1px solid #e0e7ef; margin: 25px 0 10px;">
                            <p style="color: #7f8c8d; font-size: 12px; text-align: center; margin: 0;">
                                Active Learning Academy – Transformando conhecimento em sucesso.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
            $mail->AltBody = "Olá {$pedido['nome']},\n\nSeu pedido foi aprovado!\n\nEmail: {$pedido['email']}\nSenha: {$senha}\n\nAcesse em: http://localhost/Active_Learning_Academy/login.php\n\nRecomendamos que altere a sua senha após o primeiro acesso.\n\nAtenciosamente,\nEquipa Active Learning Academy";

            $mail->send();
            $email_enviado = true;
            error_log("E-mail enviado com sucesso para {$pedido['email']}");
        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail: " . $mail->ErrorInfo);
        }
    }

    $mensagem = "Pedido aprovado! Aluno criado com senha: $senha";
    if ($email_enviado) {
        $mensagem .= " As credenciais foram enviadas para o e‑mail do aluno.";
    } else {
        $mensagem .= " Não foi possível enviar o e‑mail automaticamente. Por favor, informe o aluno manualmente.";
    }

    echo json_encode([
        'sucesso' => true,
        'mensagem' => $mensagem,
        'ficha_id' => $ficha_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}

$conn->close();
?>