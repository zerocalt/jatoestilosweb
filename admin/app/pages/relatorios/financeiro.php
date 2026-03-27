<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../top/topo.php");
$active_menu = 'relatorios';
$active_submenu = 'financeiro';
require_once("../../menu/menu.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

try {
    // 1. Receitas detalhadas por forma de pagamento
    $stmt = $pdo->prepare("SELECT forma_pagamento, SUM(valor_centavos) as total 
                           FROM movimentacoes_caixa mc
                           JOIN caixas c ON c.id = mc.caixa_id
                           WHERE c.estabelecimento_id = :estab_id 
                           AND mc.tipo = 'entrada'
                           AND DATE(mc.created_at) BETWEEN :inicio AND :fim
                           GROUP BY forma_pagamento");
    $stmt->execute(['estab_id' => $estabelecimento_id, 'inicio' => $data_inicio, 'fim' => $data_fim]);
    $receitas_pagamento = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Despesas por categoria
    $stmt = $pdo->prepare("SELECT categoria, SUM(valor_centavos) as total 
                           FROM despesas 
                           WHERE estabelecimento_id = :estab_id 
                           AND status = 'pago'
                           AND (pago_em BETWEEN :inicio AND :fim OR (pago_em IS NULL AND vencimento BETWEEN :inicio2 AND :fim2))
                           GROUP BY categoria");
    $stmt->execute(['estab_id' => $estabelecimento_id, 'inicio' => $data_inicio, 'fim' => $data_fim, 'inicio2' => $data_inicio, 'fim2' => $data_fim]);
    $despesas_categoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totais
    $total_receitas = 0;
    foreach($receitas_pagamento as $r) $total_receitas += $r['total'];
    
    $total_despesas = 0;
    foreach($despesas_categoria as $d) $total_despesas += $d['total'];

} catch (PDOException $e) {
    die("Erro no relatório: " . $e->getMessage());
}
?>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Relatório Financeiro</h3></div>
                <div class="col-sm-6 text-end">
                    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Imprimir / PDF</button>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4 no-print">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?php echo $data_inicio; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?php echo $data_fim; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-3 show-print-only" style="display:none;">
                    <h2 class="text-center">Jato Estilos - Relatório Financeiro</h2>
                    <p class="text-center">Período: <?php echo formatDate($data_inicio); ?> até <?php echo formatDate($data_fim); ?></p>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white"><h3 class="card-title">Receitas</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead><tr><th>Forma</th><th class="text-end">Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($receitas_pagamento as $r): ?>
                                        <tr><td><?php echo ucfirst($r['forma_pagamento']); ?></td><td class="text-end"><?php echo formatMoney($r['total']); ?></td></tr>
                                    <?php endforeach; ?>
                                    <tr class="fw-bold"><td>TOTAL RECEITAS</td><td class="text-end text-success"><?php echo formatMoney($total_receitas); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-danger text-white"><h3 class="card-title">Despesas</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead><tr><th>Categoria</th><th class="text-end">Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($despesas_categoria as $d): ?>
                                        <tr><td><?php echo ucfirst($d['categoria']); ?></td><td class="text-end"><?php echo formatMoney($d['total']); ?></td></tr>
                                    <?php endforeach; ?>
                                    <tr class="fw-bold"><td>TOTAL DESPESAS</td><td class="text-end text-danger"><?php echo formatMoney($total_despesas); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <div class="card bg-light border-primary">
                        <div class="card-body text-center">
                            <h4>RESUMO DO PERÍODO</h4>
                            <div class="row mt-3">
                                <div class="col-md-4"><h5>Receitas: <span class="text-success"><?php echo formatMoney($total_receitas); ?></span></h5></div>
                                <div class="col-md-4"><h5>Despesas: <span class="text-danger"><?php echo formatMoney($total_despesas); ?></span></h5></div>
                                <div class="col-md-4"><h5>Saldo: <span class="<?php echo $total_receitas-$total_despesas >= 0 ? 'text-primary' : 'text-danger'; ?>"><?php echo formatMoney($total_receitas - $total_despesas); ?></span></h5></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
@media print {
    .no-print { display: none !important; }
    .show-print-only { display: block !important; }
    .app-main { margin-left: 0 !important; }
    .app-sidebar, .app-header { display: none !important; }
}
</style>

<?php require_once("../../layout/footer.php"); ?>
