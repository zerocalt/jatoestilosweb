<?php
// ═══════════════════════════════════════════════
// PROCESSAMENTO PHP — ANTES DE QUALQUER HTML
// ═══════════════════════════════════════════════
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../config/permissions.php");

exigirLogin();
exigirPermissao($pdo, 'configuracoes');

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$msg     = null;
$msg_tipo = 'success';

$modulos = [
    'agenda'          => 'Agenda',
    'clientes'        => 'Clientes',
    'servicos'        => 'Serviços',
    'caixa_hoje'      => 'Caixa (hoje)',
    'caixa_historico' => 'Caixa (histórico)',
    'despesas'        => 'Despesas',
    'profissionais'   => 'Profissionais',
    'relatorios'      => 'Relatórios',
    'configuracoes'   => 'Configurações',
];

$perfis = [
    'atendente'    => 'Atendente',
    'profissional' => 'Profissional',
];

$acoes = [
    'pode_ver'    => 'Ver',
    'pode_criar'  => 'Criar',
    'pode_editar' => 'Editar',
    'pode_excluir'=> 'Excluir',
];

// ── Salvar permissões ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($perfis as $perfil => $label) {
            foreach ($modulos as $modulo => $mod_label) {
                $pode_ver     = isset($_POST['perm'][$perfil][$modulo]['pode_ver'])    ? 1 : 0;
                $pode_criar   = isset($_POST['perm'][$perfil][$modulo]['pode_criar'])  ? 1 : 0;
                $pode_editar  = isset($_POST['perm'][$perfil][$modulo]['pode_editar']) ? 1 : 0;
                $pode_excluir = isset($_POST['perm'][$perfil][$modulo]['pode_excluir'])? 1 : 0;

                // Regra: se não pode ver, não pode fazer nada
                if (!$pode_ver) {
                    $pode_criar = $pode_editar = $pode_excluir = 0;
                }

                $pdo->prepare("
                    INSERT INTO permissoes
                      (id, estabelecimento_id, perfil, modulo, pode_ver, pode_criar, pode_editar, pode_excluir)
                    VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                      pode_ver = VALUES(pode_ver),
                      pode_criar = VALUES(pode_criar),
                      pode_editar = VALUES(pode_editar),
                      pode_excluir = VALUES(pode_excluir)
                ")->execute([$estabelecimento_id, $perfil, $modulo, $pode_ver, $pode_criar, $pode_editar, $pode_excluir]);
            }
        }
        $msg = "Permissões salvas com sucesso!";
    } catch (PDOException $e) {
        $msg      = "Erro ao salvar: " . $e->getMessage();
        $msg_tipo = 'danger';
    }
}

// ── Restaurar permissões padrão ────────────────
if (isset($_GET['restaurar'])) {
    $pdo->prepare("DELETE FROM permissoes WHERE estabelecimento_id = ? AND perfil != 'admin'")->execute([$estabelecimento_id]);
    criarPermissoesPadrao($pdo, $estabelecimento_id);
    header("Location: index.php?msg=restaurado");
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'restaurado') {
    $msg = "Permissões restauradas para os valores padrão.";
}

// ── Carrega permissões atuais ──────────────────
$stmt = $pdo->prepare("SELECT * FROM permissoes WHERE estabelecimento_id = ? ORDER BY perfil, modulo");
$stmt->execute([$estabelecimento_id]);
$permissoes_db = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $permissoes_db[$row['perfil']][$row['modulo']] = $row;
}

// Se não existem ainda, cria os padrões
if (empty($permissoes_db)) {
    criarPermissoesPadrao($pdo, $estabelecimento_id);
    header("Location: index.php");
    exit;
}

// HTML
require_once("../../top/topo.php");
$active_menu    = 'configuracoes';
$active_submenu = 'permissoes';
require_once("../../menu/menu.php");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Permissões de Acesso</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="index.php?restaurar=1"
                       class="btn btn-outline-warning"
                       onclick="return confirm('Restaurar todas as permissões para os valores padrão?')">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar Padrão
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <?php if ($msg): ?>
                <div class="alert alert-<?php echo $msg_tipo; ?> alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> <?php echo $msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="alert alert-info py-2" style="font-size:13px;">
                <i class="bi bi-shield-fill-check"></i>
                <strong>Administrador</strong> sempre tem acesso total a tudo — não aparece aqui.
                Configure as permissões para <strong>Atendentes</strong> e <strong>Profissionais</strong>.
            </div>

            <form method="post">
                <?php foreach ($perfis as $perfil => $perfil_label): ?>
                    <div class="card mb-4">
                        <div class="card-header <?php echo $perfil === 'atendente' ? 'bg-info text-white' : 'bg-secondary text-white'; ?>">
                            <h3 class="card-title">
                                <i class="bi bi-<?php echo $perfil === 'atendente' ? 'person-badge' : 'scissors'; ?> me-2"></i>
                                Perfil: <?php echo $perfil_label; ?>
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:200px;">Módulo</th>
                                            <?php foreach ($acoes as $acao => $acao_label): ?>
                                                <th class="text-center" style="width:90px;">
                                                    <?php echo $acao_label; ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modulos as $modulo => $modulo_label):
                                            $p = $permissoes_db[$perfil][$modulo] ?? [];
                                        ?>
                                            <tr>
                                                <td class="fw-bold">
                                                    <?php echo $modulo_label; ?>
                                                    <?php if (in_array($modulo, ['caixa_historico','despesas','configuracoes'])): ?>
                                                        <i class="bi bi-lock-fill text-danger ms-1" title="Sensível"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <?php foreach ($acoes as $acao => $acao_label): ?>
                                                    <td class="text-center">
                                                        <input type="checkbox"
                                                               class="form-check-input perm-check"
                                                               name="perm[<?php echo $perfil; ?>][<?php echo $modulo; ?>][<?php echo $acao; ?>]"
                                                               value="1"
                                                               data-perfil="<?php echo $perfil; ?>"
                                                               data-modulo="<?php echo $modulo; ?>"
                                                               data-acao="<?php echo $acao; ?>"
                                                               <?php echo !empty($p[$acao]) ? 'checked' : ''; ?>
                                                               <?php
                                                               // Admin não edita
                                                               if ($perfil === 'admin') echo 'disabled';
                                                               ?>
                                                               style="width:18px;height:18px;cursor:pointer;">
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="mb-5">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Salvar Permissões
                    </button>
                    <a href="../configuracoes/index.php" class="btn btn-default">Voltar</a>
                </div>
            </form>

        </div>
    </div>
</main>

<script>
// Se desmarcar "Ver", desmarca os outros automaticamente
document.querySelectorAll('.perm-check').forEach(function(chk) {
    chk.addEventListener('change', function() {
        const perfil = this.dataset.perfil;
        const modulo = this.dataset.modulo;
        const acao   = this.dataset.acao;

        if (acao === 'pode_ver' && !this.checked) {
            // Desmarca criar, editar, excluir
            ['pode_criar','pode_editar','pode_excluir'].forEach(function(a) {
                const outro = document.querySelector(
                    `input[data-perfil="${perfil}"][data-modulo="${modulo}"][data-acao="${a}"]`
                );
                if (outro) outro.checked = false;
            });
        }

        if (acao !== 'pode_ver' && this.checked) {
            // Marca "Ver" automaticamente
            const ver = document.querySelector(
                `input[data-perfil="${perfil}"][data-modulo="${modulo}"][data-acao="pode_ver"]`
            );
            if (ver) ver.checked = true;
        }
    });
});
</script>

<?php require_once("../../layout/footer.php"); ?>