<?php
// ============================================================
//  config/permissions.php
//  Controle de acesso por profissional (não por perfil)
// ============================================================

function temPermissao(PDO $pdo, string $modulo, string $acao = 'pode_ver'): bool {
    $perfil = $_SESSION['perfil'] ?? '';

    // Admin sempre pode tudo
    if ($perfil === 'admin') return true;

    // Sem sessão = sem permissão
    if (empty($_SESSION['profissional_id'])) return false;

    $acoesValidas = ['pode_ver','pode_criar','pode_editar','pode_excluir'];
    if (!in_array($acao, $acoesValidas)) return false;

    static $cache = [];
    $chave = $_SESSION['profissional_id'] . '|' . $modulo . '|' . $acao;

    if (!isset($cache[$chave])) {
        $stmt = $pdo->prepare("SELECT {$acao} FROM permissoes WHERE profissional_id = ? AND modulo = ? LIMIT 1");
        $stmt->execute([$_SESSION['profissional_id'], $modulo]);
        $cache[$chave] = (bool)$stmt->fetchColumn();
    }

    return $cache[$chave];
}

function exigirPermissao(PDO $pdo, string $modulo, string $acao = 'pode_ver'): void {
    if (!temPermissao($pdo, $modulo, $acao)) {
        $_SESSION['erro_acesso'] = "Você não tem permissão para acessar este módulo.";
        header("Location: /jatoestilos/admin/app/pages/dashboard/index.php");
        exit;
    }
}

function exigirLogin(): void {
    if (empty($_SESSION['usuario_id'])) {
        $host = $_SERVER['HTTP_HOST'];

        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            define('BASE_URL', '/jatoestilos');
        } else {
            define('BASE_URL', '');
        }
        header("Location: " . BASE_URL . "/admin/login.php");
        exit;
    }
}

function podeVer(PDO $pdo, string $modulo): bool    { return temPermissao($pdo, $modulo, 'pode_ver'); }
function podeCriar(PDO $pdo, string $modulo): bool   { return temPermissao($pdo, $modulo, 'pode_criar'); }
function podeEditar(PDO $pdo, string $modulo): bool  { return temPermissao($pdo, $modulo, 'pode_editar'); }
function podeExcluir(PDO $pdo, string $modulo): bool { return temPermissao($pdo, $modulo, 'pode_excluir'); }

// Salva permissões de um profissional (chamado no form.php)
function salvarPermissoes(PDO $pdo, string $profissional_id, array $post_perm): void {
    $modulos = ['agenda','clientes','servicos','caixa_hoje','caixa_historico','despesas','profissionais','relatorios','configuracoes'];

    $stmt = $pdo->prepare("
        INSERT INTO permissoes (id, profissional_id, modulo, pode_ver, pode_criar, pode_editar, pode_excluir)
        VALUES (UUID(), ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          pode_ver     = VALUES(pode_ver),
          pode_criar   = VALUES(pode_criar),
          pode_editar  = VALUES(pode_editar),
          pode_excluir = VALUES(pode_excluir)
    ");

    foreach ($modulos as $modulo) {
        $ver     = isset($post_perm[$modulo]['pode_ver'])     ? 1 : 0;
        $criar   = isset($post_perm[$modulo]['pode_criar'])   ? 1 : 0;
        $editar  = isset($post_perm[$modulo]['pode_editar'])  ? 1 : 0;
        $excluir = isset($post_perm[$modulo]['pode_excluir']) ? 1 : 0;
        if (!$ver) $criar = $editar = $excluir = 0;
        $stmt->execute([$profissional_id, $modulo, $ver, $criar, $editar, $excluir]);
    }
}

// Carrega permissões de um profissional indexadas por módulo
function carregarPermissoes(PDO $pdo, string $profissional_id): array {
    $stmt = $pdo->prepare("SELECT * FROM permissoes WHERE profissional_id = ?");
    $stmt->execute([$profissional_id]);
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[$row['modulo']] = $row;
    }
    return $result;
}
