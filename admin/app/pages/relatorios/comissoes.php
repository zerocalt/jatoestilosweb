<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$prof_id = $_GET['profissional_id'] ?? '';
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');

try {
    // Lista de profissionais para o filtro
    $stmt = $pdo->prepare("SELECT p.id, u.nome FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE p.estabelecimento_id = :estab_id ORDER BY u.nome ASC");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resumo de comissões usando a view
    $query = "SELECT * FROM vw_comissoes_periodo WHERE estabelecimento_id = :estab_id AND mes = :mes AND ano = :ano";
    $params = ['estab_id' => $estabelecimento_id, 'mes' => $mes, 'ano' => $ano];

    if ($prof_id) {
        $query .= " AND profissional_id = :prof_id";
        $params['prof_id'] = $prof_id;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $comissoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Extrato de Comissões</h3></div>
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
                            <label class="form-label">Profissional</label>
                            <select name="profissional_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($profissionais as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo $prof_id == $p['id'] ? 'selected' : ''; ?>><?php echo sanitize($p['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Mês</label>
                            <select name="mes" class="form-select">
                                <?php for($i=1;$i<=12;$i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $mes == $i ? 'selected' : ''; ?>><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ano</label>
                            <input type="number" name="ano" class="form-control" value="<?php echo $ano; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Profissional</th>
                                <th>Atendimentos</th>
                                <th class="text-end">Base de Cálculo</th>
                                <th class="text-end">Total Comissão</th>
                                <th class="text-end">Pago</th>
                                <th class="text-end">Pendente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($comissoes)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum registro encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($comissoes as $c): ?>
                                    <tr>
                                        <td><strong><?php echo sanitize($c['profissional_nome']); ?></strong></td>
                                        <td><?php echo $c['total_atendimentos']; ?></td>
                                        <td class="text-end"><?php echo formatMoney($c['total_base_centavos']); ?></td>
                                        <td class="text-end fw-bold text-primary"><?php echo formatMoney($c['total_comissao_centavos']); ?></td>
                                        <td class="text-end text-success"><?php echo formatMoney($c['total_pago_centavos']); ?></td>
                                        <td class="text-end text-danger"><?php echo formatMoney($c['total_pendente_centavos']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
