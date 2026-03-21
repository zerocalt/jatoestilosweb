<!doctype html>
<html lang="pt-br">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Jato Estilos | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="app/css/adminlte.css" />
    <style>
      :root { --lte-primary-color: #4169B8; }
      .login-page { background-color: #f4f6f9; }
      .card-primary.card-outline { border-top: 3px solid #4169B8; }
      .btn-primary { background-color: #4169B8; border-color: #4169B8; }
      .btn-primary:hover { background-color: #365899; border-color: #365899; }
    </style>
  </head>
  <body class="login-page">
    <div class="login-box">
      <div class="card card-outline card-primary shadow">
        <div class="card-header text-center">
          <a href="#" class="link-dark text-decoration-none">
            <h1 class="mb-0"><b>Jato</b>Estilos</h1>
          </a>
        </div>
        <div class="card-body login-card-body">
          <p class="login-box-msg">Acesse o painel administrativo</p>

          <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              E-mail ou senha incorretos.
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form action="app/includes/auth_logic.php" method="post">
            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="loginEmail" name="email" type="email" class="form-control" placeholder="Email" required />
                <label for="loginEmail">E-mail</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-envelope"></span>
              </div>
            </div>
            <div class="input-group mb-3">
              <div class="form-floating">
                <input id="loginPassword" name="password" type="password" class="form-control" placeholder="Senha" required />
                <label for="loginPassword">Senha</label>
              </div>
              <div class="input-group-text">
                <span class="bi bi-lock-fill"></span>
              </div>
            </div>
            <div class="row">
              <div class="col-12">
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary">Entrar</button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
  </body>
</html>