<?php
require_once("../../top/topo.php");
$active_menu = 'despesas';
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$status_filtro = $_GET['status'] ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';

try {
    $query = "SELECT * FROM despesas WHERE estabelecimento_id = :estab_id";
    $params = ['estab_id' => $estabelecimento_id];

    if ($status_filtro) {
        $query .= " AND status = :status";
        $params['status'] = $status_filtro;
    }
    if ($categoria_filtro) {
        $query .= " AND categoria = :cat";
        $params['cat'] = $categoria_filtro;
    }

    $query .= " ORDER BY vencimento ASC, created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar despesas: " . $e->getMessage());
}

$categorias = ['aluguel', 'energia', 'agua', 'internet', 'telefone', 'materiais', 'salarios', 'outros'];
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Despesas</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Despesa</a>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Filtrar Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <option value="pendente" <?php echo $status_filtro == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="pago" <?php echo $status_filtro == 'pago' ? 'selected' : ''; ?>>Pago</option>
                                <option value="atrasado" <?php echo $status_filtro == 'atrasado' ? 'selected' : ''; ?>>Atrasado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filtrar Categoria</label>
                            <select name="categoria" class="form-select" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $categoria_filtro == $cat ? 'selected' : ''; ?>><?php echo ucfirst($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Despesa</th>
                                <th>Categoria</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($despesas)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum despesa encontrada.</td></tr>
                            <?php else: ?>
                                <?php foreach ($despesas as $d): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold"><?php echo sanitize($d['nome']); ?></span><br>
                                            <small class="text-muted"><?php echo ucfirst($d['tipo']); ?></small>
                                        </td>
                                        <td><?php echo ucfirst($d['categoria']); ?></td>
                                        <td><?php echo formatDate($d['vencimento']); ?></td>
                                        <td class="fw-bold"><?php echo formatMoney($d['valor_centavos']); ?></td>
                                        <td>
                                            <?php 
                                                $badge = $d['status'] == 'pago' ? 'bg-success' : ($d['status'] == 'atrasado' ? 'bg-danger' : 'bg-warning');
                                                echo "<span class='badge $badge'>".ucfirst($d['status'])."</span>";
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="form.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
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
</main>

<?php require_once("../../layout/footer.php"); ?>
