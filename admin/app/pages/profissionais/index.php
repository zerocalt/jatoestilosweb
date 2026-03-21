<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];

try {
    $stmt = $pdo->prepare("SELECT p.*, u.nome, u.email, u.telefone, u.foto_url as user_foto 
                           FROM profissionais p 
                           JOIN usuarios u ON u.id = p.usuario_id 
                           WHERE p.estabelecimento_id = :estab_id 
                           ORDER BY u.nome ASC");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar profissionais: " . $e->getMessage());
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Profissionais</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Profissional</a>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <?php if (empty($profissionais)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">Nenhum profissional cadastrado.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($profissionais as $prof): ?>
                        <div class="col-md-4">
                            <div class="card card-widget widget-user-2 shadow-sm">
                                <div class="widget-user-header bg-primary">
                                    <div class="widget-user-image">
                                        <img class="img-circle elevation-2" src="<?php echo $prof['foto_url'] ?: '../../assets/img/user2-160x160.jpg'; ?>" alt="User Avatar">
                                    </div>
                                    <h3 class="widget-user-username"><?php echo sanitize($prof['nome']); ?></h3>
                                    <h5 class="widget-user-desc"><?php echo sanitize($prof['cargo']); ?></h5>
                                </div>
                                <div class="card-footer p-0">
                                    <ul class="nav flex-column">
                                        <li class="nav-item">
                                            <span class="nav-link">
                                                Comissão <span class="float-end badge bg-primary"><?php echo $prof['comissao_percentual']; ?>%</span>
                                            </span>
                                        </li>
                                        <li class="nav-item">
                                            <span class="nav-link">
                                                Telefone <span class="float-end text-muted small"><?php echo sanitize($prof['telefone']); ?></span>
                                            </span>
                                        </li>
                                        <li class="nav-item">
                                            <span class="nav-link">
                                                Status <span class="float-end badge <?php echo $prof['ativo'] ? 'bg-success' : 'bg-danger'; ?>"><?php echo $prof['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-footer text-center">
                                    <a href="form.php?id=<?php echo $prof['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                                    <a href="../relatorios/comissoes.php?profissional_id=<?php echo $prof['id']; ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-cash"></i> Comissões</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once("../../layout/footer.php"); ?>
