<?php
require_once("../../top/topo.php");
$active_menu = 'agenda';
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$data_selecionada = $_GET['data'] ?? date('Y-m-d');
$profissional_id = $_GET['profissional_id'] ?? '';

try {
    // Listar profissionais para filtro
    $stmt = $pdo->prepare("SELECT p.id, u.nome FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE p.estabelecimento_id = :estab_id AND p.ativo = 1 ORDER BY u.nome ASC");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Listar agendamentos do dia
    $query = "SELECT * FROM vw_agenda_dia WHERE estabelecimento_id = :estab_id AND DATE(data_inicio) = :data";
    $params = ['estab_id' => $estabelecimento_id, 'data' => $data_selecionada];

    if (!empty($profissional_id)) {
        $query .= " AND profissional_id = :prof_id";
        $params['prof_id'] = $profissional_id;
    }

    $query .= " ORDER BY data_inicio ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar agenda: " . $e->getMessage());
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Agenda</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="form.php" class="btn btn-primary"><i class="bi bi-calendar-plus"></i> Novo Agendamento</a>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" class="form-control" value="<?php echo $data_selecionada; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Profissional</label>
                            <select name="profissional_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <?php foreach ($profissionais as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo $profissional_id == $p['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($p['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <?php if (empty($agendamentos)): ?>
                    <div class="col-12">
                        <div class="alert alert-light text-center py-5 shadow-sm">
                            <i class="bi bi-calendar-x fs-1 text-muted"></i>
                            <p class="mt-3 mb-0">Nenhum agendamento para este dia.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($agendamentos as $ag): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm mb-4 border-start border-4 <?php 
                                echo $ag['status'] == 'concluido' ? 'border-success' : ($ag['status'] == 'cancelado' ? 'border-danger' : 'border-primary'); 
                            ?>">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold text-primary">
                                            <i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($ag['data_inicio'])); ?>
                                        </span>
                                        <?php echo formatStatus($ag['status']); ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title mb-1"><?php echo sanitize($ag['cliente_nome']); ?></h5>
                                    <p class="card-text text-muted small mb-2"><?php echo sanitize($ag['servicos_nomes']); ?></p>
                                    <hr class="my-2">
                                    <p class="mb-0 small">
                                        <strong>Profissional:</strong> <?php echo sanitize($ag['profissional_nome']); ?><br>
                                        <strong>Duração:</strong> <?php echo $ag['duracao_total_minutos']; ?> min
                                    </p>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <div class="btn-group w-100">
                                        <?php if ($ag['status'] == 'pendente' || $ag['status'] == 'confirmado'): ?>
                                            <button onclick="updateStatus('<?php echo $ag['agendamento_id']; ?>', 'em_atendimento')" class="btn btn-sm btn-outline-primary">Iniciar</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($ag['status'] == 'em_atendimento'): ?>
                                            <a href="concluir.php?id=<?php echo $ag['agendamento_id']; ?>" class="btn btn-sm btn-success">Concluir</a>
                                        <?php endif; ?>

                                        <?php if ($ag['status'] != 'concluido' && $ag['status'] != 'cancelado'): ?>
                                            <button onclick="updateStatus('<?php echo $ag['agendamento_id']; ?>', 'cancelado')" class="btn btn-sm btn-outline-danger">Cancelar</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function updateStatus(id, status) {
    if (confirm('Deseja alterar o status para ' + status + '?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id + '&status=' + status
        }).then(response => response.json())
          .then(data => {
              if (data.success) location.reload();
              else alert('Erro ao atualizar: ' + data.message);
          });
    }
}
</script>

<?php require_once("../../layout/footer.php"); ?>
