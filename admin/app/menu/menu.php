      <aside class="app-sidebar shadow" data-bs-theme="dark" style="background-color: #1a1a1a;">
<?php
$active_menu = isset($active_menu) ? $active_menu : '';
$active_submenu = isset($active_submenu) ? $active_submenu : '';
?>
        <div class="sidebar-brand" style="padding-left: -15px; background-color: #1C3B51 !important; display: flex !important; align-items: center !important; justify-content: center !important; width: 100% !important; box-sizing: border-box !important;">
          <a href="../dashboard/index.php" class="brand-link" style="display: flex !important; align-items: center !important; justify-content: center !important; width: 100% !important; text-align: center !important; gap: 8px !important; padding: 0 !important; margin: 0 auto !important;">
            <img src="../../assets/img/logoP.png" alt="Logo Jato Estilos" class="brand-image" style="float: none !important; width: 33px !important; height: auto !important; margin: 0 !important;">
            <span class="brand-text fw-bold" style="color: white !important; margin: 0 !important;">JATO ESTILOS</span>
          </a>
        </div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
              <li class="nav-item <?php echo ($active_menu == 'dashboard') ? 'active' : ''; ?>">
                <a href="../dashboard/index.php" class="nav-link <?php echo ($active_menu == 'dashboard') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Dashboard</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'agenda') ? 'active' : ''; ?>">
                <a href="../agenda/index.php" class="nav-link <?php echo ($active_menu == 'agenda') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-calendar3"></i>
                  <p>Agenda</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'atendimento') ? 'active' : ''; ?>">
                <a href="../atendimento/rapido.php" class="nav-link <?php echo ($active_menu == 'atendimento') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-person-plus"></i>
                  <p>Atendimento Rápido</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'checkin') ? 'active' : ''; ?>">
                <a href="../atendimento/checkin.php" class="nav-link <?php echo ($active_menu == 'checkin') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-person-check"></i>
                  <p>Check-in Walk-in</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'clientes') ? 'active' : ''; ?>">
                <a href="../clientes/index.php" class="nav-link <?php echo ($active_menu == 'clientes') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-people-fill"></i>
                  <p>Clientes</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'servicos') ? 'active' : ''; ?>">
                <a href="../servicos/index.php" class="nav-link <?php echo ($active_menu == 'servicos') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-scissors"></i>
                  <p>Serviços</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'profissionais') ? 'active' : ''; ?>">
                <a href="../profissionais/index.php" class="nav-link <?php echo ($active_menu == 'profissionais') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-person-badge"></i>
                  <p>Profissionais</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'caixa') ? 'active' : ''; ?>">
                <a href="../caixa/index.php" class="nav-link <?php echo ($active_menu == 'caixa') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-safe-fill"></i>
                  <p>Caixa</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'despesas') ? 'active' : ''; ?>">
                <a href="../despesas/index.php" class="nav-link <?php echo ($active_menu == 'despesas') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-cart-dash"></i>
                  <p>Despesas</p>
                </a>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'relatorios') ? 'menu-open' : ''; ?>">
                <a href="#" class="nav-link <?php echo ($active_menu == 'relatorios') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-file-earmark-bar-graph"></i>
                  <p>
                    Relatórios
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="../relatorios/agendamentos.php" class="nav-link <?php echo ($active_menu == 'relatorios' && $active_submenu == 'agendamentos') ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Agendamentos</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="../relatorios/comissoes.php" class="nav-link <?php echo ($active_menu == 'relatorios' && $active_submenu == 'comissoes') ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Comissões</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="../relatorios/rankings.php" class="nav-link <?php echo ($active_menu == 'relatorios' && $active_submenu == 'rankings') ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Rankings</p>
                    </a>
                  </li>
                </ul>
              </li>
              <li class="nav-item <?php echo ($active_menu == 'configuracoes') ? 'active' : ''; ?>">
                <a href="../configuracoes/index.php" class="nav-link <?php echo ($active_menu == 'configuracoes') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-gear"></i>
                  <p>Configurações</p>
                </a>
              </li>
              <li class="nav-header">ACESSO</li>
              <li class="nav-item">
                <a href="../../../logout.php" class="nav-link">
                  <i class="nav-icon bi bi-box-arrow-right"></i>
                  <p>Sair</p>
                </a>
              </li>
            </ul>
          </nav>
        </div>
      </aside>
