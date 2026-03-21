<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id = $_GET['id'] ?? null;
$profissional = null;
$horarios = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT p.*, u.nome, u.email, u.telefone FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = :id AND p.estabelecimento_id = :estab_id");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $profissional = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profissional) {
        header("Location: index.php");
        exit;
    }

    // Carregar horários
    $stmt = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE profissional_id = :prof_id ORDER BY dia_semana ASC");
    $stmt->execute(['prof_id' => $id]);
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $horarios[$row['dia_semana']] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome']);
    $email = sanitize($_POST['email']);
    $telefone = sanitize($_POST['telefone']);
    $cargo = sanitize($_POST['cargo']);
    $comissao_percentual = $_POST['comissao_percentual'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        if ($id) {
            // Atualizar Usuario
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = :nome, email = :email, telefone = :telefone WHERE id = :user_id");
            $stmt->execute(['nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'user_id' => $profissional['usuario_id']]);

            // Atualizar Profissional
            $stmt = $pdo->prepare("UPDATE profissionais SET cargo = :cargo, comissao_percentual = :comissao, ativo = :ativo WHERE id = :id");
            $stmt->execute(['cargo' => $cargo, 'comissao' => $comissao_percentual, 'ativo' => $ativo, 'id' => $id]);
        } else {
            // Criar Usuario (perfil profissional)
            $stmt = $pdo->prepare("INSERT INTO usuarios (id, nome, email, telefone, perfil) VALUES (uuid(), :nome, :email, :telefone, 'profissional')");
            $stmt->execute(['nome' => $nome, 'email' => $email, 'telefone' => $telefone]);

            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user_id = $stmt->fetchColumn();

            // Criar Profissional
            $stmt = $pdo->prepare("INSERT INTO profissionais (id, usuario_id, estabelecimento_id, cargo, comissao_percentual, ativo) VALUES (uuid(), :user_id, :estab_id, :cargo, :comissao, :ativo)");
            $stmt->execute(['user_id' => $user_id, 'estab_id' => $estabelecimento_id, 'cargo' => $cargo, 'comissao' => $comissao_percentual, 'ativo' => $ativo]);

            $stmt = $pdo->prepare("SELECT id FROM profissionais WHERE usuario_id = :user_id");
            $stmt->execute(['user_id' => $user_id]);
            $id = $stmt->fetchColumn();
        }

        // Atualizar Horários
        $stmt = $pdo->prepare("DELETE FROM horarios_funcionamento WHERE profissional_id = :prof_id");
        $stmt->execute(['prof_id' => $id]);

        if (isset($_POST['dias'])) {
            foreach ($_POST['dias'] as $dia => $valor) {
                $h_inicio = $_POST['hora_inicio'][$dia];
                $h_fim = $_POST['hora_fim'][$dia];
                $stmt = $pdo->prepare("INSERT INTO horarios_funcionamento (id, estabelecimento_id, profissional_id, dia_semana, hora_inicio, hora_fim) VALUES (uuid(), :estab_id, :prof_id, :dia, :inicio, :fim)");
                $stmt->execute([
                    'estab_id' => $estabelecimento_id,
                    'prof_id' => $id,
                    'dia' => $dia,
                    'inicio' => $h_inicio,
                    'fim' => $h_fim
                ]);
            }
        }

        $pdo->commit();
        header("Location: index.php?success=1");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

$dias_semana = ["Domingo", "Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado"];
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><?php echo $id ? 'Editar' : 'Novo'; ?> Profissional</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <form method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Dados Pessoais</h3></div>
                            <div class="card-body">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label">Nome *</label>
                                    <input type="text" name="nome" class="form-control" value="<?php echo $profissional ? sanitize($profissional['nome']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">E-mail *</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo $profissional ? sanitize($profissional['email']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" class="form-control" value="<?php echo $profissional ? sanitize($profissional['telefone']) : ''; ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cargo</label>
                                        <input type="text" name="cargo" class="form-control" value="<?php echo $profissional ? sanitize($profissional['cargo']) : ''; ?>" placeholder="Ex: Barbeiro, Manicure">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Comissão (%)</label>
                                        <input type="number" name="comissao_percentual" class="form-control" value="<?php echo $profissional ? $profissional['comissao_percentual'] : '0'; ?>">
                                    </div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="ativoSwitch" <?php echo (!$profissional || $profissional['ativo']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ativoSwitch">Profissional Ativo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Horários de Trabalho</h3></div>
                            <div class="card-body p-0">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px"></th>
                                            <th>Dia</th>
                                            <th>Início</th>
                                            <th>Fim</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dias_semana as $idx => $dia):
                                            $checked = isset($horarios[$idx]);
                                            $h_ini = $checked ? $horarios[$idx]['hora_inicio'] : '08:00';
                                            $h_fim = $checked ? $horarios[$idx]['hora_fim'] : '18:00';
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="dias[<?php echo $idx; ?>]" value="1" <?php echo $checked ? 'checked' : ''; ?>>
                                                </td>
                                                <td><?php echo $dia; ?></td>
                                                <td><input type="time" name="hora_inicio[<?php echo $idx; ?>]" class="form-control form-control-sm" value="<?php echo $h_ini; ?>"></td>
                                                <td><input type="time" name="hora_fim[<?php echo $idx; ?>]" class="form-control form-control-sm" value="<?php echo $h_fim; ?>"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 mb-5">
                    <button type="submit" class="btn btn-primary">Salvar Profissional</button>
                    <a href="index.php" class="btn btn-default">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once("../../layout/footer.php"); ?>
