<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id = $_GET['id'] ?? null;
$servico = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = :id AND estabelecimento_id = :estab_id");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $servico = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$servico) {
        header("Location: index.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome']);
    $descricao = sanitize($_POST['descricao']);
    $duracao_minutos = (int)$_POST['duracao_minutos'];
    $valor_centavos = (int)($_POST['valor_real'] * 100);
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE servicos SET nome = :nome, descricao = :descricao, duracao_minutos = :duracao, valor_centavos = :valor, ativo = :ativo WHERE id = :id AND estabelecimento_id = :estab_id");
            $stmt->execute([
                'nome' => $nome,
                'descricao' => $descricao,
                'duracao' => $duracao_minutos,
                'valor' => $valor_centavos,
                'ativo' => $ativo,
                'id' => $id,
                'estab_id' => $estabelecimento_id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO servicos (estabelecimento_id, nome, descricao, duracao_minutos, valor_centavos, ativo) VALUES (:estab_id, :nome, :descricao, :duracao, :valor, :ativo)");
            $stmt->execute([
                'estab_id' => $estabelecimento_id,
                'nome' => $nome,
                'descricao' => $descricao,
                'duracao' => $duracao_minutos,
                'valor' => $valor_centavos,
                'ativo' => $ativo
            ]);
        }
        header("Location: index.php?success=1");
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao salvar serviço: " . $e->getMessage();
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><?php echo $id ? 'Editar' : 'Novo'; ?> Serviço</h3>
                </div>
            </div>
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
                                <label class="form-label">Nome do Serviço *</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo $servico ? sanitize($servico['nome']) : ''; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Duração (minutos) *</label>
                                <input type="number" name="duracao_minutos" class="form-control" value="<?php echo $servico ? $servico['duracao_minutos'] : '30'; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Valor (R$) *</label>
                                <input type="number" step="0.01" name="valor_real" class="form-control" value="<?php echo $servico ? ($servico['valor_centavos'] / 100) : '0.00'; ?>" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3"><?php echo $servico ? sanitize($servico['descricao']) : ''; ?></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="ativoSwitch" <?php echo (!$servico || $servico['ativo']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ativoSwitch">Serviço Ativo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <a href="index.php" class="btn btn-default">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once("../../layout/footer.php"); ?>
