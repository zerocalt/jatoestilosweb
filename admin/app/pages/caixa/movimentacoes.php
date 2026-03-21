<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$tipo = $_GET['tipo'] ?? 'sangria'; // 'sangria' ou 'suprimento'

try {
    $stmt = $pdo->prepare("SELECT id FROM caixas WHERE estabelecimento_id = :estab_id AND status = 'aberto' LIMIT 1");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $caixa_id = $stmt->fetchColumn();

    if (!$caixa_id) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor_centavos = (int)($_POST['valor_real'] * 100);
    $descricao = sanitize($_POST['descricao']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO movimentacoes_caixa (id, caixa_id, tipo, descricao, valor_centavos, operador_id) VALUES (uuid(), :caixa_id, :tipo, :descr, :valor, :operador)");
        $stmt->execute([
            'caixa_id' => $caixa_id,
            'tipo' => $tipo == 'sangria' ? 'sangria' : 'suprimento',
            'descr' => $descricao,
            'valor' => $valor_centavos,
            'operador' => $_SESSION['admin_id']
        ]);
        header("Location: index.php?success=1");
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao registrar: " . $e->getMessage();
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0">Registrar <?php echo ucfirst($tipo); ?></h3>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="card">
                <form method="post">
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Valor (R$)</label>
                                <input type="number" step="0.01" name="valor_real" class="form-control" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Descrição / Motivo</label>
                                <input type="text" name="descricao" class="form-control" placeholder="Ex: Pagamento de material, Troco extra..." required>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <a href="index.php" class="btn btn-default">Cancelar</a>
                        <button type="submit" class="btn <?php echo $tipo == 'sangria' ? 'btn-danger' : 'btn-info'; ?>">Confirmar <?php echo ucfirst($tipo); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once("../../layout/footer.php"); ?>
