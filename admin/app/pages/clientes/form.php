<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id                 = $_GET['id'] ?? null;
$cliente            = null;
$error              = null;
$foto_url           = null;

// ── Busca cliente para edição ──────────────────
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id AND estabelecimento_id = :estab_id");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) {
        header("Location: index.php");
        exit;
    }
}

// ── Função de upload de foto ───────────────────
function uploadFotoCliente(array $arquivo, string $estabelecimento_id, string $cliente_id): string|false {
    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
    $tamanho_max      = 10 * 1024 * 1024; // 10MB

    if ($arquivo['error'] !== UPLOAD_ERR_OK)           return false;
    if ($arquivo['size'] > $tamanho_max)                return false;
    if (!in_array($arquivo['type'], $tipos_permitidos)) return false;
    if (!is_uploaded_file($arquivo['tmp_name']))        return false;

    // Cria pasta do estabelecimento/clientes
    $pasta = $_SERVER['DOCUMENT_ROOT'] . "/jatoestilos/uploads/estabelecimentos/{$estabelecimento_id}/clientes/";
    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
        file_put_contents($pasta . '.htaccess',
            "Options -Indexes\n" .
            "<FilesMatch \"\\.(php|php5|phtml|pl|py|jsp|asp)$\">\n" .
            "    Deny from all\n" .
            "</FilesMatch>\n"
        );
    }

    $destino = $pasta . $cliente_id . '.jpg';

    // Comprime e redimensiona com GD
    $info = getimagesize($arquivo['tmp_name']);
    if (!$info) return false;

    switch ($info['mime']) {
        case 'image/jpeg': $img = imagecreatefromjpeg($arquivo['tmp_name']); break;
        case 'image/png':  $img = imagecreatefrompng($arquivo['tmp_name']);  break;
        case 'image/webp': $img = imagecreatefromwebp($arquivo['tmp_name']); break;
        default: return false;
    }
    if (!$img) return false;

    // Corrige orientação EXIF (fotos do celular)
    if (function_exists('exif_read_data') && $info['mime'] === 'image/jpeg') {
        $exif = @exif_read_data($arquivo['tmp_name']);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $img = imagerotate($img, 180, 0); break;
                case 6: $img = imagerotate($img, -90, 0); break;
                case 8: $img = imagerotate($img,  90, 0); break;
            }
        }
    }

    // Redimensiona mantendo proporção (máx 400x400)
    $orig_w = imagesx($img);
    $orig_h = imagesy($img);
    $max    = 400;

    if ($orig_w > $max || $orig_h > $max) {
        $ratio  = min($max / $orig_w, $max / $orig_h);
        $novo_w = (int)($orig_w * $ratio);
        $novo_h = (int)($orig_h * $ratio);
        $redim  = imagecreatetruecolor($novo_w, $novo_h);
        imagecopyresampled($redim, $img, 0, 0, 0, 0, $novo_w, $novo_h, $orig_w, $orig_h);
        imagedestroy($img);
        $img = $redim;
    }

    imagejpeg($img, $destino, 80);
    imagedestroy($img);

    return "/jatoestilos/uploads/estabelecimentos/{$estabelecimento_id}/clientes/{$cliente_id}.jpg";
}

