<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../config/permissions.php");
exigirLogin();

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$hoje = date('Y-m-d');

try {
    // 1. Resumo do dia: Agendamentos por status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as total FROM agendamentos WHERE estabelecimento_id = :estab_id AND DATE(data_inicio) = :hoje GROUP BY status");
    $stmt->execute(['estab_id' => $estabelecimento_id, 'hoje' => $hoje]);
    $stats_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Receita do dia
    $stmt = $pdo->prepare("SELECT SUM(valor_centavos) FROM movimentacoes_caixa mc 
                           JOIN caixas c ON c.id = mc.caixa_id 
                           WHERE c.estabelecimento_id = :estab_id AND DATE(mc.created_at) = :hoje AND mc.tipo = 'entrada'");
    $stmt->execute(['estab_id' => $estabelecimento_id, 'hoje' => $hoje]);
    $receita_hoje = $stmt->fetchColumn() ?: 0;

    // 3. Status do Caixa
    $stmt = $pdo->prepare("SELECT * FROM caixas WHERE estabelecimento_id = :estab_id AND status = 'aberto' LIMIT 1");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $caixa_aberto = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Últimos agendamentos
    $stmt = $pdo->prepare("SELECT * FROM vw_agenda_dia WHERE estabelecimento_id = :estab_id ORDER BY data_inicio DESC LIMIT 5");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $ultimos_agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Dados para o gráfico (últimos 7 dias)
    $grafico_datas = [];
    $grafico_valores = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $grafico_datas[] = date('d/m', strtotime($d));
        
        $st = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE estabelecimento_id = :estab_id AND DATE(data_inicio) = :data AND status != 'cancelado'");
        $st->execute(['estab_id' => $estabelecimento_id, 'data' => $d]);
        $grafico_valores[] = $st->fetchColumn();
    }

} catch (PDOException $e) {
    die("Erro no dashboard: " . $e->getMessage());
}
$active_menu = 'dashboard';
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Dashboard</h3></div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <!-- Small boxes (Stat box) -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box text-bg-primary">
                        <div class="inner">
                            <h3><?php echo ($stats_status['confirmado'] ?? 0) + ($stats_status['pendente'] ?? 0) + ($stats_status['em_atendimento'] ?? 0); ?></h3>
                            <p>Agendamentos Hoje</p>
                        </div>
                        <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M17 3h4a1 1 0 011 1v16a1 1 0 01-1 1H3a1 1 0 01-1-1V4a1 1 0 011-1h4V1h2v2h6V1h2v2zM4 9v10h16V9H4zm2 2h2v2H6v-2zm0 4h2v2H6v-2zm4-4h2v2h-2v-2zm0 4h2v2h-2v-2zm4-4h2v2h-2v-2zm0 4h2v2h-2v-2z"></path>
                        </svg>
                        <a href="../agenda/index.php" class="small-box-footer">Ver Agenda <i class="bi bi-arrow-right-circle"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box text-bg-success">
                        <div class="inner">
                            <h3><?php echo formatMoney($receita_hoje); ?></h3>
                            <p>Receita do Dia</p>
                        </div>
                        <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        <a href="../caixa/index.php" class="small-box-footer">Ver Caixa <i class="bi bi-arrow-right-circle"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box text-bg-warning">
                        <div class="inner">
                            <h3><?php echo $stats_status['concluido'] ?? 0; ?></h3>
                            <p>Atendimentos Concluídos</p>
                        </div>
                        <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        <a href="../relatorios/agendamentos.php" class="small-box-footer">Relatórios <i class="bi bi-arrow-right-circle"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box <?php echo $caixa_aberto ? 'text-bg-primary' : 'text-bg-danger'; ?>">
                        <div class="inner">
                            <h3><?php echo $caixa_aberto ? 'Aberto' : 'Fechado'; ?></h3>
                            <p>Status do Caixa</p>
                        </div>
                        <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 6H4C2.9 6 2 6.9 2 8v8c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM20 16H4V8h16v8z"/><path d="M7 9h2v2H7V9zm0 4h2v2H7v-2zm8-4h2v2h-2V9zm0 4h2v2h-2v-2z"/><path d="M12 10.5c-1.1 0-2 .9-2 2 0 .73.4 1.36 1 1.7V15h2v-.8c.6-.34 1-.97 1-1.7 0-1.1-.9-2-2-2zm0 2.5c-.28 0-.5-.22-.5-.5S11.72 12.5 12 12.5s.5.22.5.5-.22.5-.5.5z"/></svg>
                        <a href="../caixa/index.php" class="small-box-footer">Ir para Caixa <i class="bi bi-arrow-right-circle"></i></a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-7">
                    <div class="card mb-4">
                        <div class="card-header"><h3 class="card-title">Agendamentos na Semana</h3></div>
                        <div class="card-body">
                            <div id="revenue-chart"></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Últimos Agendamentos</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Horário</th>
                                        <th>Cliente</th>
                                        <th>Serviço</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimos_agendamentos as $ua): ?>
                                        <tr>
                                            <td><?php echo date('H:i', strtotime($ua['data_inicio'])); ?></td>
                                            <td><?php echo sanitize($ua['cliente_nome']); ?></td>
                                            <td><?php echo sanitize($ua['servicos_nomes']); ?></td>
                                            <td><?php echo formatStatus($ua['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header bg-primary text-white"><h3 class="card-title">Atalhos Rápidos</h3></div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="../agenda/form.php" class="btn btn-outline-primary text-start"><i class="bi bi-calendar-plus me-2"></i> Novo Agendamento</a>
                                <a href="../clientes/form.php" class="btn btn-outline-primary text-start"><i class="bi bi-person-plus me-2"></i> Cadastrar Cliente</a>
                                <a href="../caixa/movimentacoes.php?tipo=sangria" class="btn btn-outline-danger text-start"><i class="bi bi-dash-circle me-2"></i> Registrar Sangria</a>
                                <a href="../despesas/form.php" class="btn btn-outline-warning text-start"><i class="bi bi-cart-plus me-2"></i> Nova Despesa</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- apexcharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>
<script>
    const options = {
        series: [{
            name: 'Agendamentos',
            data: <?php echo json_encode($grafico_valores); ?>
        }],
        chart: { height: 300, type: 'area', toolbar: { show: false } },
        colors: ['#4169B8'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth' },
        xaxis: {
            categories: <?php echo json_encode($grafico_datas); ?>
        },
        tooltip: { x: { format: 'dd/MM' } }
    };
    const chart = new ApexCharts(document.querySelector("#revenue-chart"), options);
    chart.render();
</script>

<?php require_once("../../layout/footer.php"); ?>
