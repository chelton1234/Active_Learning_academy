<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json'); // Resposta em JSON

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de conexão: ' . $conn->connect_error]);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// ========== VERIFICAR SE É REQUISIÇÃO JSON ==========
$input = json_decode(file_get_contents('php://input'), true);

// Se for requisição AJAX com JSON
if ($input && isset($input['acao']) && $input['acao'] === 'salvar') {
    processarJSON($conn, $usuario_id, $input['dados']);
    exit;
}

// Se for requisição tradicional POST (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    processarPOST($conn, $usuario_id);
    exit;
}

// Se não for nenhum dos casos
http_response_code(400);
echo json_encode(['sucesso' => false, 'mensagem' => 'Requisição inválida']);
exit;

// ========== FUNÇÃO PARA PROCESSAR JSON (AJAX) ==========
function processarJSON($conn, $usuario_id, $dados) {
    // ========== DADOS DO FORMULÁRIO ==========
    $nome = $conn->real_escape_string($dados['nome'] ?? '');
    $classe = $conn->real_escape_string($dados['classe'] ?? '');
    $sexo = $dados['sexo'] ?? '';
    $localizacao = $conn->real_escape_string($dados['localizacao'] ?? '');
    $contacto_encarregado = $conn->real_escape_string($dados['contacto_encarregado'] ?? '');
    $escola = $conn->real_escape_string($dados['escola'] ?? '');
    $nivel = $dados['nivel'] ?? '';
    $pacote = $dados['pacote'] ?? '';
    $dificuldade = $conn->real_escape_string($dados['dificuldade'] ?? '');
    $valor_total = isset($dados['valor_total']) ? floatval($dados['valor_total']) : 0.0;
    
    // Arrays e JSON
    $dias_semana = isset($dados['dias']) ? $dados['dias'] : [];
    $dias_semana_json = json_encode($dias_semana);
    
    // Horários (formato: { "segunda": "07:00", "terca": "08:30" })
    $horarios = isset($dados['horarios']) ? $dados['horarios'] : [];
    $horarios_json = json_encode($horarios);
    
    // Checkboxes
    $regime_presencial = isset($dados['presencial']) && $dados['presencial'] ? 1 : 0;
    $regime_online = isset($dados['online']) && $dados['online'] ? 1 : 0;
    $regime_domicilio = isset($dados['domicilio']) && $dados['domicilio'] ? 1 : 0;
    
    // ========== VALIDAÇÕES ==========
    $campos_obrigatorios = [
        'nome', 'classe', 'sexo', 'localizacao', 
        'contacto_encarregado', 'escola', 'nivel', 'pacote', 'dificuldade'
    ];
    
    foreach ($campos_obrigatorios as $campo) {
        if (empty($dados[$campo] ?? '')) {
            echo json_encode(['sucesso' => false, 'mensagem' => "Campo obrigatório faltando: $campo"]);
            exit;
        }
    }
    
    // ========== CÁLCULOS ==========
    $valor_mensal = match($pacote) {
        'basico' => 3000,
        'intermedio' => 4000,
        'premium' => 5000,
        default => 0
    };
    
    $valor_final = $valor_mensal;
    if ($regime_domicilio) {
        $valor_final = $valor_mensal + 1000;
    }
    
    if ($valor_total > 0 && $valor_total != $valor_final) {
        $valor_final = $valor_total;
    }
    
    // ========== CAMPOS LEGACY (valores padrão) ==========
    $idade = 0;
    $data_nascimento = null;
    $internet_casa = 0;
    $provincia = 'Maputo';
    $regime_hibrido = 0;
    $pagamento_status = 'pendente';
    $permite_finsemana = ($pacote === 'basico') ? 1 : 0;
    
    // ========== INSERIR NO BANCO ==========
    try {
        $sql = "INSERT INTO fichas (
            usuario_id, nome, classe, sexo, localizacao, 
            contacto_encarregado, escola, nivel, dificuldade, pacote,
            valor_total, valor_mensal, regime_presencial, regime_online, regime_domicilio,
            dias_semana, horarios_json, provincia, idade, data_nascimento,
            internet_casa, regime_hibrido, pagamento_status, permite_finsemana,
            data_submissao
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            NOW()
        )";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erro na preparação: " . $conn->error);
        }
        
        $stmt->bind_param("isssssssssddiiiissssisss",
            $usuario_id,           // i
            $nome,                 // s
            $classe,               // s
            $sexo,                 // s
            $localizacao,          // s
            $contacto_encarregado, // s
            $escola,               // s
            $nivel,                // s
            $dificuldade,          // s
            $pacote,               // s
            $valor_final,          // d
            $valor_mensal,         // d
            $regime_presencial,    // i
            $regime_online,        // i
            $regime_domicilio,     // i
            $dias_semana_json,     // s
            $horarios_json,        // s
            $provincia,            // s
            $idade,                // i
            $data_nascimento,      // s
            $internet_casa,        // i
            $regime_hibrido,       // i
            $pagamento_status,     // s
            $permite_finsemana     // i
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar: " . $stmt->error);
        }
        
        $ficha_id = $conn->insert_id;
        $stmt->close();
        
        // ========== SALVAR HORÁRIOS ==========
        if (!empty($horarios)) {
            // Criar tabela de horários se não existir
            $conn->query("CREATE TABLE IF NOT EXISTS horarios_aulas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ficha_id INT NOT NULL,
                dia_semana VARCHAR(20) NOT NULL,
                horario VARCHAR(20) NOT NULL,
                FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
                INDEX idx_ficha (ficha_id)
            )");
            
            // Inserir novos horários
            $stmt_horario = $conn->prepare("INSERT INTO horarios_aulas (ficha_id, dia_semana, horario) VALUES (?, ?, ?)");
            
            foreach ($horarios as $dia => $horario) {
                if (!empty($horario)) {
                    $stmt_horario->bind_param("iss", $ficha_id, $dia, $horario);
                    $stmt_horario->execute();
                }
            }
            $stmt_horario->close();
        }
        
        // ========== RESPOSTA DE SUCESSO ==========
        echo json_encode([
            'sucesso' => true,
            'ficha_id' => $ficha_id,
            'mensagem' => 'Ficha salva com sucesso'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao salvar: ' . $e->getMessage()
        ]);
    }
    
    $conn->close();
}

