<?php
require_once("app/config/database.php");
require_once("app/config/functions.php");

// Não é necessário verificar se já existe estabelecimento cadastrado
// As empresas podem se cadastrar livremente

$error = '';
$success = '';

// Manter dados preenchidos em caso de erro
$nome = '';
$email = '';
$telefone = '';
$endereco = '';
$cidade = '';
$estado = '';
$cep = '';
$categoria_id = '';

// Buscar categorias para exibir no formulário (sempre buscar, mesmo fora do POST)
try {
    $stmt = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Caso não haja categorias, usar categorias padrão
    if (empty($categorias)) {
        $categorias = [
            ['id' => '1', 'nome' => 'Barbearia'],
            ['id' => '2', 'nome' => 'Cabeleireiro'],
            ['id' => '3', 'nome' => 'Estética'],
            ['id' => '4', 'nome' => 'Unhas'],
            ['id' => '5', 'nome' => 'Outros']
        ];
    }
} catch (Exception $e) {
    // Se houver erro na consulta, usar categorias padrão
    $categorias = [
        ['id' => '1', 'nome' => 'Barbearia'],
        ['id' => '2', 'nome' => 'Cabeleireiro'],
        ['id' => '3', 'nome' => 'Estética'],
        ['id' => '4', 'nome' => 'Unhas'],
        ['id' => '5', 'nome' => 'Outros']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $telefone = sanitize($_POST['telefone'] ?? '');
    $endereco = sanitize($_POST['endereco'] ?? '');
    $cidade = sanitize($_POST['cidade'] ?? '');
    $estado = sanitize($_POST['estado'] ?? '');
    $cep = sanitize($_POST['cep'] ?? '');
    $categoria_id = sanitize($_POST['categoria_id'] ?? '');

    // Validar campos obrigatórios
    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha) || empty($telefone) || empty($endereco) || empty($cidade) || empty($estado) || empty($cep) || empty($categoria_id)) {
        $error = "Todos os campos são obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "E-mail inválido.";
    } elseif (strlen($senha) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres.";
    } elseif ($senha !== $confirmar_senha) {
        $error = "As senhas não conferem.";
    } else {
        try {
            $pdo->beginTransaction();

            // Verificar se o e-mail já existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $error = "Já existe um usuário com este e-mail.";
                throw new Exception("E-mail já cadastrado");
            }

            // Criar usuário admin primeiro
            $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (id, email, senha_hash, nome, telefone, perfil, created_at, updated_at) VALUES (:user_id, :email, :senha, :nome, :telefone, 'admin', NOW(), NOW())");
            $stmt->execute([
                'user_id' => $user_id,
                'email' => $email,
                'senha' => $senha_hash,
                'nome' => $nome,
                'telefone' => $telefone
            ]);

            // Buscar categorias disponíveis
            $stmt = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome");
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Caso não haja categorias, criar categorias padrão
            if (empty($categorias)) {
                $categorias = [
                    ['id' => '1', 'nome' => 'Barbearia'],
                    ['id' => '2', 'nome' => 'Cabeleireiro'],
                    ['id' => '3', 'nome' => 'Estética'],
                    ['id' => '4', 'nome' => 'Unhas'],
                    ['id' => '5', 'nome' => 'Outros']
                ];
            }

            // Criar estabelecimento com o ID do usuário admin
            $estabelecimento_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
            $stmt = $pdo->prepare("INSERT INTO estabelecimentos (id, nome, telefone, endereco, cidade, estado, cep, admin_id, categoria_id, created_at, updated_at) VALUES (:estab_id, :nome, :telefone, :endereco, :cidade, :estado, :cep, :admin_id, :categoria_id, NOW(), NOW())");
            $stmt->execute([
                'estab_id' => $estabelecimento_id,
                'nome' => $nome,
                'telefone' => $telefone,
                'endereco' => $endereco,
                'cidade' => $cidade,
                'estado' => $estado,
                'cep' => $cep,
                'admin_id' => $user_id,
                'categoria_id' => $categoria_id
            ]);

            $pdo->commit();
            $success = "Empresa cadastrada com sucesso! Você já pode fazer login.";
            
            // Redirecionar para login após sucesso
            header("Location: login.php?success=1");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            if (empty($error)) {
                $error = "Erro ao cadastrar empresa: " . $e->getMessage();
            }
        }
    }
}
?>