// ── Processamento do POST ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome            = sanitize($_POST['nome']);
    $telefone        = sanitize($_POST['telefone']);
    $email           = sanitize($_POST['email']);
    $cpf             = sanitize($_POST['cpf']);
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    $observacoes     = sanitize($_POST['observacoes']);

    try {
        if ($id) {
            // ── Edição ─────────────────────────
            $stmt = $pdo->prepare("
                UPDATE clientes
                SET nome = :nome, telefone = :telefone, email = :email,
                    cpf = :cpf, data_nascimento = :data_nascimento,
                    observacoes = :observacoes, updated_at = NOW()
                WHERE id = :id AND estabelecimento_id = :estab_id
            ");
            $stmt->execute([
                'nome'            => $nome,
                'telefone'        => $telefone,
                'email'           => $email,
                'cpf'             => $cpf,
                'data_nascimento' => $data_nascimento,
                'observacoes'     => $observacoes,
                'id'              => $id,
                'estab_id'        => $estabelecimento_id,
            ]);

            // Upload da foto se enviou
            if (!empty($_FILES['foto']['name'])) {
                $url_foto = uploadFotoCliente($_FILES['foto'], $estabelecimento_id, $id);
                if ($url_foto) {
                    $pdo->prepare("
                        UPDATE usuarios u
                        INNER JOIN clientes c ON c.usuario_id = u.id
                        SET u.foto_url = ?, u.updated_at = NOW()
                        WHERE c.id = ? AND c.estabelecimento_id = ?
                    ")->execute([$url_foto, $id, $estabelecimento_id]);
                } else {
                    $error = "Erro ao processar a foto. Verifique o formato e tamanho (máx 10MB).";
                }
            }

        } else {
            // ── Cadastro ───────────────────────
            $novo_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $stmt = $pdo->prepare("
                INSERT INTO clientes (id, estabelecimento_id, nome, telefone, email, cpf, data_nascimento, observacoes)
                VALUES (:id, :estab_id, :nome, :telefone, :email, :cpf, :data_nascimento, :observacoes)
            ");
            $stmt->execute([
                'id'              => $novo_id,
                'estab_id'        => $estabelecimento_id,
                'nome'            => $nome,
                'telefone'        => $telefone,
                'email'           => $email,
                'cpf'             => $cpf,
                'data_nascimento' => $data_nascimento,
                'observacoes'     => $observacoes,
            ]);

            // Upload da foto após criar o cliente
            if (!empty($_FILES['foto']['name'])) {
                $url_foto = uploadFotoCliente($_FILES['foto'], $estabelecimento_id, $novo_id);
                if ($url_foto) {
                    $pdo->prepare("
                        UPDATE usuarios u
                        INNER JOIN clientes c ON c.usuario_id = u.id
                        SET u.foto_url = ?, u.updated_at = NOW()
                        WHERE c.id = ? AND c.estabelecimento_id = ?
                    ")->execute([$url_foto, $novo_id, $estabelecimento_id]);
                }
            }

            $id = $novo_id;
        }

        // ── Redireciona ANTES de qualquer HTML ──
        if (!isset($error)) {
            header("Location: index.php?success=1");
            exit;
        }

    } catch (PDOException $e) {
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

// ── Busca foto atual (somente na edição) ───────
if ($cliente && $id) {
    $stmt_foto = $pdo->prepare("
        SELECT u.foto_url
        FROM usuarios u
        INNER JOIN clientes c ON c.usuario_id = u.id
        WHERE c.id = ? AND c.estabelecimento_id = ?
    ");
    $stmt_foto->execute([$id, $estabelecimento_id]);
    $foto_row = $stmt_foto->fetch(PDO::FETCH_ASSOC);
    $foto_url = $foto_row['foto_url'] ?? null;

    // Verifica se o arquivo existe mesmo sem conta de usuário
    if (!$foto_url) {
        $caminho = $_SERVER['DOCUMENT_ROOT'] . "/jatoestilos/uploads/estabelecimentos/{$estabelecimento_id}/clientes/{$id}.jpg";
        if (file_exists($caminho)) {
            $foto_url = "/jatoestilos/uploads/estabelecimentos/{$estabelecimento_id}/clientes/{$id}.jpg";
        }
    }
}

$active_menu = 'clientes';
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
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
                <form method="post" enctype="multipart/form-data">
                    <div class="card-body">

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <div class="row">

                            <!-- Foto do cliente -->
                            <div class="col-md-12 mb-4">
                                <label class="form-label d-block">Foto do Cliente</label>
                                <div class="d-flex align-items-center gap-3">
                                    <img id="preview-foto"
                                         src="<?php echo $foto_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($cliente['nome'] ?? 'Cliente') . '&background=4169B8&color=fff&size=100'; ?>"
                                         alt="Foto"
                                         style="width:100px; height:100px; object-fit:cover; border-radius:50%; border:2px solid #dee2e6;">
                                    <div>
                                        <input type="file"
                                               name="foto"
                                               id="foto"
                                               class="form-control"
                                               accept="image/jpeg,image/png,image/webp"
                                               style="max-width:300px;"
                                               onchange="previewFoto(this)">
                                        <small class="text-muted d-block mt-1">JPEG, PNG ou WebP — máx. 10MB</small>
                                        <?php if ($foto_url): ?>
                                            <small class="text-success">
                                                <i class="bi bi-check-circle"></i> Foto cadastrada — envie outra para substituir
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Nome -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" name="nome" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['nome'] ?? ''); ?>" required>
                            </div>

                            <!-- Telefone -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" id="telefone" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['telefone'] ?? ''); ?>"
                                       placeholder="(88) 99999-9999">
                            </div>

                            <!-- E-mail -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>">
                            </div>

                            <!-- CPF -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CPF</label>
                                <input type="text" name="cpf" id="cpf" class="form-control"
                                       value="<?php echo htmlspecialchars($cliente['cpf'] ?? ''); ?>"
                                       placeholder="000.000.000-00">
                            </div>

                            <!-- Data de Nascimento -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" name="data_nascimento" class="form-control"
                                       value="<?php echo $cliente['data_nascimento'] ?? ''; ?>">
                            </div>

                            <!-- Observações -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3"><?php echo htmlspecialchars($cliente['observacoes'] ?? ''); ?></textarea>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Salvar
                        </button>
                        <a href="index.php" class="btn btn-default">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function previewFoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('preview-foto').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

// Máscara telefone
document.getElementById('telefone')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').substring(0,11);
    if (v.length > 6)      v = '('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);
    else if (v.length > 2) v = '('+v.substring(0,2)+') '+v.substring(2);
    else if (v.length > 0) v = '('+v;
    this.value = v;
});

// Máscara CPF
document.getElementById('cpf')?.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').substring(0,11);
    if (v.length > 9)      v = v.substring(0,3)+'.'+v.substring(3,6)+'.'+v.substring(6,9)+'-'+v.substring(9);
    else if (v.length > 6) v = v.substring(0,3)+'.'+v.substring(3,6)+'.'+v.substring(6);
    else if (v.length > 3) v = v.substring(0,3)+'.'+v.substring(3);
    this.value = v;
});
</script>

<?php require_once("../../layout/footer.php"); ?>