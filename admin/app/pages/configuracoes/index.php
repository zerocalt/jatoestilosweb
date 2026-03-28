<?php
// ═══════════════════════════════════════════════
// PROCESSAMENTO PHP — ANTES DE QUALQUER HTML
// ═══════════════════════════════════════════════
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$usuario_id         = $_SESSION['usuario_id']; // corrigido: era admin_id
$error   = null;
$success = null;

// Carrega dados
$stmt = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = ?");
$stmt->execute([$estabelecimento_id]);
$estab = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM politicas_agendamento WHERE estabelecimento_id = ?");
$stmt->execute([$estabelecimento_id]);
$politica = $stmt->fetch(PDO::FETCH_ASSOC);

// Carrega usuário atual (para verificar senha)
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$usuario_id]);
$usuario_atual = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // ── Dados do negócio ───────────────────
        if ($action === 'dados') {
            $pdo->prepare("
                UPDATE estabelecimentos
                SET nome=?, telefone=?, endereco=?, cidade=?, estado=?, updated_at=NOW()
                WHERE id=?
            ")->execute([
                sanitize($_POST['nome']),
                sanitize($_POST['telefone']),
                sanitize($_POST['endereco']),
                sanitize($_POST['cidade']),
                sanitize($_POST['estado']),
                $estabelecimento_id
            ]);
            $_SESSION['success'] = "Dados do negócio atualizados com sucesso!";
            header("Location: index.php"); exit;

        // ── Políticas de agendamento ───────────
        } elseif ($action === 'politicas') {
            $pdo->prepare("
                UPDATE politicas_agendamento
                SET antecedencia_min_minutos=?, antecedencia_max_dias=?,
                    cancelamento_limite_horas=?, lembrete_horas_antes=?
                WHERE estabelecimento_id=?
            ")->execute([
                (int)$_POST['antecedencia_min'],
                (int)$_POST['antecedencia_max'],
                (int)$_POST['cancelamento'],
                (int)$_POST['lembrete'],
                $estabelecimento_id
            ]);
            $_SESSION['success'] = "Políticas de agendamento salvas!";
            header("Location: index.php"); exit;

        // ── Alterar senha ──────────────────────
        } elseif ($action === 'senha') {
            $senha_atual    = $_POST['senha_atual']    ?? '';
            $nova_senha     = $_POST['nova_senha']     ?? '';
            $confirma_senha = $_POST['confirma_senha'] ?? '';

            // 1. Verifica senha atual
            if (empty($senha_atual)) {
                $error = "Informe sua senha atual para continuar.";

            } elseif (!password_verify($senha_atual, $usuario_atual['senha_hash'])) {
                $error = "Senha atual incorreta.";

            } elseif (empty($nova_senha)) {
                $error = "A nova senha não pode estar vazia.";

            } elseif (strlen($nova_senha) < 6) {
                $error = "A nova senha deve ter pelo menos 6 caracteres.";

            } elseif ($nova_senha !== $confirma_senha) {
                $error = "A nova senha e a confirmação não coincidem.";

            } elseif ($nova_senha === $senha_atual) {
                $error = "A nova senha deve ser diferente da senha atual.";

            } else {
                $hash = password_hash($nova_senha, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE usuarios SET senha_hash=?, updated_at=NOW() WHERE id=?")
                    ->execute([$hash, $usuario_id]);
                $_SESSION['success'] = "Senha alterada com sucesso!";
                header("Location: index.php"); exit;
            }
        }

    } catch (PDOException $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

// Mensagem de sessão
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// HTML somente aqui
require_once("../../top/topo.php");
$active_menu = 'configuracoes';
require_once("../../menu/menu.php");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0">Configurações do Estabelecimento</h3>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-circle-fill"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">

                    <!-- ── Dados do Negócio ── -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title"><i class="bi bi-shop me-2"></i>Dados do Negócio</h3>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="dados">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Nome Fantasia</label>
                                    <input type="text" name="nome" class="form-control"
                                           value="<?php echo htmlspecialchars($estab['nome']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" class="form-control"
                                           value="<?php echo htmlspecialchars($estab['telefone']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Endereço</label>
                                    <input type="text" name="endereco" class="form-control"
                                           value="<?php echo htmlspecialchars($estab['endereco']); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="cidade" class="form-control"
                                               value="<?php echo htmlspecialchars($estab['cidade']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">UF</label>
                                        <input type="text" name="estado" class="form-control"
                                               value="<?php echo htmlspecialchars($estab['estado']); ?>"
                                               maxlength="2" style="text-transform:uppercase;">
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Salvar Dados
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ── Segurança / Alterar Senha ── -->
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h3 class="card-title"><i class="bi bi-shield-lock me-2"></i>Segurança — Alterar Senha</h3>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="senha">
                            <div class="card-body">

                                <div class="alert alert-info py-2 mb-3" style="font-size:13px;">
                                    <i class="bi bi-info-circle"></i>
                                    Para alterar sua senha, confirme a <strong>senha atual</strong> primeiro.
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Senha Atual <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" name="senha_atual" id="senhaAtual"
                                               class="form-control" required autocomplete="current-password"
                                               placeholder="Digite sua senha atual">
                                        <button type="button" class="btn btn-outline-secondary"
                                                onclick="toggleSenha('senhaAtual')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Nova Senha <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" name="nova_senha" id="novaSenha"
                                               class="form-control" required autocomplete="new-password"
                                               placeholder="Mínimo 6 caracteres"
                                               oninput="checarForca(this.value)">
                                        <button type="button" class="btn btn-outline-secondary"
                                                onclick="toggleSenha('novaSenha')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="progress mt-1" style="height:4px;">
                                        <div id="forcaBarra" class="progress-bar" style="width:0%;transition:.3s;"></div>
                                    </div>
                                    <small id="forcaTexto" class="text-muted"></small>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label">Confirmar Nova Senha <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" name="confirma_senha" id="confirmaSenha"
                                               class="form-control" required autocomplete="new-password"
                                               placeholder="Repita a nova senha">
                                        <button type="button" class="btn btn-outline-secondary"
                                                onclick="toggleSenha('confirmaSenha')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-dark">
                                    <i class="bi bi-key"></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- ── Políticas de Agendamento ── -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h3 class="card-title"><i class="bi bi-calendar-check me-2"></i>Políticas de Agendamento</h3>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="politicas">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Antecedência Mínima (minutos)</label>
                                    <input type="number" name="antecedencia_min" class="form-control"
                                           value="<?php echo $politica['antecedencia_min_minutos'] ?? 60; ?>" min="0" required>
                                    <small class="text-muted">Tempo mínimo para permitir agendamento. Ex: 60 = 1 hora de antecedência.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Antecedência Máxima (dias)</label>
                                    <input type="number" name="antecedencia_max" class="form-control"
                                           value="<?php echo $politica['antecedencia_max_dias'] ?? 30; ?>" min="1" required>
                                    <small class="text-muted">Até quantos dias no futuro o cliente pode agendar.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Prazo para Cancelamento (horas)</label>
                                    <input type="number" name="cancelamento" class="form-control"
                                           value="<?php echo $politica['cancelamento_limite_horas'] ?? 2; ?>" min="0" required>
                                    <small class="text-muted">Horas antes do horário para cancelar sem taxas.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Enviar Lembrete (horas antes)</label>
                                    <input type="number" name="lembrete" class="form-control"
                                           value="<?php echo $politica['lembrete_horas_antes'] ?? 24; ?>" min="1" required>
                                    <small class="text-muted">Quando enviar o lembrete push ao cliente.</small>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-info text-white">
                                    <i class="bi bi-check-lg"></i> Salvar Políticas
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function toggleSenha(id) {
    const campo = document.getElementById(id);
    campo.type = campo.type === 'password' ? 'text' : 'password';
}

function checarForca(senha) {
    const barra  = document.getElementById('forcaBarra');
    const texto  = document.getElementById('forcaTexto');
    let pontos = 0;
    if (senha.length >= 6)  pontos++;
    if (senha.length >= 10) pontos++;
    if (/[A-Z]/.test(senha)) pontos++;
    if (/[0-9]/.test(senha)) pontos++;
    if (/[^A-Za-z0-9]/.test(senha)) pontos++;

    const niveis = [
        { pct:'20%', cor:'bg-danger',  txt:'Muito fraca'  },
        { pct:'40%', cor:'bg-danger',  txt:'Fraca'        },
        { pct:'60%', cor:'bg-warning', txt:'Razoável'     },
        { pct:'80%', cor:'bg-info',    txt:'Boa'          },
        { pct:'100%',cor:'bg-success', txt:'Muito forte'  },
    ];
    const n = niveis[Math.max(0, pontos - 1)];
    barra.style.width = n.pct;
    barra.className   = 'progress-bar ' + n.cor;
    texto.textContent = senha.length ? n.txt : '';
}
</script>

<?php require_once("../../layout/footer.php"); ?>
