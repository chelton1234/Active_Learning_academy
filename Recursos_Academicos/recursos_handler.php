<?php
/**
 * Recursos_Academicos/recursos_handler.php
 * Gestão completa de recursos e trabalhos (professor <-> aluno)
 * 
 * Versão com logging avançado e tratamento de erros para uploads
 */

// Ativação de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/erros.log'); // Log de erros na mesma pasta

session_start();
header('Cache-Control: no-cache, must-revalidate');

// ---------- CONFIGURAÇÕES DE PASTAS ----------
define('UPLOAD_RECURSOS_DIR', __DIR__ . '/uploads_recursos/');
define('UPLOAD_TRABALHOS_DIR', __DIR__ . '/uploads_trabalhos/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // Aumentado para 20 MB
$ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'];

// ---------- FUNÇÃO DE RESPOSTA JSON ----------
function enviarResposta($sucesso, $mensagem, $codigo = 200, $dados = []) {
    http_response_code($codigo);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $sucesso, 'message' => $mensagem], $dados));
    exit;
}

// ---------- VERIFICAÇÃO DE DEPENDÊNCIA: notificacoes.php ----------
$notificacoes_path = __DIR__ . '/../notificacoes.php';
if (file_exists($notificacoes_path)) {
    require_once $notificacoes_path;
} else {
    if (!function_exists('criarNotificacao')) {
        function criarNotificacao($conn, $usuario_id, $tipo, $titulo, $mensagem, $link = null) { return true; }
    }
}

// ---------- CONEXÃO COM BANCO DE DADOS ----------
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    enviarResposta(false, "Erro de conexão: " . $conn->connect_error);
}

// ---------- FUNÇÕES AUXILIARES ----------
function criarPastas() {
    if (!is_dir(UPLOAD_RECURSOS_DIR)) {
        if (!mkdir(UPLOAD_RECURSOS_DIR, 0777, true)) {
            enviarResposta(false, "Não foi possível criar a pasta de recursos. Verifique as permissões.");
        }
    }
    if (!is_dir(UPLOAD_TRABALHOS_DIR)) {
        if (!mkdir(UPLOAD_TRABALHOS_DIR, 0777, true)) {
            enviarResposta(false, "Não foi possível criar a pasta de trabalhos. Verifique as permissões.");
        }
    }
    // Verificar permissões de escrita
    if (!is_writable(UPLOAD_RECURSOS_DIR)) {
        enviarResposta(false, "A pasta de recursos não tem permissão de escrita. Defina chmod 755 ou 777.");
    }
    if (!is_writable(UPLOAD_TRABALHOS_DIR)) {
        enviarResposta(false, "A pasta de trabalhos não tem permissão de escrita. Defina chmod 755 ou 777.");
    }
}

function gerarNomeUnico($arquivo_original) {
    $ext = strtolower(pathinfo($arquivo_original, PATHINFO_EXTENSION));
    return uniqid() . '_' . time() . '.' . $ext;
}

function validarArquivo($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Erro no upload: código " . $file['error']);
        return false;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        error_log("Tamanho excede o limite: " . $file['size'] . " > " . MAX_FILE_SIZE);
        return false;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $GLOBALS['ALLOWED_EXTENSIONS'])) {
        error_log("Extensão não permitida: " . $ext);
        return false;
    }
    return true;
}

function getFichaIdByUsuarioId($conn, $usuario_id) {
    $sql = "SELECT id FROM fichas WHERE usuario_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) return $row['id'];
    return null;
}

