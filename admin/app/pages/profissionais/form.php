<?php
// ═══════════════════════════════════════════════
// PROCESSAMENTO PHP — ANTES DE QUALQUER HTML
// ═══════════════════════════════════════════════
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../config/permissoes.php");

//exigirLogin();
//exigirPermissao($pdo, 'profissionais', 'pode_ver');

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id          = $_GET['id'] ?? null;
$profissional = null;
$horarios    = [];
$error       = null;

if ($id) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nome, u.email, u.telefone, u.perfil AS usuario_perfil,
               u.ativo AS usuario_ativo
        FROM profissionais p
        JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.id = :id AND p.estabelecimento_id = :estab_id
    ");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $profissional = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profissional) {
        header("Location: index.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE profissional_id = :prof_id ORDER BY dia_semana ASC");
    $stmt->execute(['prof_id' => $id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $horarios[$row['dia_semana']] = $row;
    }
}

// ── Processamento do POST ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome                = sanitize($_POST['nome']);
    $email               = sanitize($_POST['email'] ?? '');
    $telefone            = sanitize($_POST['telefone'] ?? '');
    $cargo               = sanitize($_POST['cargo'] ?? '');
    $comissao_percentual = (float)($_POST['comissao_percentual'] ?? 0);
    $ativo               = isset($_POST['ativo']) ? 1 : 0;
    $tem_acesso          = isset($_POST['tem_acesso']) ? 1 : 0;
    $perfil_acesso       = in_array($_POST['perfil_acesso'] ?? '', ['profissional','atendente'])
                           ? $_POST['perfil_acesso']
                           : 'profissional';
    $senha               = $_POST['senha'] ?? '';
    $senha_confirma      = $_POST['senha_confirma'] ?? '';

    // Validações
    if (empty($nome)) {
        $error = "O nome é obrigatório.";
    } elseif ($tem_acesso && empty($email)) {
        $error = "E-mail é obrigatório para profissionais com acesso ao sistema.";
    } elseif ($tem_acesso && !$id && empty($senha)) {
        $error = "Defina uma senha para o acesso ao sistema.";
    } elseif ($tem_acesso && !empty($senha) && $senha !== $senha_confirma) {
        $error = "As senhas não conferem.";
    } elseif ($tem_acesso && !empty($senha) && strlen($senha) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres.";
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            if ($id) {
                // ── Edição ─────────────────────────
                $stmt = $pdo->prepare("
                    UPDATE usuarios
                    SET nome = ?, email = ?, telefone = ?, perfil = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nome,
                    $email ?: null,
                    $telefone ?: null,
                    $tem_acesso ? $perfil_acesso : 'profissional',
                    $profissional['usuario_id']
                ]);

                // Atualiza senha se informou nova
                if ($tem_acesso && !empty($senha)) {
                    $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                    $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")
                        ->execute([$hash, $profissional['usuario_id']]);
                }

                // Remove acesso se desmarcou
                if (!$tem_acesso) {
                    $pdo->prepare("UPDATE usuarios SET senha_hash = NULL, perfil = 'profissional' WHERE id = ?")
                        ->execute([$profissional['usuario_id']]);
                }

                $pdo->prepare("
                    UPDATE profissionais
                    SET cargo = ?, comissao_percentual = ?, ativo = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$cargo, $comissao_percentual, $ativo, $id]);

            } else {
                // ── Cadastro ───────────────────────
                // Verifica e-mail duplicado se informado
                if (!empty($email)) {
                    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
                    $chk->execute([$email]);
                    if ($chk->fetchColumn()) {
                        throw new Exception("Este e-mail já está cadastrado no sistema.");
                    }
                }

                $perfil_usuario = $tem_acesso ? $perfil_acesso : 'profissional';
                $hash = $tem_acesso && !empty($senha)
                        ? password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12])
                        : null;

                $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
                    mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
                    mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
                );
                $prof_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
                    mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
                    mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
                );

                $pdo->prepare("
                    INSERT INTO usuarios (id, nome, email, telefone, senha_hash, perfil, ativo)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ")->execute([$user_id, $nome, $email ?: null, $telefone ?: null, $hash, $perfil_usuario]);

                $pdo->prepare("
                    INSERT INTO profissionais (id, usuario_id, estabelecimento_id, cargo, comissao_percentual, ativo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$prof_id, $user_id, $estabelecimento_id, $cargo, $comissao_percentual, $ativo]);

                $id = $prof_id;
            }

            // ── Horários de trabalho ────────────────
            $pdo->prepare("DELETE FROM horarios_funcionamento WHERE profissional_id = ?")->execute([$id]);

            if (!empty($_POST['dias'])) {
                $stmtH = $pdo->prepare("
                    INSERT INTO horarios_funcionamento
                      (id, estabelecimento_id, profissional_id, dia_semana, hora_inicio, hora_fim, intervalo_minutos)
                    VALUES (UUID(), ?, ?, ?, ?, ?, ?)
                ");
                foreach ($_POST['dias'] as $dia => $valor) {
                    $h_inicio  = $_POST['hora_inicio'][$dia] ?? '08:00';
                    $h_fim     = $_POST['hora_fim'][$dia]    ?? '18:00';
                    $intervalo = (int)($_POST['intervalo'][$dia] ?? 30);
                    $stmtH->execute([$estabelecimento_id, $id, $dia, $h_inicio, $h_fim, $intervalo]);
                }
            }

            $pdo->commit();
            header("Location: index.php?success=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

$dias_semana = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];

// HTML somente aqui
require_once("../../top/topo.php");
$active_menu = 'profissionais';
require_once("../../menu/menu.php");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><?php echo $id ? 'Editar' : 'Novo'; ?> Profissional</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="row">

                    <!-- ── Dados Pessoais ── -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Dados Pessoais</h3></div>
                            <div class="card-body">

                                <div class="mb-3">
                                    <label class="form-label">Nome *</label>
                                    <input type="text" name="nome" class="form-control"
                                           value="<?php echo htmlspecialchars($profissional['nome'] ?? ''); ?>" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cargo</label>
                                        <input type="text" name="cargo" class="form-control"
                                               value="<?php echo htmlspecialchars($profissional['cargo'] ?? ''); ?>"
                                               placeholder="Ex: Barbeiro, Manicure">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Comissão (%)</label>
                                        <input type="number" name="comissao_percentual" class="form-control"
                                               min="0" max="100" step="0.5"
                                               value="<?php echo $profissional['comissao_percentual'] ?? 0; ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" id="telefone" class="form-control"
                                           value="<?php echo htmlspecialchars($profissional['telefone'] ?? ''); ?>"
                                           placeholder="(88) 99999-9999">
                                </div>

                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="ativoSwitch"
                                           <?php echo (!$profissional || $profissional['ativo']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ativoSwitch">Profissional Ativo</label>
                                </div>
                            </div>
                        </div>

                        <!-- ── Acesso ao Sistema ── -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">Acesso ao Sistema</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="tem_acesso"
                                           id="temAcessoSwitch"
                                           <?php
                                             $tem_acesso_atual = $profissional && !empty($profissional['email']);
                                             echo $tem_acesso_atual ? 'checked' : '';
                                           ?>
                                           onchange="toggleAcesso(this.checked)">
                                    <label class="form-check-label" for="temAcessoSwitch">
                                        Este profissional tem login no sistema
                                    </label>
                                </div>

                                <div id="blocoAcesso" style="<?php echo $tem_acesso_atual ? '' : 'display:none;'; ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Perfil de Acesso</label>
                                        <select name="perfil_acesso" class="form-select">
                                            <option value="profissional"
                                                <?php echo ($profissional['usuario_perfil'] ?? '') === 'profissional' ? 'selected' : ''; ?>>
                                                Profissional — só vê a própria agenda e comissões
                                            </option>
                                            <option value="atendente"
                                                <?php echo ($profissional['usuario_perfil'] ?? '') === 'atendente' ? 'selected' : ''; ?>>
                                                Atendente — agenda, clientes e caixa do dia
                                            </option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">E-mail <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control"
                                               value="<?php echo htmlspecialchars($profissional['email'] ?? ''); ?>"
                                               placeholder="usado para fazer login">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">
                                                <?php echo $id ? 'Nova Senha (deixe em branco para não alterar)' : 'Senha *'; ?>
                                            </label>
                                            <input type="password" name="senha" class="form-control"
                                                   placeholder="Mínimo 6 caracteres"
                                                   autocomplete="new-password">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Confirmar Senha</label>
                                            <input type="password" name="senha_confirma" class="form-control"
                                                   autocomplete="new-password">
                                        </div>
                                    </div>

                                    <div class="alert alert-info py-2 mb-0" style="font-size:13px;">
                                        <i class="bi bi-info-circle"></i>
                                        O profissional fará login com o e-mail e senha definidos acima.
                                        As permissões detalhadas podem ser ajustadas em
                                        <a href="../permissoes/index.php">Configurações → Permissões</a>.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Horários de Trabalho ── -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Horários de Trabalho</h3></div>
                            <div class="card-body p-0">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:40px"></th>
                                            <th>Dia</th>
                                            <th>Início</th>
                                            <th>Fim</th>
                                            <th>Intervalo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dias_semana as $idx => $dia):
                                            $checked  = isset($horarios[$idx]);
                                            $h_ini    = $checked ? $horarios[$idx]['hora_inicio'] : '08:00';
                                            $h_fim    = $checked ? $horarios[$idx]['hora_fim']    : '18:00';
                                            $interval = $checked ? ($horarios[$idx]['intervalo_minutos'] ?? 30) : 30;
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="dias[<?php echo $idx; ?>]"
                                                           value="1" <?php echo $checked ? 'checked' : ''; ?>>
                                                </td>
                                                <td><?php echo $dia; ?></td>
                                                <td>
                                                    <input type="time" name="hora_inicio[<?php echo $idx; ?>]"
                                                           class="form-control form-control-sm"
                                                           value="<?php echo $h_ini; ?>">
                                                </td>
                                                <td>
                                                    <input type="time" name="hora_fim[<?php echo $idx; ?>]"
                                                           class="form-control form-control-sm"
                                                           value="<?php echo $h_fim; ?>">
                                                </td>
                                                <td>
                                                    <select name="intervalo[<?php echo $idx; ?>]"
                                                            class="form-select form-select-sm">
                                                        <option value="15"  <?php echo $interval==15  ? 'selected':'' ?>>15 min</option>
                                                        <option value="30"  <?php echo $interval==30  ? 'selected':'' ?>>30 min</option>
                                                        <option value="45"  <?php echo $interval==45  ? 'selected':'' ?>>45 min</option>
                                                        <option value="60"  <?php echo $interval==60  ? 'selected':'' ?>>60 min</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer text-muted" style="font-size:12px;">
                                <i class="bi bi-info-circle"></i>
                                Marque os dias em que o profissional trabalha. O intervalo define o espaço entre os horários disponíveis para agendamento.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 mb-5">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Salvar Profissional
                    </button>
                    <a href="index.php" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function toggleAcesso(ativo) {
    document.getElementById('blocoAcesso').style.display = ativo ? 'block' : 'none';
}

document.getElementById('telefone')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').substring(0,11);
    if (v.length > 6)      v = '('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);
    else if (v.length > 2) v = '('+v.substring(0,2)+') '+v.substring(2);
    else if (v.length > 0) v = '('+v;
    this.value = v;
});
</script>

<?php require_once("../../layout/footer.php"); ?>