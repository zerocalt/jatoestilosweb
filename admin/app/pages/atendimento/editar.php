<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../config/permissions.php");
exigirLogin();

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: checkin.php");
    exit;
}

try {
    // Carregar dados do agendamento
    $stmt = $pdo->prepare("SELECT * FROM vw_agenda_dia WHERE agendamento_id = :id AND estabelecimento_id = :estab_id");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug: exibir os dados do agendamento
    error_log("Agendamento carregado: " . print_r($agendamento, true));

    if (!$agendamento) {
        header("Location: checkin.php");
        exit;
    }

    // Carregar dados necessários
    $clientes = $pdo->prepare("SELECT id, nome FROM clientes WHERE estabelecimento_id = :estab_id AND deleted_at IS NULL AND bloqueado = 0 ORDER BY nome ASC");
    $clientes->execute(['estab_id' => $estabelecimento_id]);
    $lista_clientes = $clientes->fetchAll(PDO::FETCH_ASSOC);

    $profissionais = $pdo->prepare("SELECT p.id, u.nome FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE p.estabelecimento_id = :estab_id AND p.ativo = 1 ORDER BY u.nome ASC");
    $profissionais->execute(['estab_id' => $estabelecimento_id]);
    $lista_profissionais = $profissionais->fetchAll(PDO::FETCH_ASSOC);

    $servicos = $pdo->prepare("SELECT id, nome, duracao_minutos, valor_centavos FROM servicos WHERE estabelecimento_id = :estab_id AND ativo = 1 ORDER BY nome ASC");
    $servicos->execute(['estab_id' => $estabelecimento_id]);
    $lista_servicos = $servicos->fetchAll(PDO::FETCH_ASSOC);

    // Carregar serviços do agendamento
    $stmt = $pdo->prepare("SELECT servico_id FROM agendamento_servicos WHERE agendamento_id = :id");
    $stmt->execute(['id' => $id]);
    $servicos_selecionados = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?: null;
    $cliente_nome_avulso = sanitize($_POST['cliente_nome_avulso'] ?? '');
    $profissional_id = $_POST['profissional_id'];
    $servicos_selecionados = $_POST['servicos'] ?? [];
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    
    if (empty($servicos_selecionados)) {
        $error = "Selecione pelo menos um serviço.";
    } elseif (!$profissional_id) {
        $error = "Selecione um profissional.";
    } else {
        try {
            $pdo->beginTransaction();

            // Calcular duração total e valor
            $duracao_total = 0;
            $valor_total = 0;
            $servicos_detalhes = [];
            foreach ($servicos_selecionados as $s_id) {
                $st = $pdo->prepare("SELECT valor_centavos, duracao_minutos FROM servicos WHERE id = :id");
                $st->execute(['id' => $s_id]);
                $s_info = $st->fetch(PDO::FETCH_ASSOC);
                $duracao_total += $s_info['duracao_minutos'];
                $valor_total += $s_info['valor_centavos'];
                $servicos_detalhes[] = array_merge($s_info, ['id' => $s_id]);
            }

            // Atualizar agendamento
            $stmt = $pdo->prepare("UPDATE agendamentos SET cliente_id = :cli_id, cliente_nome_avulso = :cli_avulso, profissional_id = :prof_id, observacoes = :obs, valor_total_centavos = :total WHERE id = :id");
            $stmt->execute([
                'cli_id' => $cliente_id,
                'cli_avulso' => $cliente_nome_avulso,
                'prof_id' => $profissional_id,
                'obs' => $observacoes,
                'total' => $valor_total,
                'id' => $id
            ]);

            // Atualizar serviços do agendamento
            $stmt = $pdo->prepare("DELETE FROM agendamento_servicos WHERE agendamento_id = :id");
            $stmt->execute(['id' => $id]);

            foreach ($servicos_detalhes as $idx => $s_det) {
                $stmt = $pdo->prepare("INSERT INTO agendamento_servicos (id, agendamento_id, servico_id, valor_cobrado_centavos, duracao_minutos, ordem) VALUES (uuid(), :ag_id, :s_id, :valor, :duracao, :ordem)");
                $stmt->execute([
                    'ag_id' => $id,
                    's_id' => $s_det['id'],
                    'valor' => $s_det['valor_centavos'],
                    'duracao' => $s_det['duracao_minutos'],
                    'ordem' => $idx + 1
                ]);
            }

            $pdo->commit();
            header("Location: checkin.php?success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao editar check-in: " . $e->getMessage();
        }
    }
}

$active_menu = 'checkin';
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Editar Check-in</h3>
                </div>
                <div class="col-sm-6">
                    <div class="d-flex justify-content-end">
                        <span class="badge bg-warning">Em Atendimento</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <form method="post">
                            <div class="card-body">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger">
                                        <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cliente</label>
                                        <select name="cliente_id" class="form-select" id="selectCliente">
                                            <option value="">-- Selecione ou digite nome avulso --</option>
                                            <?php foreach ($lista_clientes as $c): ?>
                                                <option value="<?php echo $c['id']; ?>" <?php echo isset($agendamento['cliente_id']) && $agendamento['cliente_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nome Cliente Avulso</label>
                                        <input type="text" name="cliente_nome_avulso" class="form-control" placeholder="Ex: João da Silva" value="<?php echo isset($agendamento['cliente_nome']) ? sanitize($agendamento['cliente_nome']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Profissional *</label>
                                        <select name="profissional_id" class="form-select" required>
                                            <option value="">-- Selecione --</option>
                                            <?php foreach ($lista_profissionais as $p): ?>
                                                <option value="<?php echo $p['id']; ?>" <?php echo $agendamento['profissional_id'] == $p['id'] ? 'selected' : ''; ?>><?php echo sanitize($p['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Observações</label>
                                        <input type="text" name="observacoes" class="form-control" placeholder="Ex: Indicação, motivo da visita..." value="<?php echo isset($agendamento['observacoes']) ? sanitize($agendamento['observacoes']) : ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label d-block">Serviços *</label>
                                        <div class="row">
                                            <?php foreach ($lista_servicos as $s): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input servico-checkbox" type="checkbox" name="servicos[]" value="<?php echo $s['id']; ?>" id="s_<?php echo $s['id']; ?>" <?php echo in_array($s['id'], $servicos_selecionados) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="s_<?php echo $s['id']; ?>">
                                                            <?php echo sanitize($s['nome']); ?> 
                                                            <span class="text-muted">(<?php echo $s['duracao_minutos']; ?>min)</span>
                                                            <br><small class="text-success">R$ <?php echo number_format($s['valor_centavos'] / 100, 2, ',', '.'); ?></small>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-md-6">
                                        <a href="checkin.php" class="btn btn-default">
                                            <i class="bi bi-arrow-left"></i> Voltar
                                        </a>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="bi bi-save"></i> Salvar Alterações
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h5 class="card-title mb-0">Resumo do Check-in</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Serviços Selecionados</label>
                                <div id="resumo-servicos" class="border rounded p-2 bg-light">
                                    <p class="text-muted mb-0">Nenhum serviço selecionado</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label class="form-label">Total</label>
                                    <h4 class="text-primary" id="total-servicos">R$ 0,00</h4>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Duração</label>
                                    <h4 class="text-info" id="total-duracao">0 min</h4>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Status</span>
                                <span class="fw-bold fs-4 text-warning">Em Atendimento</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Atualizar resumo dos serviços
function atualizarResumo() {
    const checkboxes = document.querySelectorAll('.servico-checkbox:checked');
    const resumoDiv = document.getElementById('resumo-servicos');
    const totalServicos = document.getElementById('total-servicos');
    const totalDuracao = document.getElementById('total-duracao');
    
    let total = 0;
    let duracao = 0;
    let html = '';
    
    if (checkboxes.length === 0) {
        resumoDiv.innerHTML = '<p class="text-muted mb-0">Nenhum serviço selecionado</p>';
        totalServicos.textContent = 'R$ 0,00';
        totalDuracao.textContent = '0 min';
        return;
    }

    checkboxes.forEach(cb => {
        const label = cb.nextElementSibling;
        const text = label.textContent;
        const precoMatch = text.match(/R\$ ([\d.,]+)/);
        const preco = precoMatch ? parseFloat(precoMatch[1].replace('.', '').replace(',', '.')) : 0;
        const duracaoMatch = text.match(/\((\d+)min\)/);
        const duracaoMin = duracaoMatch ? parseInt(duracaoMatch[1]) : 0;
        
        total += preco;
        duracao += duracaoMin;
        
        html += `<div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">${label.textContent.split('R$')[0].trim()}</span>
                    <span class="text-success">R$ ${preco.toFixed(2).replace('.', ',')}</span>
                 </div>`;
    });

    resumoDiv.innerHTML = html;
    totalServicos.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    totalDuracao.textContent = duracao + ' min';
}

// Eventos
document.querySelectorAll('.servico-checkbox').forEach(cb => {
    cb.addEventListener('change', atualizarResumo);
});

// Auto-completar cliente avulso quando selecionar cliente cadastrado
document.getElementById('selectCliente').addEventListener('change', function() {
    if (this.value) {
        document.querySelector('input[name="cliente_nome_avulso"]').value = '';
    }
});

// Foco no campo de cliente avulso quando digitar
document.querySelector('input[name="cliente_nome_avulso"]').addEventListener('input', function() {
    if (this.value.trim()) {
        document.getElementById('selectCliente').value = '';
    }
});

// Atualizar resumo ao carregar a página
atualizarResumo();
</script>

<?php require_once("../../layout/footer.php"); ?>