// ========== FUNÇÃO PARA PROCESSAR POST TRADICIONAL (fallback) ==========
function processarPOST($conn, $usuario_id) {
    // ========== DADOS DO FORMULÁRIO ==========
    $ficha_id = $_POST['ficha_id'] ?? null;
    $nome = $conn->real_escape_string($_POST['nome'] ?? '');
    $classe = $conn->real_escape_string($_POST['classe'] ?? '');
    $sexo = $_POST['sexo'] ?? '';
    $localizacao = $conn->real_escape_string($_POST['localizacao'] ?? '');
    $contacto_encarregado = $conn->real_escape_string($_POST['contacto_encarregado'] ?? '');
    $escola = $conn->real_escape_string($_POST['escola'] ?? '');
    $nivel = $_POST['nivel'] ?? '';
    $pacote = $_POST['pacote'] ?? '';
    $dificuldade = $conn->real_escape_string($_POST['dificuldade'] ?? '');
    $valor_total = isset($_POST['valor_total']) ? floatval($_POST['valor_total']) : 0.0;
    $pagamento_status = $_POST['pagamento_status'] ?? 'pendente';
    
    // Arrays e JSON
    $dias_semana = isset($_POST['dias']) ? $_POST['dias'] : [];
    $dias_semana_json = json_encode($dias_semana);
    $horarios_json = isset($_POST['horarios_json']) ? $_POST['horarios_json'] : '{}';
    $horarios = json_decode($horarios_json, true);
    
    // Checkboxes
    $regime_presencial = isset($_POST['presencial']) ? 1 : 0;
    $regime_online = isset($_POST['online']) ? 1 : 0;
    $regime_domicilio = isset($_POST['domicilio']) ? 1 : 0;
    
    // ========== VALIDAÇÕES ==========
    $campos_obrigatorios = [
        'nome', 'classe', 'sexo', 'localizacao', 
        'contacto_encarregado', 'escola', 'nivel', 'pacote', 'dificuldade'
    ];
    
    foreach ($campos_obrigatorios as $campo) {
        if (empty($_POST[$campo] ?? '')) {
            http_response_code(400);
            echo "Campo obrigatório faltando: $campo";
            exit;
        }
    }
    
    // ========== CÁLCULOS ==========
    $valor_mensal = match($pacote) {
        'basico' => 3000,
        'intermedio' => 4000,
        'premium' => 5000,
        default => 0
    };
    
    $valor_final = $valor_mensal;
    if ($regime_domicilio) {
        $valor_final = $valor_mensal + 1000;
    }
    
    if ($valor_total > 0 && $valor_total != $valor_final) {
        $valor_final = $valor_total;
    }
    
    // ========== CAMPOS LEGACY ==========
    $idade = 0;
    $data_nascimento = null;
    $internet_casa = 0;
    $provincia = 'Maputo';
    $regime_hibrido = 0;
    $permite_finsemana = ($pacote === 'basico') ? 1 : 0;
    
    // ========== INSERIR/ATUALIZAR ==========
    try {
        if (!empty($ficha_id)) {
            // Verificar se a ficha pertence ao usuário
            $check_stmt = $conn->prepare("SELECT id FROM fichas WHERE id = ? AND usuario_id = ?");
            $check_stmt->bind_param("ii", $ficha_id, $usuario_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows == 0) {
                http_response_code(403);
                echo "Não autorizado para editar esta ficha.";
                $check_stmt->close();
                exit;
            }
            $check_stmt->close();
            
            // UPDATE
            $stmt = $conn->prepare("UPDATE fichas 
                SET nome = ?, classe = ?, sexo = ?, localizacao = ?, 
                    contacto_encarregado = ?, escola = ?, nivel = ?, dificuldade = ?, 
                    pacote = ?, valor_total = ?, valor_mensal = ?, 
                    regime_presencial = ?, regime_online = ?, regime_domicilio = ?,
                    dias_semana = ?, horarios_json = ?, provincia = ?, idade = ?, 
                    data_nascimento = ?, internet_casa = ?, regime_hibrido = ?,
                    permite_finsemana = ?
                WHERE id = ? AND usuario_id = ?");
            
            if (!$stmt) {
                throw new Exception("Erro na preparação: " . $conn->error);
            }
            
            $stmt->bind_param("sssssssssddiiiissssisii",
                $nome, $classe, $sexo, $localizacao,
                $contacto_encarregado, $escola, $nivel, $dificuldade, $pacote,
                $valor_final, $valor_mensal, $regime_presencial, 
                $regime_online, $regime_domicilio, $dias_semana_json,
                $horarios_json, $provincia, $idade, $data_nascimento,
                $internet_casa, $regime_hibrido, $permite_finsemana,
                $ficha_id, $usuario_id
            );
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO fichas 
                (usuario_id, nome, classe, sexo, localizacao, contacto_encarregado, 
                 escola, nivel, dificuldade, pacote, valor_total, valor_mensal,
                 regime_presencial, regime_online, regime_domicilio,
                 dias_semana, horarios_json, provincia, idade, data_nascimento, 
                 internet_casa, regime_hibrido, pagamento_status, permite_finsemana,
                 data_submissao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if (!$stmt) {
                throw new Exception("Erro na preparação: " . $conn->error);
            }
            
            $stmt->bind_param("isssssssssddiiiissssissi",
                $usuario_id,
                $nome, $classe, $sexo, $localizacao,
                $contacto_encarregado, $escola, $nivel, $dificuldade, $pacote,
                $valor_final, $valor_mensal, $regime_presencial, 
                $regime_online, $regime_domicilio, $dias_semana_json,
                $horarios_json, $provincia, $idade, $data_nascimento,
                $internet_casa, $regime_hibrido, $pagamento_status,
                $permite_finsemana
            );
        }
        
        if ($stmt->execute()) {
            $ficha_id = $ficha_id ?: $conn->insert_id;
            
            // ========== SALVAR HORÁRIOS ==========
            if (!empty($horarios) && is_array($horarios)) {
                // Criar tabela de horários
                $conn->query("CREATE TABLE IF NOT EXISTS horarios_aulas (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    ficha_id INT NOT NULL,
                    dia_semana VARCHAR(20) NOT NULL,
                    horario VARCHAR(20) NOT NULL,
                    FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE
                )");
                
                // Remover horários antigos se for edição
                if (!empty($_POST['ficha_id'])) {
                    $conn->query("DELETE FROM horarios_aulas WHERE ficha_id = $ficha_id");
                }
                
                // Inserir novos horários
                $stmt_horario = $conn->prepare("INSERT INTO horarios_aulas (ficha_id, dia_semana, horario) VALUES (?, ?, ?)");
                foreach ($horarios as $dia => $horario) {
                    if (!empty($horario)) {
                        $stmt_horario->bind_param("iss", $ficha_id, $dia, $horario);
                        $stmt_horario->execute();
                    }
                }
                $stmt_horario->close();
            }
            
            $stmt->close();
            $conn->close();
            
            // ========== RESPOSTA ==========
            if (isset($_GET['pagamento']) && $_GET['pagamento'] == 1) {
                echo $ficha_id;
            } else {
                header("Location: dashboard.php?status=success&ficha_id=" . $ficha_id);
            }
            exit;
            
        } else {
            throw new Exception("Erro ao executar: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo "Erro ao guardar ficha: " . $e->getMessage();
        if (isset($stmt)) $stmt->close();
        $conn->close();
        exit;
    }
}
?>