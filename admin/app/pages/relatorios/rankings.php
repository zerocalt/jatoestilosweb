<?php
require_once("../../top/topo.php");
$active_menu = 'relatorios';
$active_submenu = 'rankings';
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];

try {
    // 1. Ranking de Clientes (mais frequentes e maior valor gasto)
    $stmt = $pdo->prepare("SELECT * FROM vw_ranking_clientes WHERE estabelecimento_id = :estab_id ORDER BY total_visitas DESC LIMIT 10");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $ranking_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ranking de Serviços (mais realizados)
    $stmt = $pdo->prepare("SELECT * FROM vw_ranking_servicos WHERE estabelecimento_id = :estab_id ORDER BY total_realizacoes DESC LIMIT 10");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $ranking_servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Rankings e Performance</h3></div>
                <div class="col-sm-6 text-end">
                    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Imprimir</button>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white"><h3 class="card-title">Top 10 Clientes Frequentes</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead><tr><th>Cliente</th><th class="text-center">Visitas</th><th class="text-end">Total Gasto</th></tr></thead>
                                <tbody>
                                    <?php foreach ($ranking_clientes as $c): ?>
                                        <tr>
                                            <td><?php echo sanitize($c['nome']); ?></td>
                                            <td class="text-center"><?php echo $c['total_visitas']; ?></td>
                                            <td class="text-end"><?php echo formatMoney($c['total_gasto_centavos']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-dark text-white"><h3 class="card-title">Top 10 Serviços Mais Realizados</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead><tr><th>Serviço</th><th class="text-center">Realizações</th><th class="text-end">Receita Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($ranking_servicos as $s): ?>
                                        <tr>
                                            <td><?php echo sanitize($s['servico_nome']); ?></td>
                                            <td class="text-center"><?php echo $s['total_realizacoes']; ?></td>
                                            <td class="text-end"><?php echo formatMoney($s['total_receita_centavos']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
    .app-main { margin-left: 0 !important; }
    .app-sidebar, .app-header { display: none !important; }
}
</style>

<?php require_once("../../layout/footer.php"); ?>
