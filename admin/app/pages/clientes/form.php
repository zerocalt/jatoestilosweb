<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id = $_GET['id'] ?? null;
$cliente = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id AND estabelecimento_id = :estab_id");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) {
        header("Location: index.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome']);
    $telefone = sanitize($_POST['telefone']);
    $email = sanitize($_POST['email']);
    $cpf = sanitize($_POST['cpf']);
    $data_nascimento = $_POST['data_nascimento'] ?: null;
    $observacoes = sanitize($_POST['observacoes']);

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE clientes SET nome = :nome, telefone = :telefone, email = :email, cpf = :cpf, data_nascimento = :data_nascimento, observacoes = :observacoes WHERE id = :id AND estabelecimento_id = :estab_id");
            $stmt->execute([
                'nome' => $nome,
                'telefone' => $telefone,
                'email' => $email,
                'cpf' => $cpf,
                'data_nascimento' => $data_nascimento,
                'observacoes' => $observacoes,
                'id' => $id,
                'estab_id' => $estabelecimento_id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO clientes (estabelecimento_id, nome, telefone, email, cpf, data_nascimento, observacoes) VALUES (:estab_id, :nome, :telefone, :email, :cpf, :data_nascimento, :observacoes)");
            $stmt->execute([
                'estab_id' => $estabelecimento_id,
                'nome' => $nome,
                'telefone' => $telefone,
                'email' => $email,
                'cpf' => $cpf,
                'data_nascimento' => $data_nascimento,
                'observacoes' => $observacoes
            ]);
        }
        header("Location: index.php?success=1");
        exit;
    } catch (PDOException $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><?php echo $id ? 'Editar' : 'Novo'; ?> Cliente</h3>
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
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo $cliente ? sanitize($cliente['nome']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" class="form-control" value="<?php echo $cliente ? sanitize($cliente['telefone']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $cliente ? sanitize($cliente['email']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CPF</label>
                                <input type="text" name="cpf" class="form-control" value="<?php echo $cliente ? sanitize($cliente['cpf']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" name="data_nascimento" class="form-control" value="<?php echo $cliente ? $cliente['data_nascimento'] : ''; ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3"><?php echo $cliente ? sanitize($cliente['observacoes']) : ''; ?></textarea>
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
