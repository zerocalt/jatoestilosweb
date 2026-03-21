<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id = $_GET['id'] ?? null;
$despesa = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM despesas WHERE id = :id AND estabelecimento_id = :estab_id");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $despesa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$despesa) {
        header("Location: index.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome']);
    $tipo = $_POST['tipo'];
    $categoria = $_POST['categoria'];
    $valor_centavos = (int)($_POST['valor_real'] * 100);
    $vencimento = $_POST['vencimento'] ?: null;
    $recorrencia = $_POST['recorrencia'] ?: 'nenhuma';
    $status = $_POST['status'];
    $pago_em = $_POST['pago_em'] ?: null;
    $forma_pagamento = $_POST['forma_pagamento'] ?: null;
    $observacoes = sanitize($_POST['observacoes']);

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE despesas SET nome = :nome, tipo = :tipo, categoria = :categoria, valor_centavos = :valor, vencimento = :vencimento, recorrencia = :recorrencia, status = :status, pago_em = :pago_em, forma_pagamento = :forma, observacoes = :obs WHERE id = :id AND estabelecimento_id = :estab_id");
            $stmt->execute([
                'nome' => $nome, 'tipo' => $tipo, 'categoria' => $categoria, 'valor' => $valor_centavos,
                'vencimento' => $vencimento, 'recorrencia' => $recorrencia, 'status' => $status,
                'pago_em' => $pago_em, 'forma' => $forma_pagamento, 'obs' => $observacoes,
                'id' => $id, 'estab_id' => $estabelecimento_id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO despesas (estabelecimento_id, nome, tipo, categoria, valor_centavos, vencimento, recorrencia, status, pago_em, forma_pagamento, observacoes) VALUES (:estab_id, :nome, :tipo, :categoria, :valor, :vencimento, :recorrencia, :status, :pago_em, :forma, :obs)");
            $stmt->execute([
                'estab_id' => $estabelecimento_id, 'nome' => $nome, 'tipo' => $tipo, 'categoria' => $categoria,
                'valor' => $valor_centavos, 'vencimento' => $vencimento, 'recorrencia' => $recorrencia,
                'status' => $status, 'pago_em' => $pago_em, 'forma' => $forma_pagamento, 'obs' => $observacoes
            ]);
        }
        header("Location: index.php?success=1");
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao salvar despesa: " . $e->getMessage();
    }
}

$categorias = ['aluguel', 'energia', 'agua', 'internet', 'telefone', 'materiais', 'salarios', 'outros'];
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><?php echo $id ? 'Editar' : 'Nova'; ?> Despesa</h3>
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
                                <label class="form-label">Nome da Despesa *</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo $despesa ? sanitize($despesa['nome']) : ''; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tipo *</label>
                                <select name="tipo" class="form-select" required>
                                    <option value="fixa" <?php echo ($despesa && $despesa['tipo'] == 'fixa') ? 'selected' : ''; ?>>Fixa</option>
                                    <option value="variavel" <?php echo ($despesa && $despesa['tipo'] == 'variavel') ? 'selected' : ''; ?>>Variável</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Categoria *</label>
                                <select name="categoria" class="form-select" required>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat; ?>" <?php echo ($despesa && $despesa['categoria'] == $cat) ? 'selected' : ''; ?>><?php echo ucfirst($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Valor (R$) *</label>
                                <input type="number" step="0.01" name="valor_real" class="form-control" value="<?php echo $despesa ? ($despesa['valor_centavos'] / 100) : '0.00'; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Vencimento</label>
                                <input type="date" name="vencimento" class="form-control" value="<?php echo $despesa ? $despesa['vencimento'] : ''; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Recorrência</label>
                                <select name="recorrencia" class="form-select">
                                    <option value="nenhuma" <?php echo ($despesa && $despesa['recorrencia'] == 'nenhuma') ? 'selected' : ''; ?>>Nenhuma</option>
                                    <option value="semanal" <?php echo ($despesa && $despesa['recorrencia'] == 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                    <option value="mensal" <?php echo ($despesa && $despesa['recorrencia'] == 'mensal') ? 'selected' : ''; ?>>Mensal</option>
                                    <option value="anual" <?php echo ($despesa && $despesa['recorrencia'] == 'anual') ? 'selected' : ''; ?>>Anual</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="pendente" <?php echo ($despesa && $despesa['status'] == 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="pago" <?php echo ($despesa && $despesa['status'] == 'pago') ? 'selected' : ''; ?>>Pago</option>
                                    <option value="atrasado" <?php echo ($despesa && $despesa['status'] == 'atrasado') ? 'selected' : ''; ?>>Atrasado</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Data de Pagamento</label>
                                <input type="date" name="pago_em" class="form-control" value="<?php echo $despesa ? $despesa['pago_em'] : ''; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Forma de Pagamento</label>
                                <select name="forma_pagamento" class="form-select">
                                    <option value="">-- Selecione --</option>
                                    <option value="dinheiro" <?php echo ($despesa && $despesa['forma_pagamento'] == 'dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                                    <option value="debito" <?php echo ($despesa && $despesa['forma_pagamento'] == 'debito') ? 'selected' : ''; ?>>Débito</option>
                                    <option value="credito" <?php echo ($despesa && $despesa['forma_pagamento'] == 'credito') ? 'selected' : ''; ?>>Crédito</option>
                                    <option value="pix" <?php echo ($despesa && $despesa['forma_pagamento'] == 'pix') ? 'selected' : ''; ?>>Pix</option>
                                    <option value="transferencia" <?php echo ($despesa && $despesa['forma_pagamento'] == 'transferencia') ? 'selected' : ''; ?>>Transferência</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="2"><?php echo $despesa ? sanitize($despesa['observacoes']) : ''; ?></textarea>
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
