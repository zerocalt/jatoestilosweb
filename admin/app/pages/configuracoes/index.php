<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];

try {
    // Carregar dados do estabelecimento
    $stmt = $pdo->prepare("SELECT * FROM estabelecimentos WHERE id = :id");
    $stmt->execute(['id' => $estabelecimento_id]);
    $estab = $stmt->fetch(PDO::FETCH_ASSOC);

    // Carregar políticas
    $stmt = $pdo->prepare("SELECT * FROM politicas_agendamento WHERE estabelecimento_id = :id");
    $stmt->execute(['id' => $estabelecimento_id]);
    $politica = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action == 'dados') {
            $stmt = $pdo->prepare("UPDATE estabelecimentos SET nome = :nome, telefone = :tel, endereco = :end, cidade = :cid, estado = :est WHERE id = :id");
            $stmt->execute([
                'nome' => sanitize($_POST['nome']),
                'tel' => sanitize($_POST['telefone']),
                'end' => sanitize($_POST['endereco']),
                'cid' => sanitize($_POST['cidade']),
                'est' => sanitize($_POST['estado']),
                'id' => $estabelecimento_id
            ]);
            $success = "Dados atualizados com sucesso!";
        } elseif ($action == 'politicas') {
            $stmt = $pdo->prepare("UPDATE politicas_agendamento SET antecedencia_min_minutos = :min, antecedencia_max_dias = :max, cancelamento_limite_horas = :canc, lembrete_horas_antes = :lemb WHERE estabelecimento_id = :id");
            $stmt->execute([
                'min' => $_POST['antecedencia_min'],
                'max' => $_POST['antecedencia_max'],
                'canc' => $_POST['cancelamento'],
                'lemb' => $_POST['lembrete'],
                'id' => $estabelecimento_id
            ]);
            $success = "Políticas atualizadas com sucesso!";
        } elseif ($action == 'senha') {
            $nova = $_POST['nova_senha'];
            $confirma = $_POST['confirma_senha'];
            if ($nova === $confirma && !empty($nova)) {
                $hash = password_hash($nova, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = :hash WHERE id = :id");
                $stmt->execute(['hash' => $hash, 'id' => $_SESSION['admin_id']]);
                $success = "Senha alterada com sucesso!";
            } else {
                $error = "As senhas não coincidem.";
            }
        }
        
        // Refresh data
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
            <h3 class="mb-0">Configurações do Estabelecimento</h3>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Alterações salvas com sucesso!</div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white"><h3 class="card-title">Dados do Negócio</h3></div>
                        <form method="post">
                            <input type="hidden" name="action" value="dados">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Nome Fantasia</label>
                                    <input type="text" name="nome" class="form-control" value="<?php echo sanitize($estab['nome']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" class="form-control" value="<?php echo sanitize($estab['telefone']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Endereço</label>
                                    <input type="text" name="endereco" class="form-control" value="<?php echo sanitize($estab['endereco']); ?>" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="cidade" class="form-control" value="<?php echo sanitize($estab['cidade']); ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Estado (UF)</label>
                                        <input type="text" name="estado" class="form-control" value="<?php echo sanitize($estab['estado']); ?>" maxlength="2" required>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Salvar Dados</button>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header bg-dark text-white"><h3 class="card-title">Segurança</h3></div>
                        <form method="post">
                            <input type="hidden" name="action" value="senha">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Nova Senha</label>
                                    <input type="password" name="nova_senha" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirmar Nova Senha</label>
                                    <input type="password" name="confirma_senha" class="form-control" required>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-dark">Alterar Senha</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white"><h3 class="card-title">Políticas de Agendamento</h3></div>
                        <form method="post">
                            <input type="hidden" name="action" value="politicas">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Antecedência Mínima (minutos)</label>
                                    <input type="number" name="antecedencia_min" class="form-control" value="<?php echo $politica['antecedencia_min_minutos']; ?>" required>
                                    <small class="text-muted">Tempo mínimo antes do horário para permitir agendamento.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Antecedência Máxima (dias)</label>
                                    <input type="number" name="antecedencia_max" class="form-control" value="<?php echo $politica['antecedencia_max_dias']; ?>" required>
                                    <small class="text-muted">Até quantos dias no futuro o cliente pode agendar.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Prazo para Cancelamento (horas)</label>
                                    <input type="number" name="cancelamento" class="form-control" value="<?php echo $politica['cancelamento_limite_horas']; ?>" required>
                                    <small class="text-muted">Limite de horas antes do início para cancelar sem taxas.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Enviar Lembrete (horas antes)</label>
                                    <input type="number" name="lembrete" class="form-control" value="<?php echo $politica['lembrete_horas_antes']; ?>" required>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-info text-white">Salvar Políticas</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once("../../layout/footer.php"); ?>
