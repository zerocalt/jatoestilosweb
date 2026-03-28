<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../config/permissions.php");
exigirLogin();

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id = $_GET['id'] ?? null;
$profissional = null;
$horarios = [];
$permissoes = [];
$error = null;

$modulos_labels = [
    'agenda'          => ['label'=>'Agenda',            'icone'=>'bi-calendar3',             'sensivel'=>false],
    'clientes'        => ['label'=>'Clientes',          'icone'=>'bi-people-fill',           'sensivel'=>false],
    'servicos'        => ['label'=>'Serviços',          'icone'=>'bi-scissors',              'sensivel'=>false],
    'caixa_hoje'      => ['label'=>'Caixa (hoje)',      'icone'=>'bi-safe',                  'sensivel'=>false],
    'caixa_historico' => ['label'=>'Caixa (histórico)', 'icone'=>'bi-safe-fill',             'sensivel'=>true],
    'despesas'        => ['label'=>'Despesas',          'icone'=>'bi-cart-dash',             'sensivel'=>true],
    'profissionais'   => ['label'=>'Profissionais',     'icone'=>'bi-person-badge',          'sensivel'=>false],
    'relatorios'      => ['label'=>'Relatórios',        'icone'=>'bi-file-earmark-bar-graph','sensivel'=>true],
    'configuracoes'   => ['label'=>'Configurações',     'icone'=>'bi-gear',                  'sensivel'=>true],
];