function verificarProfessorPodeAcessarAluno($conn, $professor_nome, $ficha_id) {
    $sql = "SELECT id FROM fichas WHERE id = ? AND professor_atribuido = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $ficha_id, $professor_nome);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// ---------- VALIDAÇÃO DE SESSÃO E PERFIL ----------
if (!isset($_SESSION['usuario_id'])) {
    enviarResposta(false, "Acesso negado. Faça login.", 401);
}
$tipo_usuario = strtolower($_SESSION['usuario_tipo'] ?? '');
$is_professor = ($tipo_usuario === 'professor' || $tipo_usuario === 'docente');
$is_aluno = ($tipo_usuario === 'aluno');
$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'] ?? '';

// Criar pastas e verificar permissões
criarPastas();

// ---------- PROCESSAR AÇÃO (com try-catch global) ----------
$acao = $_REQUEST['acao'] ?? '';

try {
    switch ($acao) {
        // ========== PROFESSOR: PARTILHAR RECURSO ==========
        case 'upload_recurso':
            if (!$is_professor) enviarResposta(false, "Apenas professores podem partilhar recursos.", 403);
            
            $ficha_id = intval($_POST['ficha_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $tipo = $_POST['tipo'] ?? 'pdf';
            
            if (!$ficha_id || !$titulo) enviarResposta(false, "Título e aluno são obrigatórios.");
            if (!verificarProfessorPodeAcessarAluno($conn, $usuario_nome, $ficha_id)) {
                enviarResposta(false, "Você não pode partilhar recursos com este aluno.");
            }
            
            $ficheiro_path = '';
            if ($tipo === 'link') {
                $link = trim($_POST['link'] ?? '');
                if (empty($link)) enviarResposta(false, "URL é obrigatório.");
                $ficheiro_path = $link;
            } else {
                // Log detalhado do upload
                error_log("Iniciando upload de ficheiro. POST: " . print_r($_POST, true));
                error_log("FILES: " . print_r($_FILES, true));
                
                if (!isset($_FILES['ficheiro']) || $_FILES['ficheiro']['error'] !== UPLOAD_ERR_OK) {
                    $codigo_erro = $_FILES['ficheiro']['error'] ?? 'sem ficheiro';
                    $mensagem_erro = "Erro no upload (código $codigo_erro). ";
                    switch ($codigo_erro) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $mensagem_erro .= "Ficheiro excede o tamanho máximo permitido.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $mensagem_erro .= "O ficheiro foi apenas parcialmente carregado.";
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $mensagem_erro .= "Nenhum ficheiro foi enviado.";
                            break;
                        default:
                            $mensagem_erro .= "Erro desconhecido no upload.";
                    }
                    enviarResposta(false, $mensagem_erro);
                }
                
                if (!validarArquivo($_FILES['ficheiro'])) {
                    enviarResposta(false, "Ficheiro inválido. Tamanho máximo " . (MAX_FILE_SIZE/1024/1024) . "MB e extensões permitidas: " . implode(', ', $ALLOWED_EXTENSIONS));
                }
                
                $nome_arquivo = gerarNomeUnico($_FILES['ficheiro']['name']);
                $destino = UPLOAD_RECURSOS_DIR . $nome_arquivo;
                
                // Verificação extra da pasta
                if (!is_dir(UPLOAD_RECURSOS_DIR)) {
                    if (!mkdir(UPLOAD_RECURSOS_DIR, 0777, true)) {
                        enviarResposta(false, "Não foi possível criar a pasta de uploads.");
                    }
                }
                if (!is_writable(UPLOAD_RECURSOS_DIR)) {
                    enviarResposta(false, "A pasta de uploads não tem permissão de escrita. Contacte o administrador.");
                }
                
                if (!move_uploaded_file($_FILES['ficheiro']['tmp_name'], $destino)) {
                    error_log("Falha ao mover ficheiro para $destino. Último erro: " . error_get_last()['message'] ?? 'sem erro');
                    enviarResposta(false, "Erro ao salvar o ficheiro no servidor. Verifique as permissões da pasta.");
                }
                $ficheiro_path = str_replace(__DIR__ . '/', '', $destino);
            }
            
            $sql = "INSERT INTO recursos (ficha_id, titulo, descricao, ficheiro, tipo) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $ficha_id, $titulo, $descricao, $ficheiro_path, $tipo);
            if ($stmt->execute()) {
                if (function_exists('criarNotificacao')) {
                    $sql_aluno = "SELECT usuario_id FROM fichas WHERE id = ?";
                    $stmt_a = $conn->prepare($sql_aluno);
                    $stmt_a->bind_param("i", $ficha_id);
                    $stmt_a->execute();
                    if ($aluno_row = $stmt_a->get_result()->fetch_assoc()) {
                        criarNotificacao($conn, $aluno_row['usuario_id'], 'aluno', 
                            "Novo recurso partilhado", 
                            "O professor partilhou: $titulo", 
                            "../dashboard.php#recursos");
                    }
                }
                enviarResposta(true, "Recurso partilhado com sucesso!", 200, ['id' => $stmt->insert_id]);
            } else {
                enviarResposta(false, "Erro ao registar recurso: " . $conn->error);
            }
            break;
            
        // ========== PROFESSOR: LISTAR TODOS OS SEUS RECURSOS ==========
        case 'listar_todos_recursos':
            if (!$is_professor) enviarResposta(false, "Apenas professores.", 403);
            $sql = "SELECT r.*, f.nome as aluno_nome 
                    FROM recursos r 
                    JOIN fichas f ON f.id = r.ficha_id 
                    WHERE f.professor_atribuido = ? 
                    ORDER BY r.data_upload DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $usuario_nome);
            $stmt->execute();
            $result = $stmt->get_result();
            $recursos = [];
            while ($row = $result->fetch_assoc()) {
                $row['data_formatada'] = date('d/m/Y H:i', strtotime($row['data_upload']));
                $recursos[] = $row;
            }
            enviarResposta(true, "OK", 200, ['recursos' => $recursos]);
            break;
            
        // ========== APAGAR RECURSO (só professor) ==========
        case 'apagar_recurso':
            if (!$is_professor) enviarResposta(false, "Apenas professores.", 403);
            $recurso_id = intval($_POST['recurso_id'] ?? 0);
            if (!$recurso_id) enviarResposta(false, "ID inválido.");
            
            $sql = "SELECT r.*, f.professor_atribuido FROM recursos r JOIN fichas f ON f.id = r.ficha_id WHERE r.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $recurso_id);
            $stmt->execute();
            $recurso = $stmt->get_result()->fetch_assoc();
            if (!$recurso || $recurso['professor_atribuido'] != $usuario_nome) {
                enviarResposta(false, "Sem permissão.");
            }
            if ($recurso['tipo'] !== 'link' && file_exists(__DIR__ . '/' . $recurso['ficheiro'])) {
                unlink(__DIR__ . '/' . $recurso['ficheiro']);
            }
            $sql_del = "DELETE FROM recursos WHERE id = ?";
            $stmt_del = $conn->prepare($sql_del);
            $stmt_del->bind_param("i", $recurso_id);
            if ($stmt_del->execute()) {
                enviarResposta(true, "Recurso apagado.");
            } else {
                enviarResposta(false, "Erro ao apagar.");
            }
            break;
            
        // ========== ALUNO: LISTAR SEUS RECURSOS ==========
        case 'listar_recursos':
            if (!$is_aluno) enviarResposta(false, "Apenas alunos.", 403);
            $ficha_id = getFichaIdByUsuarioId($conn, $usuario_id);
            if (!$ficha_id) enviarResposta(false, "Ficha não encontrada. Complete o seu cadastro.");
            $sql = "SELECT * FROM recursos WHERE ficha_id = ? ORDER BY data_upload DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $ficha_id);
            $stmt->execute();
            $recursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($recursos as &$r) {
                $r['data_formatada'] = date('d/m/Y H:i', strtotime($r['data_upload']));
            }
            enviarResposta(true, "OK", 200, ['recursos' => $recursos]);
            break;
            
        // ========== ALUNO: SUBMETER TRABALHO ==========
        case 'submeter_trabalho':
            if (!$is_aluno) enviarResposta(false, "Apenas alunos.", 403);
            $ficha_id = getFichaIdByUsuarioId($conn, $usuario_id);
            if (!$ficha_id) enviarResposta(false, "Ficha não encontrada. Complete o seu cadastro.");
            
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $tipo_entrega = $_POST['tipo_entrega'] ?? 'ficheiro';
            if (!$titulo) enviarResposta(false, "Título obrigatório.");
            
            $ficheiro_path = null;
            $link = null;
            if ($tipo_entrega === 'ficheiro') {
                if (!isset($_FILES['ficheiro']) || !validarArquivo($_FILES['ficheiro'])) {
                    enviarResposta(false, "Ficheiro inválido. Tamanho máximo " . (MAX_FILE_SIZE/1024/1024) . "MB e extensões permitidas: " . implode(', ', $ALLOWED_EXTENSIONS));
                }
                $nome_arquivo = gerarNomeUnico($_FILES['ficheiro']['name']);
                $destino = UPLOAD_TRABALHOS_DIR . $nome_arquivo;
                if (!move_uploaded_file($_FILES['ficheiro']['tmp_name'], $destino)) {
                    enviarResposta(false, "Erro ao salvar o ficheiro. Verifique as permissões da pasta.");
                }
                $ficheiro_path = str_replace(__DIR__ . '/', '', $destino);
            } else {
                $link = trim($_POST['link'] ?? '');
                if (empty($link)) enviarResposta(false, "Link obrigatório.");
            }
            
            $sql = "INSERT INTO trabalhos (ficha_id, titulo, descricao, ficheiro, link, status) VALUES (?, ?, ?, ?, ?, 'pendente')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $ficha_id, $titulo, $descricao, $ficheiro_path, $link);
            if ($stmt->execute()) {
                if (function_exists('criarNotificacao')) {
                    $sql_prof = "SELECT professor_atribuido FROM fichas WHERE id = ?";
                    $stmt_p = $conn->prepare($sql_prof);
                    $stmt_p->bind_param("i", $ficha_id);
                    $stmt_p->execute();
                    $prof_row = $stmt_p->get_result()->fetch_assoc();
                    if ($prof_row && $prof_row['professor_atribuido']) {
                        $nome_prof = $prof_row['professor_atribuido'];
                        $sql_user = "SELECT id FROM usuarios WHERE nome = ? AND (tipo = 'professor' OR tipo = 'docente')";
                        $stmt_u = $conn->prepare($sql_user);
                        $stmt_u->bind_param("s", $nome_prof);
                        $stmt_u->execute();
                        if ($prof_user = $stmt_u->get_result()->fetch_assoc()) {
                            criarNotificacao($conn, $prof_user['id'], 'professor',
                                "Novo trabalho submetido",
                                "O aluno submeteu: $titulo",
                                "../dashboard_professor.php?secao=trabalhos");
                        }
                    }
                }
                enviarResposta(true, "Trabalho submetido com sucesso!");
            } else {
                enviarResposta(false, "Erro ao submeter: " . $conn->error);
            }
            break;
            
        // ========== PROFESSOR: LISTAR TRABALHOS DOS ALUNOS ==========
        case 'listar_trabalhos':
            if (!$is_professor) enviarResposta(false, "Apenas professores.", 403);
            $sql = "SELECT t.*, f.nome as aluno_nome 
                    FROM trabalhos t 
                    JOIN fichas f ON f.id = t.ficha_id 
                    WHERE f.professor_atribuido = ? 
                    ORDER BY t.data_submissao DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $usuario_nome);
            $stmt->execute();
            $trabalhos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($trabalhos as &$t) {
                $t['data_formatada'] = date('d/m/Y H:i', strtotime($t['data_submissao']));
            }
            enviarResposta(true, "OK", 200, ['trabalhos' => $trabalhos]);
            break;
            
        // ========== PROFESSOR: AVALIAR TRABALHO ==========
        case 'avaliar_trabalho':
            if (!$is_professor) enviarResposta(false, "Apenas professores.", 403);
            $trabalho_id = intval($_POST['trabalho_id'] ?? 0);
            $nota = floatval($_POST['nota'] ?? 0);
            $feedback = trim($_POST['feedback'] ?? '');
            if (!$trabalho_id) enviarResposta(false, "ID inválido.");
            
            $sql_check = "SELECT t.id, f.usuario_id, f.professor_atribuido 
                          FROM trabalhos t 
                          JOIN fichas f ON f.id = t.ficha_id 
                          WHERE t.id = ?";
            $stmt = $conn->prepare($sql_check);
            $stmt->bind_param("i", $trabalho_id);
            $stmt->execute();
            $trab = $stmt->get_result()->fetch_assoc();
            if (!$trab || $trab['professor_atribuido'] != $usuario_nome) {
                enviarResposta(false, "Sem permissão.");
            }
            
            $sql_up = "UPDATE trabalhos SET nota = ?, feedback = ?, status = 'avaliado' WHERE id = ?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("dsi", $nota, $feedback, $trabalho_id);
            if ($stmt_up->execute()) {
                if (function_exists('criarNotificacao')) {
                    criarNotificacao($conn, $trab['usuario_id'], 'aluno',
                        "Trabalho avaliado",
                        "Seu trabalho foi avaliado com nota $nota. Feedback: " . ($feedback ?: "Sem comentários"),
                        "../dashboard.php#recursos");
                }
                enviarResposta(true, "Avaliado com sucesso!");
            } else {
                enviarResposta(false, "Erro ao avaliar.");
            }
            break;
            
        // ========== ALUNO: LISTAR MEUS TRABALHOS ==========
        case 'listar_meus_trabalhos':
            if (!$is_aluno) enviarResposta(false, "Apenas alunos.", 403);
            $ficha_id = getFichaIdByUsuarioId($conn, $usuario_id);
            if (!$ficha_id) enviarResposta(false, "Ficha não encontrada.");
            $sql = "SELECT * FROM trabalhos WHERE ficha_id = ? ORDER BY data_submissao DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $ficha_id);
            $stmt->execute();
            $trabalhos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($trabalhos as &$t) {
                $t['data_formatada'] = date('d/m/Y H:i', strtotime($t['data_submissao']));
            }
            enviarResposta(true, "OK", 200, ['trabalhos' => $trabalhos]);
            break;
            
        // ========== DOWNLOAD DE RECURSO (para aluno) ==========
        case 'baixar_recurso':
            $recurso_id = intval($_GET['id'] ?? 0);
            if (!$recurso_id) enviarResposta(false, "ID inválido.", 400);
            
            $sql = "SELECT r.*, f.professor_atribuido, f.usuario_id 
                    FROM recursos r 
                    JOIN fichas f ON f.id = r.ficha_id 
                    WHERE r.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $recurso_id);
            $stmt->execute();
            $recurso = $stmt->get_result()->fetch_assoc();
            if (!$recurso) enviarResposta(false, "Recurso não encontrado.", 404);
            
            $podeBaixar = false;
            if ($is_professor) {
                $podeBaixar = ($recurso['professor_atribuido'] == $usuario_nome);
            } elseif ($is_aluno) {
                $ficha_id_aluno = getFichaIdByUsuarioId($conn, $usuario_id);
                $podeBaixar = ($ficha_id_aluno == $recurso['ficha_id']);
            }
            if (!$podeBaixar) enviarResposta(false, "Sem permissão.", 403);
            
            if ($recurso['tipo'] == 'link') {
                header("Location: " . $recurso['ficheiro']);
                exit;
            }
            $arquivo = __DIR__ . '/' . $recurso['ficheiro'];
            if (!file_exists($arquivo)) enviarResposta(false, "Ficheiro não encontrado no servidor.", 404);
            
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($recurso['titulo'] . '.' . pathinfo($arquivo, PATHINFO_EXTENSION)) . '"');
            header('Content-Length: ' . filesize($arquivo));
            readfile($arquivo);
            exit;
            break;
            
        // ========== DOWNLOAD DE TRABALHO (para professor) ==========
        case 'baixar_trabalho':
            if (!$is_professor) enviarResposta(false, "Apenas professores.", 403);
            $trabalho_id = intval($_GET['id'] ?? 0);
            if (!$trabalho_id) enviarResposta(false, "ID inválido.", 400);
            
            $sql = "SELECT t.*, f.professor_atribuido 
                    FROM trabalhos t 
                    JOIN fichas f ON f.id = t.ficha_id 
                    WHERE t.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $trabalho_id);
            $stmt->execute();
            $trabalho = $stmt->get_result()->fetch_assoc();
            if (!$trabalho || $trabalho['professor_atribuido'] != $usuario_nome) {
                enviarResposta(false, "Sem permissão.", 403);
            }
            if ($trabalho['link']) {
                header("Location: " . $trabalho['link']);
                exit;
            }
            $arquivo = __DIR__ . '/' . $trabalho['ficheiro'];
            if (!file_exists($arquivo)) enviarResposta(false, "Ficheiro não encontrado.", 404);
            
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($trabalho['titulo'] . '.' . pathinfo($arquivo, PATHINFO_EXTENSION)) . '"');
            header('Content-Length: ' . filesize($arquivo));
            readfile($arquivo);
            exit;
            break;
            
        default:
            enviarResposta(false, "Ação inválida. Ação recebida: '$acao'", 400);
    }
} catch (Exception $e) {
    error_log("Exceção capturada: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    enviarResposta(false, "Erro interno do servidor: " . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("Erro fatal capturado: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    enviarResposta(false, "Erro fatal: " . $e->getMessage(), 500);
}

$conn->close();
?>