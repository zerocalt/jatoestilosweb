<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];

try {
    $clientes = $pdo->prepare("SELECT id, nome FROM clientes WHERE estabelecimento_id = :estab_id AND deleted_at IS NULL ORDER BY nome ASC");
    $clientes->execute(['estab_id' => $estabelecimento_id]);
    $lista_clientes = $clientes->fetchAll(PDO::FETCH_ASSOC);

    $profissionais = $pdo->prepare("SELECT p.id, u.nome FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE p.estabelecimento_id = :estab_id AND p.ativo = 1 ORDER BY u.nome ASC");
    $profissionais->execute(['estab_id' => $estabelecimento_id]);
    $lista_profissionais = $profissionais->fetchAll(PDO::FETCH_ASSOC);

    $servicos = $pdo->prepare("SELECT id, nome, duracao_minutos, valor_centavos FROM servicos WHERE estabelecimento_id = :estab_id AND ativo = 1 ORDER BY nome ASC");
    $servicos->execute(['estab_id' => $estabelecimento_id]);
    $lista_servicos = $servicos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'] ?: null;
    $cliente_nome_avulso = $_POST['cliente_nome_avulso'] ?: null;
    $profissional_id = $_POST['profissional_id'];
    $servicos_selecionados = $_POST['servicos'] ?? [];
    $data_inicio = $_POST['data_agendamento'] . ' ' . $_POST['hora_agendamento'];
    
    if (empty($servicos_selecionados)) {
        $error = "Selecione pelo menos um serviço.";
    } else {
        try {
            $pdo->beginTransaction();

            // Calcular duração total e fim
            $duracao_total = 0;
            $valor_total = 0;
            $servicos_detalhes = [];
            foreach ($servicos_selecionados as $s_id) {
                $st = $pdo->prepare("SELECT valor_centavos, duracao_minutos FROM servicos WHERE id = :id");
                $st->execute(['id' => $s_id]);
                $s_info = $st->fetch(PDO::FETCH_ASSOC);
                $duracao_total += $s_info['duracao_minutos'];
                $valor_total += $s_info['valor_centavos'];
                $servicos_detalhes[] = array_merge($s_info, ['id' => $s_id]);
            }

            $data_fim = date('Y-m-d H:i:s', strtotime($data_inicio . " + $duracao_total minutes"));

            // Verificar conflito de horário
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamentos 
                                   WHERE profissional_id = :prof_id 
                                   AND status NOT IN ('cancelado', 'falta')
                                   AND (
                                       (:inicio BETWEEN data_inicio AND data_fim) OR 
                                       (:fim BETWEEN data_inicio AND data_fim) OR
                                       (data_inicio BETWEEN :inicio2 AND :fim2)
                                   )");
            $stmt->execute([
                'prof_id' => $profissional_id,
                'inicio' => $data_inicio,
                'fim' => $data_fim,
                'inicio2' => $data_inicio,
                'fim2' => $data_fim
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("O profissional já possui um agendamento neste horário.");
            }

            // Inserir agendamento
            $stmt = $pdo->prepare("INSERT INTO agendamentos (id, estabelecimento_id, profissional_id, cliente_id, cliente_nome_avulso, data_inicio, data_fim, status, origem) VALUES (uuid(), :estab_id, :prof_id, :cli_id, :cli_avulso, :inicio, :fim, 'confirmado', 'interno')");
            $stmt->execute([
                'estab_id' => $estabelecimento_id,
                'prof_id' => $profissional_id,
                'cli_id' => $cliente_id,
                'cli_avulso' => $cliente_nome_avulso,
                'inicio' => $data_inicio,
                'fim' => $data_fim
            ]);
            
            // Buscar o ID inserido
            $stmt = $pdo->prepare("SELECT id FROM agendamentos WHERE profissional_id = :prof_id AND data_inicio = :inicio ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['prof_id' => $profissional_id, 'inicio' => $data_inicio]);
            $ag_id = $stmt->fetchColumn();

            // Inserir serviços do agendamento
            foreach ($servicos_detalhes as $idx => $s_det) {
                $stmt = $pdo->prepare("INSERT INTO agendamento_servicos (id, agendamento_id, servico_id, valor_cobrado_centavos, duracao_minutos, ordem) VALUES (uuid(), :ag_id, :s_id, :valor, :duracao, :ordem)");
                $stmt->execute([
                    'ag_id' => $ag_id,
                    's_id' => $s_det['id'],
                    'valor' => $s_det['valor_centavos'],
                    'duracao' => $s_det['duracao_minutos'],
                    'ordem' => $idx + 1
                ]);
            }

            $pdo->commit();
            header("Location: index.php?success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0">Novo Agendamento</h3>
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
                                <label class="form-label">Cliente Cadastrado</label>
                                <select name="cliente_id" class="form-select">
                                    <option value="">-- Selecione ou digite nome avulso --</option>
                                    <?php foreach ($lista_clientes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome Cliente Avulso (se não cadastrado)</label>
                                <input type="text" name="cliente_nome_avulso" class="form-control" placeholder="Ex: João da Silva">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Profissional *</label>
                                <select name="profissional_id" class="form-select" required>
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($lista_profissionais as $p): ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Data *</label>
                                <input type="date" name="data_agendamento" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Hora *</label>
                                <input type="time" name="hora_agendamento" class="form-control" required>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label d-block">Serviços *</label>
                                <div class="row">
                                    <?php foreach ($lista_servicos as $s): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="servicos[]" value="<?php echo $s['id']; ?>" id="s_<?php echo $s['id']; ?>">
                                                <label class="form-check-label" for="s_<?php echo $s['id']; ?>">
                                                    <?php echo sanitize($s['nome']); ?> (<?php echo $s['duracao_minutos']; ?>min)
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <a href="index.php" class="btn btn-default">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Agendar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once("../../layout/footer.php"); ?>
