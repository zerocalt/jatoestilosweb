<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../config/permissions.php");
exigirLogin();
$active_menu = 'caixa';
require_once("../../top/topo.php");
require_once("../../menu/menu.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];

try {
    // Verificar se existe caixa aberto
    $stmt = $pdo->prepare("SELECT * FROM caixas WHERE estabelecimento_id = :estab_id AND status = 'aberto' LIMIT 1");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $caixa_aberto = $stmt->fetch(PDO::FETCH_ASSOC);

    $movimentacoes = [];
    $total_entradas = 0;
    $total_saidas = 0;

    if ($caixa_aberto) {
        // Buscar movimentações do caixa aberto
        $stmt = $pdo->prepare("SELECT * FROM movimentacoes_caixa WHERE caixa_id = :caixa_id ORDER BY created_at DESC");
        $stmt->execute(['caixa_id' => $caixa_aberto['id']]);
        $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($movimentacoes as $m) {
            if ($m['tipo'] == 'entrada' || $m['tipo'] == 'suprimento') $total_entradas += $m['valor_centavos'];
            else $total_saidas += $m['valor_centavos'];
        }
    }

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

// Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action == 'abrir' && !$caixa_aberto) {
            $valor_inicial = (int)($_POST['valor_inicial'] * 100);
            $stmt = $pdo->prepare("INSERT INTO caixas (id, estabelecimento_id, operador_id, valor_inicial_centavos, status) VALUES (uuid(), :estab_id, :operador, :valor, 'aberto')");
            $stmt->execute([
                'estab_id' => $estabelecimento_id,
                'operador' => $_SESSION['usuario_id'],
                'valor' => $valor_inicial
            ]);
            header("Location: index.php");
            exit;
        }

        if ($action == 'fechar' && $caixa_aberto) {
            $valor_informado = (int)($_POST['valor_informado'] * 100);
            $valor_esperado = $caixa_aberto['valor_inicial_centavos'] + $total_entradas - $total_saidas;
            
            $stmt = $pdo->prepare("UPDATE caixas SET status = 'fechado', fechamento_em = NOW(), valor_esperado_centavos = :esperado, valor_informado_centavos = :informado, observacoes = :obs WHERE id = :id");
            $stmt->execute([
                'esperado' => $valor_esperado,
                'informado' => $valor_informado,
                'obs' => sanitize($_POST['observacoes']),
                'id' => $caixa_aberto['id']
            ]);
            header("Location: index.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Erro ao processar: " . $e->getMessage();
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0">Fluxo de Caixa</h3>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <?php if (!$caixa_aberto): ?>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card card-primary card-outline text-center p-4">
                            <i class="bi bi-lock-fill fs-1 text-primary mb-3"></i>
                            <h4>O caixa está fechado</h4>
                            <p class="text-muted">Abra o caixa para começar a registrar movimentações e atendimentos.</p>
                            <form method="post" class="mt-3">
                                <input type="hidden" name="action" value="abrir">
                                <div class="mb-3 text-start">
                                    <label class="form-label">Valor Inicial em Caixa (Troco) - R$</label>
                                    <input type="text" step="0.01" name="valor_inicial" class="form-control mask-money" value="0,00" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100">Abrir Caixa</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h3 class="card-title">Resumo do Caixa</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Saldo Inicial:</span>
                                    <span class="fw-bold"><?php echo formatMoney($caixa_aberto['valor_inicial_centavos']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>Total Entradas:</span>
                                    <span>+ <?php echo formatMoney($total_entradas); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span>Total Saídas:</span>
                                    <span>- <?php echo formatMoney($total_saidas); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span class="fs-5">Saldo Atual:</span>
                                    <span class="fs-5 fw-bold text-primary"><?php echo formatMoney($caixa_aberto['valor_inicial_centavos'] + $total_entradas - $total_saidas); ?></span>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#modalFechar">
                                    <i class="bi bi-check-circle"></i> Fechar Caixa
                                </button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Ações do Caixa</h3></div>
                            <div class="card-body">
                                <a href="movimentacoes.php?tipo=sangria" class="btn btn-outline-danger w-100 mb-2">Registrar Sangria (Retirada)</a>
                                <a href="movimentacoes.php?tipo=suprimento" class="btn btn-outline-info w-100">Registrar Suprimento (Entrada)</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Últimas Movimentações</h3></div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Hora</th>
                                            <th>Tipo</th>
                                            <th>Descrição</th>
                                            <th>Pagamento</th>
                                            <th class="text-end">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($movimentacoes)): ?>
                                            <tr><td colspan="5" class="text-center py-4">Nenhuma movimentação.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($movimentacoes as $m): ?>
                                                <tr>
                                                    <td><?php echo date('H:i', strtotime($m['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo ($m['tipo'] == 'entrada' || $m['tipo'] == 'suprimento') ? 'bg-success' : 'bg-danger'; ?>">
                                                            <?php echo ucfirst($m['tipo']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo sanitize($m['descricao']); ?></td>
                                                    <td><?php echo ucfirst($m['forma_pagamento'] ?: '-'); ?></td>
                                                    <td class="text-end fw-bold <?php echo ($m['tipo'] == 'entrada' || $m['tipo'] == 'suprimento') ? 'text-success' : 'text-danger'; ?>">
                                                        <?php echo formatMoney($m['valor_centavos']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal Fechar Caixa -->
<div class="modal fade" id="modalFechar" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            <input type="hidden" name="action" value="fechar">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fechar Caixa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Valor Total em Dinheiro no Caixa (Contagem Física)</label>
                        <input type="text" step="0.01" name="valor_informado" class="form-control mask-money" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Fechamento</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once("../../layout/footer.php"); ?>