<?php
// ============================================================
//  config/permissions.php
//  Funções de controle de acesso (ACL)
//  Inclua em todas as páginas protegidas
// ============================================================

// ── Verifica se o usuário tem permissão ─────────────────────
// $modulo: agenda | clientes | servicos | caixa_hoje |
//          caixa_historico | despesas | profissionais |
//          relatorios | configuracoes
// $acao:   pode_ver | pode_criar | pode_editar | pode_excluir
function temPermissao(PDO $pdo, string $modulo, string $acao = 'pode_ver'): bool {
    $perfil = $_SESSION['perfil'] ?? '';

    // Admin sempre tem acesso total
    if ($perfil === 'admin') return true;

    // Sem sessão = sem permissão
    if (empty($perfil) || empty($_SESSION['estabelecimento_id'])) return false;

    // Ações válidas
    $acoesValidas = ['pode_ver', 'pode_criar', 'pode_editar', 'pode_excluir'];
    if (!in_array($acao, $acoesValidas)) return false;

    static $cache = [];
    $chave = $perfil . '|' . $modulo . '|' . $acao;

    if (!isset($cache[$chave])) {
        $stmt = $pdo->prepare("
            SELECT {$acao} FROM permissoes
            WHERE estabelecimento_id = ? AND perfil = ? AND modulo = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['estabelecimento_id'], $perfil, $modulo]);
        $cache[$chave] = (bool)$stmt->fetchColumn();
    }

    return $cache[$chave];
}

// ── Bloqueia acesso e redireciona ───────────────────────────
function exigirPermissao(PDO $pdo, string $modulo, string $acao = 'pode_ver'): void {
    if (!temPermissao($pdo, $modulo, $acao)) {
        $_SESSION['erro_acesso'] = "Você não tem permissão para acessar este módulo.";
        header("Location: /jatoestilos/admin/app/pages/dashboard/index.php");
        exit;
    }
}

// ── Exige que o usuário esteja logado ───────────────────────
function exigirLogin(): void {
    if (empty($_SESSION['usuario_id'])) {
        header("Location: /jatoestilos/admin/login.php");
        exit;
    }
}

// ── Gera permissões padrão para um novo estabelecimento ─────
function criarPermissoesPadrao(PDO $pdo, string $estabelecimento_id): void {
    $permissoes = [
        'atendente' => [
            'agenda'          => [1, 1, 1, 0],
            'clientes'        => [1, 1, 1, 0],
            'servicos'        => [1, 0, 0, 0],
            'caixa_hoje'      => [1, 1, 0, 0],
            'caixa_historico' => [0, 0, 0, 0],
            'despesas'        => [0, 0, 0, 0],
            'profissionais'   => [0, 0, 0, 0],
            'relatorios'      => [0, 0, 0, 0],
            'configuracoes'   => [0, 0, 0, 0],
        ],
        'profissional' => [
            'agenda'          => [1, 0, 0, 0],
            'clientes'        => [0, 0, 0, 0],
            'servicos'        => [1, 0, 0, 0],
            'caixa_hoje'      => [0, 0, 0, 0],
            'caixa_historico' => [0, 0, 0, 0],
            'despesas'        => [0, 0, 0, 0],
            'profissionais'   => [0, 0, 0, 0],
            'relatorios'      => [1, 0, 0, 0],
            'configuracoes'   => [0, 0, 0, 0],
        ],
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO permissoes
          (id, estabelecimento_id, perfil, modulo, pode_ver, pode_criar, pode_editar, pode_excluir)
        VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($permissoes as $perfil => $modulos) {
        foreach ($modulos as $modulo => $acoes) {
            $stmt->execute([$estabelecimento_id, $perfil, $modulo, ...$acoes]);
        }
    }
}
?>