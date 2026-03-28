<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

try {
    // 1. Agendamentos por status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as total, SUM(valor_total_centavos) as v_total 
                           FROM agendamentos 
                           WHERE estabelecimento_id = :estab_id 
                           AND DATE(data_inicio) BETWEEN :inicio AND :fim
                           GROUP BY status");
    $stmt->execute(['estab_id' => $estabelecimento_id, 'inicio' => $data_inicio, 'fim' => $data_fim]);
    $stats_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Agendamentos por Profissional
    $stmt = $pdo->prepare("SELECT u.nome, COUNT(a.id) as total, SUM(a.valor_total_centavos) as v_total
                           FROM agendamentos a
                           JOIN profissionais p ON p.id = a.profissional_id
                           JOIN usuarios u ON u.id = p.usuario_id
                           WHERE a.estabelecimento_id = :estab_id 
                           AND DATE(a.data_inicio) BETWEEN :inicio AND :fim
                           AND a.status = 'concluido'
                           GROUP BY u.nome
                           ORDER BY total DESC");
    $stmt->execute(['estab_id' => $estabelecimento_id, 'inicio' => $data_inicio, 'fim' => $data_fim]);
    $stats_prof = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

require_once("../../top/topo.php");
$active_menu = 'relatorios';
$active_submenu = 'agendamentos';
require_once("../../menu/menu.php");
?>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Relatório de Agendamentos</h3></div>
                <div class="col-sm-6 text-end">
                    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Imprimir</button>
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
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white"><h3 class="card-title">Status dos Agendamentos</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead><tr><th>Status</th><th>Quantidade</th><th class="text-end">Valor Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($stats_status as $s): ?>
                                        <tr>
                                            <td><?php echo ucfirst($s['status']); ?></td>
                                            <td><?php echo $s['total']; ?></td>
                                            <td class="text-end"><?php echo formatMoney($s['v_total'] ?: 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white"><h3 class="card-title">Produtividade por Profissional (Concluídos)</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <thead><tr><th>Profissional</th><th>Atendimentos</th><th class="text-end">Total Gerado</th></tr></thead>
                                <tbody>
                                    <?php foreach ($stats_prof as $p): ?>
                                        <tr>
                                            <td><?php echo sanitize($p['nome']); ?></td>
                                            <td><?php echo $p['total']; ?></td>
                                            <td class="text-end"><?php echo formatMoney($p['v_total']); ?></td>
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
