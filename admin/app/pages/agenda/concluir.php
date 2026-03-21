<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM vw_agenda_dia WHERE agendamento_id = :id AND estabelecimento_id = :estab_id");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agendamento) {
        header("Location: index.php");
        exit;
    }

    // Verificar se existe caixa aberto
    $stmt = $pdo->prepare("SELECT id FROM caixas WHERE estabelecimento_id = :estab_id AND status = 'aberto' LIMIT 1");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $caixa_id = $stmt->fetchColumn();

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$caixa_id) {
        $error = "É necessário abrir o caixa para concluir atendimentos.";
    } else {
        $forma_pagamento = $_POST['forma_pagamento'];
        $desconto_real = $_POST['desconto_real'] ?: 0;
        $desconto_centavos = (int)($desconto_real * 100);
        $valor_total_centavos = $agendamento['valor_total_centavos'] ?: 0;

        // Se valor_total_centavos for null na view (acontece antes de concluir), somamos dos servicos
        if (!$valor_total_centavos) {
            $stmt = $pdo->prepare("SELECT SUM(valor_cobrado_centavos) FROM agendamento_servicos WHERE agendamento_id = :id");
            $stmt->execute(['id' => $id]);
            $valor_total_centavos = $stmt->fetchColumn();
        }

        $valor_final_centavos = $valor_total_centavos - $desconto_centavos;

        try {
            $pdo->beginTransaction();

            // Atualizar Agendamento
            $stmt = $pdo->prepare("UPDATE agendamentos SET status = 'concluido', valor_total_centavos = :total, desconto_centavos = :desconto, forma_pagamento = :forma, pago_em = NOW() WHERE id = :id");
            $stmt->execute([
                'total' => $valor_final_centavos,
                'desconto' => $desconto_centavos,
                'forma' => $forma_pagamento,
                'id' => $id
            ]);

            // Registrar Movimentação de Caixa
            $stmt = $pdo->prepare("INSERT INTO movimentacoes_caixa (id, caixa_id, agendamento_id, tipo, descricao, valor_centavos, forma_pagamento, operador_id) VALUES (uuid(), :caixa_id, :ag_id, 'entrada', :descr, :valor, :forma, :operador)");
            $stmt->execute([
                'caixa_id' => $caixa_id,
                'ag_id' => $id,
                'descr' => "Atendimento: " . $agendamento['cliente_nome'],
                'valor' => $valor_final_centavos,
                'forma' => $forma_pagamento,
                'operador' => $_SESSION['admin_id']
            ]);

            // Calcular e Registrar Comissão
            $stmt = $pdo->prepare("SELECT comissao_percentual FROM profissionais WHERE id = :id");
            $stmt->execute(['id' => $agendamento['profissional_id']]);
            $percentual = $stmt->fetchColumn() ?: 0;

            if ($percentual > 0) {
                $valor_comissao = (int)($valor_final_centavos * ($percentual / 100));
                $stmt = $pdo->prepare("INSERT INTO comissoes (id, profissional_id, agendamento_id, estabelecimento_id, valor_base_centavos, percentual, valor_comissao_centavos, status) VALUES (uuid(), :prof_id, :ag_id, :estab_id, :base, :perc, :valor, 'pendente')");
                $stmt->execute([
                    'prof_id' => $agendamento['profissional_id'],
                    'ag_id' => $id,
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
            $error = "Erro ao concluir: " . $e->getMessage();
        }
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0">Concluir Atendimento</h3>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Resumo do Atendimento</h3></div>
                        <div class="card-body">
                            <p><strong>Cliente:</strong> <?php echo sanitize($agendamento['cliente_nome']); ?></p>
                            <p><strong>Profissional:</strong> <?php echo sanitize($agendamento['profissional_nome']); ?></p>
                            <p><strong>Serviços:</strong> <?php echo sanitize($agendamento['servicos_nomes']); ?></p>
                            <hr>
                            <?php
                                $total_servicos = $agendamento['valor_total_centavos'] ?: 0;
                                if (!$total_servicos) {
                                    $stmt = $pdo->prepare("SELECT SUM(valor_cobrado_centavos) FROM agendamento_servicos WHERE agendamento_id = :id");
                                    $stmt->execute(['id' => $id]);
                                    $total_servicos = $stmt->fetchColumn();
                                }
                            ?>
                            <h4 class="text-primary">Total: <?php echo formatMoney($total_servicos); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <form method="post">
                            <div class="card-body">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>

                                <?php if (!$caixa_id): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Atenção: O caixa está fechado. <br>
                                        <a href="../caixa/index.php" class="btn btn-sm btn-dark mt-2">Ir para o Caixa</a>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label">Forma de Pagamento *</label>
                                    <select name="forma_pagamento" class="form-select" required>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="debito">Cartão de Débito</option>
                                        <option value="credito">Cartão de Crédito</option>
                                        <option value="pix">Pix</option>
                                        <option value="voucher">Voucher</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Desconto (R$)</label>
                                    <input type="number" step="0.01" name="desconto_real" class="form-control" placeholder="0,00">
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <a href="index.php" class="btn btn-default">Voltar</a>
                                <button type="submit" class="btn btn-success" <?php echo !$caixa_id ? 'disabled' : ''; ?>>Finalizar e Receber</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once("../../layout/footer.php"); ?>