<!doctype html>
<html lang="pt-br">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Jato Estilos | Cadastro de Empresa</title>
    <link rel="icon" href="app/assets/img/logoP.png" type="image/png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="app/css/adminlte.css" />
    <style>
      :root { --lte-primary-color: #1C3B51; }
      .login-page { background-color: #f4f6f9; }
      .card-primary.card-outline { border-top: 3px solid #1C3B51; }
      .btn-primary { background-color: #1C3B51; border-color: #1C3B51; }
      .btn-primary:hover { background-color: #1C3B51; border-color: #1C3B51; }
    </style>
  </head>
  <body class="login-page">
    <div class="login-box">
      <div class="card card-outline card-primary shadow">
        <div class="card-header text-center">
          <img src="app/assets/img/logo-150.png" alt="Logo" class="img-circle img-fluid" style="padding: 15px" />
          <a href="#" class="link-dark text-decoration-none">
            <h1 class="mb-0">Jato<b>Estilos</b></h1>
          </a>
        </div>
        <div class="card-body login-card-body">
          <p class="login-box-msg">Cadastre sua empresa e comece a usar o sistema</p>

          <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?php echo $error; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?php echo $success; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form action="cadastro-empresa.php" method="post">
            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="empresaNome" name="nome" type="text" class="form-control" placeholder="Nome da Empresa" value="<?php echo htmlspecialchars($nome); ?>" required />
                <label for="empresaNome">Nome da Empresa</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-building"></span>
              </div>
            </div>

            <div class="input-group mb-3">
              <div class="form-floating">
                <select id="empresaCategoria" name="categoria_id" class="form-select" required>
                  <option value="">Selecione a Categoria</option>
                  <?php foreach ($categorias as $categoria): ?>
                  <option value="<?php echo $categoria['id']; ?>" <?php echo $categoria_id === $categoria['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($categoria['nome']); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <label for="empresaCategoria">Categoria da Empresa</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-tags"></span>
              </div>
            </div>

            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="empresaEmail" name="email" type="email" class="form-control" placeholder="E-mail do Administrador" value="<?php echo htmlspecialchars($email); ?>" required />
                <label for="empresaEmail">E-mail do Administrador</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-envelope"></span>
              </div>
            </div>

            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="empresaSenha" name="senha" type="password" class="form-control" placeholder="Senha" required />
                <label for="empresaSenha">Senha</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-lock-fill"></span>
              </div>
            </div>

            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="empresaConfirmarSenha" name="confirmar_senha" type="password" class="form-control" placeholder="Confirmar Senha" required />
                <label for="empresaConfirmarSenha">Confirmar Senha</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-lock-fill"></span>
              </div>
            </div>

            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="empresaTelefone" name="telefone" type="tel" class="form-control" placeholder="Telefone" value="<?php echo htmlspecialchars($telefone); ?>" required />
                <label for="empresaTelefone">Telefone</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-telephone"></span>
              </div>
            </div>

            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="empresaEndereco" name="endereco" type="text" class="form-control" placeholder="Endereço" value="<?php echo htmlspecialchars($endereco); ?>" required />
                <label for="empresaEndereco">Endereço</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-geo-alt"></span>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <div class="form-floating">
                  <input id="empresaCidade" name="cidade" type="text" class="form-control" placeholder="Cidade" value="<?php echo htmlspecialchars($cidade); ?>" required />
                  <label for="empresaCidade">Cidade</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating">
                  <select id="empresaEstado" name="estado" class="form-select" required>
                    <option value="">Selecione o Estado</option>
                    <option value="AC" <?php echo $estado === 'AC' ? 'selected' : ''; ?>>Acre</option>
                    <option value="AL" <?php echo $estado === 'AL' ? 'selected' : ''; ?>>Alagoas</option>
                    <option value="AP" <?php echo $estado === 'AP' ? 'selected' : ''; ?>>Amapá</option>
                    <option value="AM" <?php echo $estado === 'AM' ? 'selected' : ''; ?>>Amazonas</option>
                    <option value="BA" <?php echo $estado === 'BA' ? 'selected' : ''; ?>>Bahia</option>
                    <option value="CE" <?php echo $estado === 'CE' ? 'selected' : ''; ?>>Ceará</option>
                    <option value="DF" <?php echo $estado === 'DF' ? 'selected' : ''; ?>>Distrito Federal</option>
                    <option value="ES" <?php echo $estado === 'ES' ? 'selected' : ''; ?>>Espírito Santo</option>
                    <option value="GO" <?php echo $estado === 'GO' ? 'selected' : ''; ?>>Goiás</option>
                    <option value="MA" <?php echo $estado === 'MA' ? 'selected' : ''; ?>>Maranhão</option>
                    <option value="MT" <?php echo $estado === 'MT' ? 'selected' : ''; ?>>Mato Grosso</option>
                    <option value="MS" <?php echo $estado === 'MS' ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                    <option value="MG" <?php echo $estado === 'MG' ? 'selected' : ''; ?>>Minas Gerais</option>
                    <option value="PA" <?php echo $estado === 'PA' ? 'selected' : ''; ?>>Pará</option>
                    <option value="PB" <?php echo $estado === 'PB' ? 'selected' : ''; ?>>Paraíba</option>
                    <option value="PR" <?php echo $estado === 'PR' ? 'selected' : ''; ?>>Paraná</option>
                    <option value="PE" <?php echo $estado === 'PE' ? 'selected' : ''; ?>>Pernambuco</option>
                    <option value="PI" <?php echo $estado === 'PI' ? 'selected' : ''; ?>>Piauí</option>
                    <option value="RJ" <?php echo $estado === 'RJ' ? 'selected' : ''; ?>>Rio de Janeiro</option>
                    <option value="RN" <?php echo $estado === 'RN' ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                    <option value="RS" <?php echo $estado === 'RS' ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                    <option value="RO" <?php echo $estado === 'RO' ? 'selected' : ''; ?>>Rondônia</option>
                    <option value="RR" <?php echo $estado === 'RR' ? 'selected' : ''; ?>>Roraima</option>
                    <option value="SC" <?php echo $estado === 'SC' ? 'selected' : ''; ?>>Santa Catarina</option>
                    <option value="SP" <?php echo $estado === 'SP' ? 'selected' : ''; ?>>São Paulo</option>
                    <option value="SE" <?php echo $estado === 'SE' ? 'selected' : ''; ?>>Sergipe</option>
                    <option value="TO" <?php echo $estado === 'TO' ? 'selected' : ''; ?>>Tocantins</option>
                  </select>
                  <label for="empresaEstado">Estado</label>
                </div>
              </div>
            </div>

            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="empresaCep" name="cep" type="text" class="form-control" placeholder="CEP" value="<?php echo htmlspecialchars($cep); ?>" required />
                <label for="empresaCep">CEP</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-pin-map"></span>
              </div>
            </div>

            <div class="row">
              <div class="col-12">
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary">Cadastrar Empresa</button>
                </div>
              </div>
            </div>
          </form>

          <div class="row mt-3">
            <div class="col-12 text-center">
              <a href="login.php" class="text-decoration-none text-muted">Já tem uma conta? Faça login</a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script>
      // Máscara para telefone
      document.getElementById('empresaTelefone').addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'').substring(0,11);
        if (v.length > 6)      v = '('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);
        else if (v.length > 2) v = '('+v.substring(0,2)+') '+v.substring(2);
        else if (v.length > 0) v = '('+v;
        this.value = v;
      });

      // Máscara para CEP
      document.getElementById('empresaCep').addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'').substring(0,8);
        if (v.length > 5) v = v.substring(0,5)+'-'+v.substring(5);
        this.value = v;
      });
    </script>
  </body>
</html>