if ($id) {
    $stmt = $pdo->prepare("SELECT p.*, u.nome, u.email, u.telefone, u.perfil AS usuario_perfil, u.ativo AS usuario_ativo FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ? AND p.estabelecimento_id = ?");
    $stmt->execute([$id, $estabelecimento_id]);
    $profissional = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profissional) { header("Location: index.php"); exit; }

    $stmt = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE profissional_id = ? ORDER BY dia_semana");
    $stmt->execute([$id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $horarios[$row['dia_semana']] = $row;

    $permissoes = carregarPermissoes($pdo, $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome']);
    $email = sanitize($_POST['email'] ?? '');
    $telefone = sanitize($_POST['telefone'] ?? '');
    $cargo = sanitize($_POST['cargo'] ?? '');
    $comissao_percentual = (float)($_POST['comissao_percentual'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $tem_acesso = isset($_POST['tem_acesso']) ? 1 : 0;
    $perfil_acesso = in_array($_POST['perfil_acesso'] ?? '', ['profissional','atendente','gerente']) ? $_POST['perfil_acesso'] : 'profissional';
    $senha = $_POST['senha'] ?? '';
    $senha_confirma = $_POST['senha_confirma'] ?? '';

    if (empty($nome)) $error = "Nome obrigatório.";
    elseif ($tem_acesso && empty($email)) $error = "E-mail obrigatório para acesso ao sistema.";
    elseif ($tem_acesso && !$id && empty($senha)) $error = "Defina uma senha para o acesso.";
    elseif ($tem_acesso && !empty($senha) && $senha !== $senha_confirma) $error = "As senhas não conferem.";
    elseif ($tem_acesso && !empty($senha) && strlen($senha) < 6) $error = "Senha mínima de 6 caracteres.";

    if (!$error) {
        try {
            $pdo->beginTransaction();
            if ($id) {
                $pdo->prepare("UPDATE usuarios SET nome=?, email=?, telefone=?, perfil=?, updated_at=NOW() WHERE id=?")
                    ->execute([$nome, $email ?: null, $telefone ?: null, $tem_acesso ? $perfil_acesso : 'profissional', $profissional['usuario_id']]);
                if ($tem_acesso && !empty($senha))
                    $pdo->prepare("UPDATE usuarios SET senha_hash=? WHERE id=?")->execute([password_hash($senha, PASSWORD_BCRYPT, ['cost'=>12]), $profissional['usuario_id']]);
                if (!$tem_acesso)
                    $pdo->prepare("UPDATE usuarios SET senha_hash=NULL, perfil='profissional' WHERE id=?")->execute([$profissional['usuario_id']]);
                $pdo->prepare("UPDATE profissionais SET cargo=?, comissao_percentual=?, ativo=?, updated_at=NOW() WHERE id=? AND estabelecimento_id=?")
                    ->execute([$cargo, $comissao_percentual, $ativo, $id, $estabelecimento_id]);
            } else {
                if (!empty($email)) {
                    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email=? LIMIT 1"); $chk->execute([$email]);
                    if ($chk->fetchColumn()) throw new Exception("E-mail já cadastrado.");
                }
                $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
                $prof_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
                $hash = $tem_acesso && !empty($senha) ? password_hash($senha, PASSWORD_BCRYPT, ['cost'=>12]) : null;
                $pdo->prepare("INSERT INTO usuarios (id, nome, email, telefone, senha_hash, perfil, ativo) VALUES (?,?,?,?,?,?,1)")->execute([$user_id, $nome, $email ?: null, $telefone ?: null, $hash, $tem_acesso ? $perfil_acesso : 'profissional']);
                $pdo->prepare("INSERT INTO profissionais (id, usuario_id, estabelecimento_id, cargo, comissao_percentual, ativo) VALUES (?,?,?,?,?,?)")->execute([$prof_id, $user_id, $estabelecimento_id, $cargo, $comissao_percentual, $ativo]);
                $id = $prof_id;
            }

            $pdo->prepare("DELETE FROM horarios_funcionamento WHERE profissional_id=?")->execute([$id]);
            if (!empty($_POST['dias'])) {
                $stmtH = $pdo->prepare("INSERT INTO horarios_funcionamento (id, estabelecimento_id, profissional_id, dia_semana, hora_inicio, hora_fim, intervalo_minutos) VALUES (UUID(),?,?,?,?,?,?)");
                foreach ($_POST['dias'] as $dia => $v) $stmtH->execute([$estabelecimento_id, $id, $dia, $_POST['hora_inicio'][$dia]??'08:00', $_POST['hora_fim'][$dia]??'18:00', (int)($_POST['intervalo'][$dia]??30)]);
            }

            if ($tem_acesso && !empty($_POST['perm'])) salvarPermissoes($pdo, $id, $_POST['perm']);
            elseif (!$tem_acesso) $pdo->prepare("DELETE FROM permissoes WHERE profissional_id=?")->execute([$id]);

            $pdo->commit();
            header("Location: index.php?success=1"); exit;
        } catch (Exception $e) { $pdo->rollBack(); $error = "Erro: " . $e->getMessage(); }
    }
}

$dias_semana = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$tem_acesso_atual = $profissional && !empty($profissional['email']) && in_array($profissional['usuario_perfil']??'', ['profissional','atendente','gerente']);

require_once("../../top/topo.php");
$active_menu = 'profissionais';
require_once("../../menu/menu.php");
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0"><?php echo $id ? 'Editar' : 'Novo'; ?> Profissional</h3></div>
            </div>
        </div>
    </div>
    <div class="app-content"><div class="container-fluid">

        <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="post">
            <div class="row">

                <!-- ── Dados Pessoais + Horários ── -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="bi bi-person me-2"></i>Dados Pessoais</h3></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($profissional['nome']??''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cargo</label>
                                <input type="text" name="cargo" class="form-control" value="<?php echo htmlspecialchars($profissional['cargo']??''); ?>" placeholder="Ex: Barbeiro">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" id="telefone" class="form-control" value="<?php echo htmlspecialchars($profissional['telefone']??''); ?>" placeholder="(88) 99999-9999">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Comissão (%)</label>
                                <div class="input-group">
                                    <input type="number" name="comissao_percentual" class="form-control" min="0" max="100" step="0.5" value="<?php echo $profissional['comissao_percentual']??0; ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ativo" id="ativoSwitch" <?php echo (!$profissional||$profissional['ativo'])?'checked':''; ?>>
                                <label class="form-check-label" for="ativoSwitch">Profissional Ativo</label>
                            </div>
                        </div>
                    </div>

                    <!-- ── Horários de Trabalho ── -->
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0"><i class="bi bi-clock me-2"></i>Horários de Trabalho</h3>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-xs btn-outline-success py-0 px-2" style="font-size:11px;" onclick="marcarDias(true)">
                                    <i class="bi bi-check-all"></i> Todos
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px;" onclick="marcarDias(false)">
                                    <i class="bi bi-x-lg"></i> Nenhum
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:11px;" onclick="marcarUteis()">
                                    Seg-Sáb
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th style="width:36px"></th><th>Dia</th><th>Início</th><th>Fim</th><th>Interv.</th></tr></thead>
                                <tbody>
                                <?php foreach ($dias_semana as $idx => $dia):
                                    $ck=$horarios[$idx]??null;
                                    $ini=$ck?$ck['hora_inicio']:'08:00';
                                    $fim_h=$ck?$ck['hora_fim']:'18:00';
                                    $inv=$ck?($ck['intervalo_minutos']??30):30;
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="dia-check" name="dias[<?php echo $idx;?>]" value="1" <?php echo $ck?'checked':'';?>></td>
                                    <td><?php echo $dia;?></td>
                                    <td><input type="time" name="hora_inicio[<?php echo $idx;?>]" class="form-control form-control-sm" value="<?php echo $ini;?>"></td>
                                    <td><input type="time" name="hora_fim[<?php echo $idx;?>]" class="form-control form-control-sm" value="<?php echo $fim_h;?>"></td>
                                    <td>
                                        <select name="intervalo[<?php echo $idx;?>]" class="form-select form-select-sm">
                                            <?php foreach([15,30,45,60] as $m): ?>
                                            <option value="<?php echo $m;?>" <?php echo $inv==$m?'selected':'';?>><?php echo $m;?> min</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ── Acesso + Permissões ── -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="bi bi-shield-lock me-2"></i>Acesso e Permissões</h3></div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="tem_acesso" id="temAcessoSwitch"
                                       <?php echo $tem_acesso_atual?'checked':''; ?> onchange="toggleAcesso(this.checked)">
                                <label class="form-check-label fw-bold" for="temAcessoSwitch">Este profissional faz login no sistema</label>
                            </div>

                            <div id="blocoAcesso" style="<?php echo $tem_acesso_atual?'':'display:none;';?>">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Perfil
                                            <i class="bi bi-info-circle text-muted ms-1"
                                               title="Ao trocar o perfil, as permissões são preenchidas automaticamente (editável)"
                                               style="cursor:help;"></i>
                                        </label>
                                        <select name="perfil_acesso" id="perfilAcesso" class="form-select"
                                                onchange="aplicarPermissoesPerfil(this.value)"
                                                required>
                                            <option value="" disabled selected>-- Selecione o perfil --</option>
                                            <option value="profissional" <?php echo ($profissional['usuario_perfil']??'')==='profissional'?'selected':'';?>>Profissional</option>
                                            <option value="atendente"    <?php echo ($profissional['usuario_perfil']??'')==='atendente'   ?'selected':'';?>>Atendente</option>
                                            <option value="gerente"      <?php echo ($profissional['usuario_perfil']??'')==='gerente'      ?'selected':'';?>>Gerente (acesso amplo)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">E-mail <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profissional['email']??'');?>" placeholder="login">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label"><?php echo $id?'Nova senha':'Senha *';?></label>
                                        <input type="password" name="senha" class="form-control" placeholder="<?php echo $id?'Em branco = não alterar':'Mín. 6 caracteres';?>" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-4 mt-2">
                                        <label class="form-label">Confirmar senha</label>
                                        <input type="password" name="senha_confirma" class="form-control" autocomplete="new-password">
                                    </div>
                                </div>

                                <hr>

                                <!-- Badge do perfil selecionado -->
                                <div id="badgePerfil" class="alert py-2 mb-3" style="font-size:13px;"></div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0"><i class="bi bi-key me-1"></i>Permissões</h5>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="marcarTodos(true)"><i class="bi bi-check-all"></i> Marcar tudo</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="marcarTodos(false)"><i class="bi bi-x-lg"></i> Desmarcar tudo</button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width:200px;">Módulo</th>
                                                <?php foreach(['pode_ver'=>'Ver','pode_criar'=>'Criar','pode_editar'=>'Editar','pode_excluir'=>'Excluir'] as $ac=>$lbl): ?>
                                                <th class="text-center" style="width:75px;">
                                                    <div><?php echo $lbl;?></div>
                                                    <input type="checkbox" class="form-check-input col-master" data-col="<?php echo $ac;?>"
                                                           onchange="marcarColuna('<?php echo $ac;?>',this.checked)" style="cursor:pointer;">
                                                </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach($modulos_labels as $modulo=>$info):
                                            $p=$permissoes[$modulo]??[];
                                        ?>
                                        <tr class="<?php echo $info['sensivel']?'table-warning':'';?>">
                                            <td>
                                                <i class="bi <?php echo $info['icone'];?> me-1 text-muted"></i>
                                                <?php echo $info['label'];?>
                                                <?php if($info['sensivel']): ?><i class="bi bi-lock-fill text-danger ms-1" title="Módulo sensível"></i><?php endif;?>
                                            </td>
                                            <?php foreach(['pode_ver','pode_criar','pode_editar','pode_excluir'] as $ac): ?>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-check"
                                                       name="perm[<?php echo $modulo;?>][<?php echo $ac;?>]"
                                                       value="1"
                                                       data-modulo="<?php echo $modulo;?>"
                                                       data-acao="<?php echo $ac;?>"
                                                       <?php echo !empty($p[$ac])?'checked':'';?>
                                                       style="width:18px;height:18px;cursor:pointer;">
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted mt-1 d-block">
                                    <i class="bi bi-lock-fill text-danger"></i> Fundo amarelo = módulo sensível (financeiro).
                                    As permissões são sugestões baseadas no perfil — você pode ajustar livremente.
                                </small>
                            </div>

                            <div id="blocoSemAcesso" style="<?php echo $tem_acesso_atual?'display:none;':'';?>">
                                <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Profissional sem login — aparece apenas na agenda.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 mb-5">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Salvar</button>
                <a href="index.php" class="btn btn-default btn-lg">Cancelar</a>
            </div>
        </form>
    </div></div>
</main>

<script>
// ── Permissões padrão por perfil ──────────────────────────────
// Formato: { modulo: [ver, criar, editar, excluir] }
const PERMISSOES_PADRAO = {
    profissional: {
        agenda:          [1,0,0,0],
        clientes:        [0,0,0,0],
        servicos:        [1,0,0,0],
        caixa_hoje:      [0,0,0,0],
        caixa_historico: [0,0,0,0],
        despesas:        [0,0,0,0],
        profissionais:   [0,0,0,0],
        relatorios:      [1,0,0,0],
        configuracoes:   [0,0,0,0],
    },
    atendente: {
        agenda:          [1,1,1,0],
        clientes:        [1,1,1,0],
        servicos:        [1,0,0,0],
        caixa_hoje:      [1,1,0,0],
        caixa_historico: [0,0,0,0],
        despesas:        [0,0,0,0],
        profissionais:   [0,0,0,0],
        relatorios:      [0,0,0,0],
        configuracoes:   [0,0,0,0],
    },
    gerente: {
        agenda:          [1,1,1,1],
        clientes:        [1,1,1,0],
        servicos:        [1,1,1,0],
        caixa_hoje:      [1,1,1,0],
        caixa_historico: [1,0,0,0],
        despesas:        [1,1,1,0],
        profissionais:   [1,0,0,0],
        relatorios:      [1,0,0,0],
        configuracoes:   [0,0,0,0],
    },
};

const BADGE_PERFIL = {
    profissional: { cls:'alert-secondary', txt:'<i class="bi bi-scissors me-1"></i><strong>Profissional</strong> — acessa apenas a própria agenda e comissões.' },
    atendente:    { cls:'alert-info',      txt:'<i class="bi bi-person-badge me-1"></i><strong>Atendente</strong> — acessa agenda, clientes e caixa do dia. Sem acesso a dados financeiros.' },
    gerente:      { cls:'alert-warning',   txt:'<i class="bi bi-shield-fill me-1"></i><strong>Gerente</strong> — acesso amplo ao sistema. Confirme sua senha ao salvar.' },
};

const ACOES = ['pode_ver','pode_criar','pode_editar','pode_excluir'];

function aplicarPermissoesPerfil(perfil) {
    const badge = document.getElementById('badgePerfil');

    if (!perfil) {
        badge.style.display = 'none';
        marcarTodos(false); // limpa tudo se voltar para em branco
        return;
    }

    badge.style.display = 'block';

    const padrao = PERMISSOES_PADRAO[perfil];
    if (padrao) {
        Object.entries(padrao).forEach(([modulo, vals]) => {
            ACOES.forEach((acao, idx) => {
                const chk = document.querySelector(`.perm-check[data-modulo="${modulo}"][data-acao="${acao}"]`);
                if (chk) chk.checked = vals[idx] === 1;
            });
        });

        ACOES.forEach(acao => {
            const todos  = [...document.querySelectorAll(`.perm-check[data-acao="${acao}"]`)];
            const master = document.querySelector(`.col-master[data-col="${acao}"]`);
            if (master) master.checked = todos.every(c => c.checked);
        });
    }

    const b = BADGE_PERFIL[perfil];
    if (b) {
        badge.className = 'alert py-2 mb-3 ' + b.cls;
        badge.innerHTML = b.txt;
    }
}

// ── Horários: marcar/desmarcar dias ──────────────────────────
function marcarDias(checked) {
    document.querySelectorAll('.dia-check').forEach(c => c.checked = checked);
}

function marcarUteis() {
    // Domingo=0, Sábado=6 — marca Seg(1) a Sáb(6)
    document.querySelectorAll('.dia-check').forEach((c, idx) => {
        c.checked = idx >= 1; // desmarca apenas Domingo
    });
}

// ── Permissões: marcar coluna e tudo ────────────────────────
function marcarColuna(ac, checked) {
    document.querySelectorAll(`.perm-check[data-acao="${ac}"]`).forEach(c => {
        c.checked = checked;
        if (checked && ac !== 'pode_ver') {
            const v = document.querySelector(`.perm-check[data-modulo="${c.dataset.modulo}"][data-acao="pode_ver"]`);
            if (v) v.checked = true;
        }
    });
}

function marcarTodos(c) {
    document.querySelectorAll('.perm-check').forEach(x => x.checked = c);
    document.querySelectorAll('.col-master').forEach(x => x.checked = c);
}

// ── Regras automáticas por linha ────────────────────────────
document.querySelectorAll('.perm-check').forEach(chk => {
    chk.addEventListener('change', function() {
        const m = this.dataset.modulo, a = this.dataset.acao;
        if (a === 'pode_ver' && !this.checked) {
            ['pode_criar','pode_editar','pode_excluir'].forEach(x => {
                const o = document.querySelector(`.perm-check[data-modulo="${m}"][data-acao="${x}"]`);
                if (o) o.checked = false;
            });
        }
        if (a !== 'pode_ver' && this.checked) {
            const v = document.querySelector(`.perm-check[data-modulo="${m}"][data-acao="pode_ver"]`);
            if (v) v.checked = true;
        }
    });
});

// ── Toggle acesso ─────────────────────────────────────────
function toggleAcesso(a) {
    document.getElementById('blocoAcesso').style.display    = a ? 'block' : 'none';
    document.getElementById('blocoSemAcesso').style.display = a ? 'none'  : 'block';
}

// ── Inicializa badge e permissões ao carregar ─────────────
document.addEventListener('DOMContentLoaded', function() {
    const sel    = document.getElementById('perfilAcesso');
    const badge  = document.getElementById('badgePerfil');
    const temAcesso = document.getElementById('temAcessoSwitch')?.checked;

    if (!sel || !temAcesso) return;

    const perfilAtual = sel.value;

    if (perfilAtual) {
        // Editando profissional existente — só mostra o badge,
        // mantém as permissões salvas no banco
        const b = BADGE_PERFIL[perfilAtual];
        if (b) {
            badge.className = 'alert py-2 mb-3 ' + b.cls;
            badge.innerHTML = b.txt;
        }
    } else {
        // Novo profissional — esconde o badge até escolher o perfil
        badge.style.display = 'none';
    }
});

// ── Máscara telefone ─────────────────────────────────────
document.getElementById('telefone')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').substring(0,11);
    if (v.length > 6)      v = '('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);
    else if (v.length > 2) v = '('+v.substring(0,2)+') '+v.substring(2);
    else if (v.length > 0) v = '('+v;
    this.value = v;
});
</script>

<!-- Modal confirmação de senha admin -->
<div class="modal fade" id="modalConfirmarSenha" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-shield-lock-fill me-2"></i>Confirmação de Segurança</h5>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" style="font-size:13px;">
                    Você está salvando um profissional com acesso a dados sensíveis.
                    Confirme sua senha de administrador para continuar.
                </p>
                <label class="form-label">Sua senha de admin</label>
                <div class="input-group">
                    <input type="password" id="senhaConfirmAdmin" class="form-control" placeholder="Digite sua senha" autocomplete="current-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('senhaConfirmAdmin').type = document.getElementById('senhaConfirmAdmin').type==='password'?'text':'password'">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div id="erroSenhaAdmin" class="text-danger mt-2" style="font-size:12px;display:none;">
                    <i class="bi bi-exclamation-circle"></i> Senha incorreta.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" onclick="cancelarConfirmacao()">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarAdmin" onclick="verificarSenhaAdmin()">
                    <span id="btnSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                    Confirmar e Salvar
                </button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" name="senha_admin_confirmada" id="senhaAdminConfirmada" value="">

<script>
const PERFIS_SENSIVEIS = ['gerente','atendente'];

document.querySelector('form').addEventListener('submit', function(e) {
    const temAcesso = document.getElementById('temAcessoSwitch')?.checked;
    if (!temAcesso) return;
    const perfil = document.getElementById('perfilAcesso')?.value;
    if (!PERFIS_SENSIVEIS.includes(perfil)) return;
    if (document.getElementById('senhaAdminConfirmada').value !== '') return;
    e.preventDefault();
    document.getElementById('erroSenhaAdmin').style.display = 'none';
    document.getElementById('senhaConfirmAdmin').value = '';
    new bootstrap.Modal(document.getElementById('modalConfirmarSenha')).show();
    setTimeout(() => document.getElementById('senhaConfirmAdmin').focus(), 400);
});

function cancelarConfirmacao() {
    bootstrap.Modal.getInstance(document.getElementById('modalConfirmarSenha')).hide();
    document.getElementById('senhaAdminConfirmada').value = '';
}

async function verificarSenhaAdmin() {
    const senha = document.getElementById('senhaConfirmAdmin').value;
    if (!senha) { document.getElementById('erroSenhaAdmin').style.display='block'; return; }
    document.getElementById('btnSpinner').classList.remove('d-none');
    document.getElementById('btnConfirmarAdmin').disabled = true;
    try {
        const resp = await fetch('../../includes/verificar_senha.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'senha='+encodeURIComponent(senha) });
        const data = await resp.json();
        if (data.ok) {
            document.getElementById('senhaAdminConfirmada').value = senha;
            bootstrap.Modal.getInstance(document.getElementById('modalConfirmarSenha')).hide();
            document.querySelector('form').submit();
        } else {
            document.getElementById('erroSenhaAdmin').style.display = 'block';
            document.getElementById('senhaConfirmAdmin').value = '';
            document.getElementById('senhaConfirmAdmin').focus();
        }
    } catch(e) { document.getElementById('erroSenhaAdmin').style.display='block'; }
    document.getElementById('btnSpinner').classList.add('d-none');
    document.getElementById('btnConfirmarAdmin').disabled = false;
}

document.getElementById('senhaConfirmAdmin')?.addEventListener('keypress', e => { if(e.key==='Enter') verificarSenhaAdmin(); });
</script>

<?php require_once("../../layout/footer.php"); ?>