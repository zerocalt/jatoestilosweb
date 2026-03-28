<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../config/permissions.php");
exigirLogin();
$active_menu = 'atendimento';

$estabelecimento_id = $_SESSION['estabelecimento_id'];

// Verificar se existe caixa aberto
$stmt = $pdo->prepare("SELECT id FROM caixas WHERE estabelecimento_id = :estab_id AND status = 'aberto' LIMIT 1");
$stmt->execute(['estab_id' => $estabelecimento_id]);
$caixa_aberto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caixa_aberto) {
    header("Location: ../caixa/index.php");
    exit;
}

try {
    // Carregar dados necessários
    $clientes = $pdo->prepare("SELECT id, nome FROM clientes WHERE estabelecimento_id = :estab_id AND deleted_at IS NULL ORDER BY nome ASC");
    $clientes->execute(['estab_id' => $estabelecimento_id]);
    $lista_clientes = $clientes->fetchAll(PDO::FETCH_ASSOC);

    $profissionais = $pdo->prepare("SELECT p.id, u.nome FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE p.estabelecimento_id = :estab_id AND p.ativo = 1 ORDER BY u.nome ASC");
    $profissionais->execute(['estab_id' => $estabelecimento_id]);
    $lista_profissionais = $profissionais->fetchAll(PDO::FETCH_ASSOC);

    $servicos = $pdo->prepare("SELECT id, nome, duracao_minutos, valor_centavos FROM servicos WHERE estabelecimento_id = :estab_id AND ativo = 1 ORDER BY nome ASC");
    $servicos->execute(['estab_id' => $estabelecimento_id]);
    $lista_servicos = $servicos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?: null;
    $cliente_nome_avulso = sanitize($_POST['cliente_nome_avulso'] ?? '');
    $profissional_id = $_POST['profissional_id'];
    $servicos_selecionados = $_POST['servicos'] ?? [];
    $forma_pagamento = $_POST['forma_pagamento'];
    $desconto_real = $_POST['desconto_real'] ?: 0;
    $desconto_centavos = (int)($desconto_real * 100);
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

            $valor_final_centavos = $valor_total - $desconto_centavos;
            $data_inicio = date('Y-m-d H:i:s');
            $data_fim = date('Y-m-d H:i:s', strtotime($data_inicio . " + $duracao_total minutes"));

            // Inserir agendamento (status concluido imediatamente)
            $stmt = $pdo->prepare("INSERT INTO agendamentos (id, estabelecimento_id, profissional_id, cliente_id, cliente_nome_avulso, data_inicio, data_fim, status, origem, forma_pagamento, valor_total_centavos, desconto_centavos, pago_em, observacoes) VALUES (uuid(), :estab_id, :prof_id, :cli_id, :cli_avulso, :inicio, :fim, 'concluido', 'interno', :forma, :total, :desconto, NOW(), :obs)");
            $stmt->execute([
                'estab_id' => $estabelecimento_id,
                'prof_id' => $profissional_id,
                'cli_id' => $cliente_id,
                'cli_avulso' => $cliente_nome_avulso,
                'inicio' => $data_inicio,
                'fim' => $data_fim,
                'forma' => $forma_pagamento,
                'total' => $valor_final_centavos,
                'desconto' => $desconto_centavos,
                'obs' => $observacoes
            ]);
            
            // Buscar o ID inserido
            $stmt = $pdo->prepare("SELECT id FROM agendamentos WHERE profissional_id = :prof_id AND data_inicio = :inicio ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['prof_id' => $profissional_id, 'inicio' => $data_inicio]);
            $ag_id = $stmt->fetchColumn();

            // Inserir serviços do agendamento
            foreach ($servicos_detalhes as $idx => $s_det) {
                $stmt = $pdo->prepare("INSERT INTO agendamento_servicos (id, agendamento_id, servico_id, valor_cobrado_centavos, duracao_minutos, ordem) VALUES (uuid(), :ag_id, :s_id, :valor, :duracao, :ordem)");
                $stmt->execute([
                    'ag_id' => $ag_id,
                    's_id' => $s_det['id'],
                    'valor' => $s_det['valor_centavos'],
                    'duracao' => $s_det['duracao_minutos'],
                    'ordem' => $idx + 1
                ]);
            }

            // Registrar Movimentação de Caixa
            $stmt = $pdo->prepare("INSERT INTO movimentacoes_caixa (id, caixa_id, agendamento_id, tipo, descricao, valor_centavos, forma_pagamento, operador_id) VALUES (uuid(), :caixa_id, :ag_id, 'entrada', :descr, :valor, :forma, :operador)");
            $stmt->execute([
                'caixa_id' => $caixa_aberto['id'],
                'ag_id' => $ag_id,
                'descr' => "Walk-in: " . ($cliente_nome_avulso ?: 'Cliente avulso'),
                'valor' => $valor_final_centavos,
                'forma' => $forma_pagamento,
                'operador' => $_SESSION['usuario_id']
            ]);

            // Calcular e Registrar Comissão
            $stmt = $pdo->prepare("SELECT comissao_percentual FROM profissionais WHERE id = :id");
            $stmt->execute(['id' => $profissional_id]);
            $percentual = $stmt->fetchColumn() ?: 0;

            if ($percentual > 0) {
                $valor_comissao = (int)($valor_final_centavos * ($percentual / 100));
                $stmt = $pdo->prepare("INSERT INTO comissoes (id, profissional_id, agendamento_id, estabelecimento_id, valor_base_centavos, percentual, valor_comissao_centavos, status) VALUES (uuid(), :prof_id, :ag_id, :estab_id, :base, :perc, :valor, 'pendente')");
                $stmt->execute([
                    'prof_id' => $profissional_id,
                    'ag_id' => $ag_id,
                    'estab_id' => $estabelecimento_id,
                    'base' => $valor_final_centavos,
                    'perc' => $percentual,
                    'valor' => $valor_comissao
                ]);
            }

            $pdo->commit();
            header("Location: index.php?success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao registrar atendimento: " . $e->getMessage();
        }
    }
}
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Atendimento Rápido (Walk-in)</h3>
                </div>
                <div class="col-sm-6">
                    <div class="d-flex justify-content-end">
                        <span class="badge bg-success">Caixa Aberto</span>
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
                                                <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nome Cliente Avulso</label>
                                        <input type="text" name="cliente_nome_avulso" class="form-control" placeholder="Ex: João da Silva">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Profissional *</label>
                                        <select name="profissional_id" class="form-select" required>
                                            <option value="">-- Selecione --</option>
                                            <?php foreach ($lista_profissionais as $p): ?>
                                                <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Forma de Pagamento *</label>
                                        <select name="forma_pagamento" class="form-select" required>
                                            <option value="dinheiro">Dinheiro</option>
                                            <option value="debito">Cartão de Débito</option>
                                            <option value="credito">Cartão de Crédito</option>
                                            <option value="pix">Pix</option>
                                            <option value="voucher">Voucher</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label d-block">Serviços *</label>
                                        <div class="row">
                                            <?php foreach ($lista_servicos as $s): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input servico-checkbox" type="checkbox" name="servicos[]" value="<?php echo $s['id']; ?>" id="s_<?php echo $s['id']; ?>">
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

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Desconto (R$)</label>
                                        <input type="number" step="0.01" name="desconto_real" class="form-control" placeholder="0,00">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Observações</label>
                                        <input type="text" name="observacoes" class="form-control" placeholder="Ex: Indicação, motivo da visita...">
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-md-6">
                                        <a href="../agenda/index.php" class="btn btn-default">
                                            <i class="bi bi-arrow-left"></i> Voltar para Agenda
                                        </a>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="bi bi-check-circle"></i> Atender e Receber
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Resumo do Atendimento</h5>
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
                                    <label class="form-label">Desconto</label>
                                    <h4 class="text-danger" id="total-desconto">R$ 0,00</h4>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">A Receber</span>
                                <span class="fw-bold fs-4 text-success" id="total-final">R$ 0,00</span>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Estatísticas Rápidas</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <span class="text-muted">Clientes Hoje</span><br>
                                    <span class="fw-bold"><?php echo date('d/m'); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">Caixa</span><br>
                                    <span class="fw-bold">Aberto</span>
                                </div>
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
    const totalDesconto = document.getElementById('total-desconto');
    const totalFinal = document.getElementById('total-final');
    
    let total = 0;
    let html = '';
    
    if (checkboxes.length === 0) {
        resumoDiv.innerHTML = '<p class="text-muted mb-0">Nenhum serviço selecionado</p>';
        totalServicos.textContent = 'R$ 0,00';
        totalDesconto.textContent = 'R$ 0,00';
        totalFinal.textContent = 'R$ 0,00';
        return;
    }

    checkboxes.forEach(cb => {
        const label = cb.nextElementSibling;
        const text = label.textContent;
        const precoMatch = text.match(/R\$ ([\d.,]+)/);
        const preco = precoMatch ? parseFloat(precoMatch[1].replace('.', '').replace(',', '.')) : 0;
        total += preco;
        html += `<div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">${label.textContent.split('R$')[0].trim()}</span>
                    <span class="text-success">R$ ${preco.toFixed(2).replace('.', ',')}</span>
                 </div>`;
    });

    resumoDiv.innerHTML = html;
    totalServicos.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    
    const desconto = parseFloat(document.querySelector('input[name="desconto_real"]').value || 0);
    totalDesconto.textContent = 'R$ ' + desconto.toFixed(2).replace('.', ',');
    totalFinal.textContent = 'R$ ' + (total - desconto).toFixed(2).replace('.', ',');
}

// Eventos
document.querySelectorAll('.servico-checkbox').forEach(cb => {
    cb.addEventListener('change', atualizarResumo);
});

document.querySelector('input[name="desconto_real"]').addEventListener('input', atualizarResumo);

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
</script>

<?php require_once("../../layout/footer.php"); ?>