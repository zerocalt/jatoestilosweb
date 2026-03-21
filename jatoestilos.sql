-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 22/03/2026 às 04:57
-- Versão do servidor: 10.11.10-MariaDB-log
-- Versão do PHP: 8.3.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `jatoestilos`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `agendamentos`
--

CREATE TABLE `agendamentos` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `profissional_id` char(36) NOT NULL COMMENT 'FK -> profissionais.id',
  `cliente_id` char(36) DEFAULT NULL COMMENT 'FK -> clientes.id — NULL = walk-in sem cadastro',
  `cliente_nome_avulso` varchar(100) DEFAULT NULL COMMENT 'Nome para atendimento sem cliente cadastrado',
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL COMMENT 'Calculado: data_inicio + soma das durações',
  `status` varchar(20) NOT NULL DEFAULT 'pendente' CHECK (`status` in ('pendente','confirmado','em_atendimento','concluido','cancelado','falta')),
  `origem` varchar(20) NOT NULL DEFAULT 'interno' CHECK (`origem` in ('app_cliente','interno','walk_in')),
  `observacoes` text DEFAULT NULL,
  `valor_total_centavos` int(10) UNSIGNED DEFAULT NULL COMMENT 'Preenchido na conclusão',
  `desconto_centavos` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forma_pagamento` varchar(20) DEFAULT NULL CHECK (`forma_pagamento` in ('dinheiro','debito','credito','pix','voucher')),
  `parcelas` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Número de parcelas (crédito)',
  `pago_em` datetime DEFAULT NULL,
  `cancelado_em` datetime DEFAULT NULL,
  `motivo_cancelamento` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro central de todos os agendamentos';

-- --------------------------------------------------------

--
-- Estrutura para tabela `agendamento_servicos`
--

CREATE TABLE `agendamento_servicos` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `agendamento_id` char(36) NOT NULL COMMENT 'FK -> agendamentos.id',
  `servico_id` char(36) NOT NULL COMMENT 'FK -> servicos.id',
  `valor_cobrado_centavos` int(10) UNSIGNED NOT NULL COMMENT 'Valor snapshot no momento do agendamento',
  `duracao_minutos` int(10) UNSIGNED NOT NULL COMMENT 'Duração snapshot no momento do agendamento',
  `ordem` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Ordem de execução'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Serviços incluídos em cada agendamento (N:N com snapshot de valores)';

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes`
--

CREATE TABLE `avaliacoes` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `agendamento_id` char(36) NOT NULL COMMENT 'FK -> agendamentos.id',
  `cliente_id` char(36) NOT NULL COMMENT 'FK -> clientes.id',
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `profissional_id` char(36) NOT NULL COMMENT 'FK -> profissionais.id',
  `nota` tinyint(4) NOT NULL CHECK (`nota` between 1 and 5),
  `comentario` text DEFAULT NULL,
  `resposta_admin` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Avaliações dos clientes sobre atendimentos';

-- --------------------------------------------------------

--
-- Estrutura para tabela `bloqueios_agenda`
--

CREATE TABLE `bloqueios_agenda` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `profissional_id` char(36) NOT NULL COMMENT 'FK -> profissionais.id',
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Períodos bloqueados na agenda de um profissional';

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixas`
--

CREATE TABLE `caixas` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `operador_id` char(36) NOT NULL COMMENT 'FK -> usuarios.id — quem abriu',
  `status` varchar(10) NOT NULL DEFAULT 'aberto' CHECK (`status` in ('aberto','fechado')),
  `abertura_em` datetime NOT NULL DEFAULT current_timestamp(),
  `fechamento_em` datetime DEFAULT NULL,
  `valor_inicial_centavos` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Troco inicial em centavos',
  `valor_esperado_centavos` int(10) UNSIGNED DEFAULT NULL COMMENT 'Calculado pelo sistema no fechamento',
  `valor_informado_centavos` int(10) UNSIGNED DEFAULT NULL COMMENT 'Contagem física informada no fechamento',
  `observacoes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Controle de abertura e fechamento de caixa';

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `nome` varchar(100) NOT NULL,
  `icone_url` varchar(500) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de estabelecimento disponíveis na plataforma';

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`, `icone_url`, `ativo`, `created_at`, `updated_at`) VALUES
('40183810-235d-11f1-b18f-020017083972', 'Barbearia', NULL, 1, '2026-03-19 03:31:23', '2026-03-19 03:31:23'),
('40187162-235d-11f1-b18f-020017083972', 'Salão de Beleza', NULL, 1, '2026-03-19 03:31:23', '2026-03-19 03:31:23'),
('4018a0b5-235d-11f1-b18f-020017083972', 'Manicure e Pedicure', NULL, 1, '2026-03-19 03:31:23', '2026-03-19 03:31:23'),
('4018d0e7-235d-11f1-b18f-020017083972', 'Estúdio de Tatuagem', NULL, 1, '2026-03-19 03:31:23', '2026-03-19 03:31:23'),
('40190277-235d-11f1-b18f-020017083972', 'Clínica de Estética', NULL, 1, '2026-03-19 03:31:23', '2026-03-19 03:31:23'),
('40190331-235d-11f1-b18f-020017083972', 'Esmalteria', NULL, 1, '2026-03-19 03:31:23', '2026-03-19 03:31:23'),
('401903a1-235d-11f1-b18f-020017083972', 'SPA e Bem-estar', NULL, 1, '2026-03-19 03:31:23', '2026-03-19 03:31:23'),
('b4a49217-24dd-11f1-b18f-020017083972', 'Cabelos', NULL, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26'),
('b4a49458-24dd-11f1-b18f-020017083972', 'Unhas', NULL, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26'),
('b4a4be3e-24dd-11f1-b18f-020017083972', 'Depilação', NULL, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26'),
('b4a4bed7-24dd-11f1-b18f-020017083972', 'Maquiagem', NULL, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26'),
('b4a4d505-24dd-11f1-b18f-020017083972', 'Sobrancelhas', NULL, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26'),
('b4a4d582-24dd-11f1-b18f-020017083972', 'Massagem e Estética', NULL, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26'),
('b4a4d5ca-24dd-11f1-b18f-020017083972', 'Podologia', NULL, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26');

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `usuario_id` char(36) DEFAULT NULL COMMENT 'FK -> usuarios.id — NULL se não tem conta',
  `nome` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clientes de um estabelecimento (com ou sem conta no app)';

-- --------------------------------------------------------

--
-- Estrutura para tabela `comissoes`
--

CREATE TABLE `comissoes` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `profissional_id` char(36) NOT NULL COMMENT 'FK -> profissionais.id',
  `agendamento_id` char(36) NOT NULL COMMENT 'FK -> agendamentos.id',
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `valor_base_centavos` int(10) UNSIGNED NOT NULL COMMENT 'Valor do atendimento (base de cálculo)',
  `percentual` decimal(5,2) NOT NULL COMMENT 'Percentual aplicado',
  `valor_comissao_centavos` int(10) UNSIGNED NOT NULL COMMENT 'Valor calculado = base * percentual / 100',
  `status` varchar(10) NOT NULL DEFAULT 'pendente' CHECK (`status` in ('pendente','pago')),
  `pago_em` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comissões calculadas por atendimento concluído';

-- --------------------------------------------------------

--
-- Estrutura para tabela `despesas`
--

CREATE TABLE `despesas` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `nome` varchar(150) NOT NULL,
  `tipo` varchar(10) NOT NULL CHECK (`tipo` in ('fixa','variavel')),
  `categoria` varchar(20) NOT NULL CHECK (`categoria` in ('aluguel','energia','agua','internet','telefone','materiais','salarios','outros')),
  `valor_centavos` int(10) UNSIGNED NOT NULL,
  `vencimento` date DEFAULT NULL,
  `recorrencia` varchar(10) DEFAULT NULL CHECK (`recorrencia` in ('nenhuma','semanal','mensal','anual')),
  `status` varchar(10) NOT NULL DEFAULT 'pendente' CHECK (`status` in ('pendente','pago','atrasado')),
  `pago_em` date DEFAULT NULL,
  `forma_pagamento` varchar(20) DEFAULT NULL CHECK (`forma_pagamento` in ('dinheiro','debito','credito','pix','transferencia')),
  `observacoes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Despesas fixas e variáveis do estabelecimento';

-- --------------------------------------------------------

--
-- Estrutura para tabela `estabelecimentos`
--

CREATE TABLE `estabelecimentos` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `admin_id` char(36) NOT NULL COMMENT 'FK -> usuarios.id',
  `categoria_id` char(36) NOT NULL COMMENT 'FK -> categorias.id',
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `telefone` varchar(20) NOT NULL,
  `endereco` varchar(300) NOT NULL,
  `cep` varchar(10) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `estado` char(2) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `foto_capa_url` varchar(500) DEFAULT NULL,
  `avaliacao_media` decimal(3,2) NOT NULL DEFAULT 0.00,
  `total_avaliacoes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estabelecimentos cadastrados na plataforma';

--
-- Despejando dados para a tabela `estabelecimentos`
--

INSERT INTO `estabelecimentos` (`id`, `admin_id`, `categoria_id`, `nome`, `descricao`, `cnpj`, `telefone`, `endereco`, `cep`, `cidade`, `estado`, `latitude`, `longitude`, `foto_capa_url`, `avaliacao_media`, `total_avaliacoes`, `ativo`, `created_at`, `updated_at`, `deleted_at`) VALUES
('22222222-2222-2222-2222-222222222222', '11111111-1111-1111-1111-111111111111', '40183810-235d-11f1-b18f-020017083972', 'Jato Estilos Barbearia', NULL, NULL, '88999999999', 'Rua Exemplo, 123 - Centro', '63000-000', 'Juazeiro do Norte', 'CE', -7.21370000, -39.31530000, NULL, 0.00, 0, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `horarios_funcionamento`
--

CREATE TABLE `horarios_funcionamento` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `profissional_id` char(36) DEFAULT NULL COMMENT 'FK -> profissionais.id — NULL = horário do estabelecimento',
  `dia_semana` tinyint(4) NOT NULL COMMENT '0=Dom, 1=Seg, ..., 6=Sáb',
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `intervalo_minutos` int(10) UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Intervalo entre slots',
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `imagens`
--

CREATE TABLE `imagens` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `servico_id` char(36) DEFAULT NULL COMMENT 'FK -> servicos.id — NULL = imagem geral',
  `url` varchar(500) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ordem` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Galeria de imagens dos estabelecimentos e serviços';

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimentacoes_caixa`
--

CREATE TABLE `movimentacoes_caixa` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `caixa_id` char(36) NOT NULL COMMENT 'FK -> caixas.id',
  `agendamento_id` char(36) DEFAULT NULL COMMENT 'FK -> agendamentos.id — NULL se não ligado a atendimento',
  `tipo` varchar(20) NOT NULL CHECK (`tipo` in ('entrada','saida','sangria','suprimento')),
  `descricao` varchar(255) NOT NULL,
  `valor_centavos` int(10) UNSIGNED NOT NULL,
  `forma_pagamento` varchar(20) DEFAULT NULL CHECK (`forma_pagamento` in ('dinheiro','debito','credito','pix','voucher')),
  `operador_id` char(36) NOT NULL COMMENT 'FK -> usuarios.id',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transações registradas em um caixa aberto';

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `usuario_id` char(36) NOT NULL COMMENT 'FK -> usuarios.id',
  `titulo` varchar(200) NOT NULL,
  `corpo` text NOT NULL,
  `tipo` varchar(15) NOT NULL CHECK (`tipo` in ('push','email','sms','whatsapp')),
  `referencia_tipo` varchar(50) DEFAULT NULL COMMENT 'Ex: agendamento, caixa',
  `referencia_id` char(36) DEFAULT NULL COMMENT 'UUID do objeto referenciado',
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `enviada_em` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de notificações enviadas aos usuários';

-- --------------------------------------------------------

--
-- Estrutura para tabela `politicas_agendamento`
--

CREATE TABLE `politicas_agendamento` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `antecedencia_min_minutos` int(10) UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Antecedência mínima para agendar',
  `antecedencia_max_dias` int(10) UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Quantos dias no futuro pode agendar',
  `cancelamento_limite_horas` int(10) UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Prazo limite para cancelar sem taxa',
  `lembrete_horas_antes` int(10) UNSIGNED NOT NULL DEFAULT 24 COMMENT 'Horas antes para enviar lembrete',
  `exige_confirmacao_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = agendamentos online ficam pendentes',
  `retorno_inativo_dias` int(10) UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Dias sem visita para enviar convite',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações e políticas de agendamento por estabelecimento';

--
-- Despejando dados para a tabela `politicas_agendamento`
--

INSERT INTO `politicas_agendamento` (`id`, `estabelecimento_id`, `antecedencia_min_minutos`, `antecedencia_max_dias`, `cancelamento_limite_horas`, `lembrete_horas_antes`, `exige_confirmacao_admin`, `retorno_inativo_dias`, `updated_at`) VALUES
('33333333-3333-3333-3333-333333333333', '22222222-2222-2222-2222-222222222222', 60, 30, 2, 24, 0, 60, '2026-03-21 01:23:26');

-- --------------------------------------------------------

--
-- Estrutura para tabela `profissionais`
--

CREATE TABLE `profissionais` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `usuario_id` char(36) NOT NULL COMMENT 'FK -> usuarios.id',
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `cargo` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `foto_url` varchar(500) DEFAULT NULL,
  `comissao_percentual` decimal(5,2) DEFAULT NULL COMMENT 'Comissão padrão (%)',
  `comissao_tipo` varchar(20) NOT NULL DEFAULT 'percentual' CHECK (`comissao_tipo` in ('percentual','fixo','misto')),
  `data_contratacao` date DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profissionais vinculados a um estabelecimento';

-- --------------------------------------------------------

--
-- Estrutura para tabela `profissional_servicos`
--

CREATE TABLE `profissional_servicos` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `profissional_id` char(36) NOT NULL COMMENT 'FK -> profissionais.id',
  `servico_id` char(36) NOT NULL COMMENT 'FK -> servicos.id',
  `valor_custom_centavos` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = usa o preço padrão do serviço',
  `comissao_custom_percentual` decimal(5,2) DEFAULT NULL COMMENT 'NULL = usa a comissão padrão do profissional',
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relacionamento N:N entre profissionais e serviços, com preço e comissão específicos';

-- --------------------------------------------------------

--
-- Estrutura para tabela `servicos`
--

CREATE TABLE `servicos` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `duracao_minutos` int(10) UNSIGNED NOT NULL COMMENT 'Duração em minutos',
  `valor_centavos` int(10) UNSIGNED NOT NULL COMMENT 'Valor em centavos (ex: 3000 = R$30,00)',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de serviços oferecidos por um estabelecimento';

-- --------------------------------------------------------

--
-- Estrutura para tabela `tags_cliente`
--

CREATE TABLE `tags_cliente` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `estabelecimento_id` char(36) NOT NULL COMMENT 'FK -> estabelecimentos.id',
  `cliente_id` char(36) NOT NULL COMMENT 'FK -> clientes.id',
  `tag` varchar(50) NOT NULL,
  `cor_hex` char(7) DEFAULT NULL COMMENT 'Ex: #FF5733',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Etiquetas personalizadas aplicadas a clientes';

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(20) NOT NULL,
  `senha_hash` varchar(255) DEFAULT NULL COMMENT 'Hash bcrypt — NULL para login social',
  `perfil` varchar(20) NOT NULL DEFAULT 'cliente' CHECK (`perfil` in ('cliente','profissional','admin')),
  `foto_url` varchar(500) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Todos os usuários do sistema: clientes, profissionais e admins';

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `telefone`, `senha_hash`, `perfil`, `foto_url`, `data_nascimento`, `ativo`, `created_at`, `updated_at`, `deleted_at`) VALUES
('11111111-1111-1111-1111-111111111111', 'Usuário Teste', 'teste@teste.com.br', '88999999999', '$2a$12$hZeU0CHABzmjYhL5xWEfQ.jEBSxqDSagclL4H6upI07HZc9tJaGHe', 'admin', NULL, NULL, 1, '2026-03-21 01:23:26', '2026-03-21 01:23:26', NULL),
('e32e91e6-ec71-4d25-8051-d0c860a4a571', 'Jairo', 'jairo@teste.com', '88999999999', '$2a$12$nrwIMG7fE/jNuI2c6poc3eSTvmjZjdhVUgCTwk3mhytg.RnOd93qi', 'cliente', NULL, NULL, 1, '2026-03-19 03:33:03', '2026-03-19 03:33:03', NULL);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_agenda_dia`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_agenda_dia` (
`agendamento_id` char(36)
,`estabelecimento_id` char(36)
,`profissional_id` char(36)
,`profissional_nome` varchar(100)
,`data_inicio` datetime
,`data_fim` datetime
,`status` varchar(20)
,`origem` varchar(20)
,`cliente_nome` varchar(100)
,`cliente_telefone` varchar(20)
,`valor_total_centavos` int(10) unsigned
,`desconto_centavos` int(10) unsigned
,`forma_pagamento` varchar(20)
,`servicos_nomes` mediumtext
,`duracao_total_minutos` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_comissoes_periodo`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_comissoes_periodo` (
`estabelecimento_id` char(36)
,`profissional_id` char(36)
,`profissional_nome` varchar(100)
,`ano` int(5)
,`mes` int(3)
,`total_atendimentos` bigint(21)
,`total_base_centavos` decimal(32,0)
,`total_comissao_centavos` decimal(32,0)
,`total_pago_centavos` decimal(32,0)
,`total_pendente_centavos` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_ranking_clientes`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_ranking_clientes` (
`estabelecimento_id` char(36)
,`cliente_id` char(36)
,`nome` varchar(100)
,`telefone` varchar(20)
,`total_visitas` bigint(21)
,`total_gasto_centavos` decimal(32,0)
,`ultima_visita` datetime
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_ranking_servicos`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_ranking_servicos` (
`estabelecimento_id` char(36)
,`servico_id` char(36)
,`servico_nome` varchar(150)
,`total_realizacoes` bigint(21)
,`total_receita_centavos` decimal(32,0)
,`duracao_media_minutos` decimal(14,4)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_resumo_financeiro_diario`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_resumo_financeiro_diario` (
`estabelecimento_id` char(36)
,`data` date
,`total_entradas_centavos` decimal(32,0)
,`total_saidas_centavos` decimal(32,0)
,`saldo_centavos` decimal(33,0)
,`total_atendimentos_pagos` bigint(21)
);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agend_estab` (`estabelecimento_id`,`status`,`data_inicio`),
  ADD KEY `idx_agend_prof_data` (`profissional_id`,`data_inicio`),
  ADD KEY `idx_agend_cliente` (`cliente_id`,`data_inicio`),
  ADD KEY `idx_agend_data_inicio` (`data_inicio`),
  ADD KEY `idx_agend_status` (`status`);

--
-- Índices de tabela `agendamento_servicos`
--
ALTER TABLE `agendamento_servicos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_as_agendamento` (`agendamento_id`),
  ADD KEY `idx_as_servico` (`servico_id`);

--
-- Índices de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_aval_agendamento` (`agendamento_id`) COMMENT 'Uma avaliação por agendamento',
  ADD KEY `idx_aval_estab` (`estabelecimento_id`),
  ADD KEY `idx_aval_prof` (`profissional_id`),
  ADD KEY `idx_aval_nota` (`nota`),
  ADD KEY `fk_aval_cliente` (`cliente_id`);

--
-- Índices de tabela `bloqueios_agenda`
--
ALTER TABLE `bloqueios_agenda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bloq_prof_data` (`profissional_id`,`data_inicio`,`data_fim`);

--
-- Índices de tabela `caixas`
--
ALTER TABLE `caixas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caixas_estab_status` (`estabelecimento_id`,`status`),
  ADD KEY `idx_caixas_abertura` (`abertura_em`),
  ADD KEY `fk_caixas_operador` (`operador_id`);

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categorias_nome` (`nome`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clientes_estab` (`estabelecimento_id`),
  ADD KEY `idx_clientes_usuario` (`usuario_id`),
  ADD KEY `idx_clientes_telefone` (`estabelecimento_id`,`telefone`),
  ADD KEY `idx_clientes_nasc` (`data_nascimento`);

--
-- Índices de tabela `comissoes`
--
ALTER TABLE `comissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_comissao_agendamento` (`profissional_id`,`agendamento_id`),
  ADD KEY `idx_com_prof_status` (`profissional_id`,`status`,`created_at`),
  ADD KEY `idx_com_estab` (`estabelecimento_id`),
  ADD KEY `fk_com_agend` (`agendamento_id`);

--
-- Índices de tabela `despesas`
--
ALTER TABLE `despesas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_desp_estab_status` (`estabelecimento_id`,`status`,`vencimento`),
  ADD KEY `idx_desp_tipo` (`tipo`),
  ADD KEY `idx_desp_vencimento` (`vencimento`);

--
-- Índices de tabela `estabelecimentos`
--
ALTER TABLE `estabelecimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estab_admin` (`admin_id`),
  ADD KEY `idx_estab_categoria` (`categoria_id`),
  ADD KEY `idx_estab_ativo` (`ativo`),
  ADD KEY `idx_estab_geo` (`latitude`,`longitude`),
  ADD KEY `idx_estab_cidade` (`cidade`,`estado`);

--
-- Índices de tabela `horarios_funcionamento`
--
ALTER TABLE `horarios_funcionamento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hf_estab` (`estabelecimento_id`),
  ADD KEY `idx_hf_prof` (`profissional_id`),
  ADD KEY `idx_hf_dia` (`dia_semana`);

--
-- Índices de tabela `imagens`
--
ALTER TABLE `imagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_img_estab` (`estabelecimento_id`,`ordem`),
  ADD KEY `idx_img_servico` (`servico_id`);

--
-- Índices de tabela `movimentacoes_caixa`
--
ALTER TABLE `movimentacoes_caixa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mov_caixa` (`caixa_id`,`tipo`),
  ADD KEY `idx_mov_agendamento` (`agendamento_id`),
  ADD KEY `idx_mov_created` (`created_at`),
  ADD KEY `fk_mov_operador` (`operador_id`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_usuario` (`usuario_id`,`lida`),
  ADD KEY `idx_notif_referencia` (`referencia_tipo`,`referencia_id`);

--
-- Índices de tabela `politicas_agendamento`
--
ALTER TABLE `politicas_agendamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_politica_estab` (`estabelecimento_id`);

--
-- Índices de tabela `profissionais`
--
ALTER TABLE `profissionais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prof_usuario_estab` (`usuario_id`,`estabelecimento_id`),
  ADD KEY `idx_prof_estab` (`estabelecimento_id`),
  ADD KEY `idx_prof_ativo` (`ativo`);

--
-- Índices de tabela `profissional_servicos`
--
ALTER TABLE `profissional_servicos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prof_serv` (`profissional_id`,`servico_id`),
  ADD KEY `idx_ps_servico` (`servico_id`);

--
-- Índices de tabela `servicos`
--
ALTER TABLE `servicos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_servicos_estab` (`estabelecimento_id`),
  ADD KEY `idx_servicos_ativo` (`ativo`);

--
-- Índices de tabela `tags_cliente`
--
ALTER TABLE `tags_cliente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tags_cliente` (`cliente_id`),
  ADD KEY `idx_tags_estab` (`estabelecimento_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_perfil` (`perfil`),
  ADD KEY `idx_usuarios_ativo` (`ativo`);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_agenda_dia`
--
DROP TABLE IF EXISTS `vw_agenda_dia`;

CREATE ALGORITHM=UNDEFINED DEFINER=`jatoestilos`@`localhost` SQL SECURITY DEFINER VIEW `vw_agenda_dia`  AS SELECT `a`.`id` AS `agendamento_id`, `a`.`estabelecimento_id` AS `estabelecimento_id`, `a`.`profissional_id` AS `profissional_id`, `u`.`nome` AS `profissional_nome`, `a`.`data_inicio` AS `data_inicio`, `a`.`data_fim` AS `data_fim`, `a`.`status` AS `status`, `a`.`origem` AS `origem`, coalesce(`cl`.`nome`,`a`.`cliente_nome_avulso`,'Anônimo') AS `cliente_nome`, `cl`.`telefone` AS `cliente_telefone`, `a`.`valor_total_centavos` AS `valor_total_centavos`, `a`.`desconto_centavos` AS `desconto_centavos`, `a`.`forma_pagamento` AS `forma_pagamento`, group_concat(`sv`.`nome` order by `ags`.`ordem` ASC separator ', ') AS `servicos_nomes`, sum(`ags`.`duracao_minutos`) AS `duracao_total_minutos` FROM (((((`agendamentos` `a` join `profissionais` `pr` on(`pr`.`id` = `a`.`profissional_id`)) join `usuarios` `u` on(`u`.`id` = `pr`.`usuario_id`)) left join `clientes` `cl` on(`cl`.`id` = `a`.`cliente_id`)) left join `agendamento_servicos` `ags` on(`ags`.`agendamento_id` = `a`.`id`)) left join `servicos` `sv` on(`sv`.`id` = `ags`.`servico_id`)) GROUP BY `a`.`id`, `a`.`estabelecimento_id`, `a`.`profissional_id`, `u`.`nome`, `a`.`data_inicio`, `a`.`data_fim`, `a`.`status`, `a`.`origem`, `cl`.`nome`, `a`.`cliente_nome_avulso`, `cl`.`telefone`, `a`.`valor_total_centavos`, `a`.`desconto_centavos`, `a`.`forma_pagamento` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_comissoes_periodo`
--
DROP TABLE IF EXISTS `vw_comissoes_periodo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`jatoestilos`@`localhost` SQL SECURITY DEFINER VIEW `vw_comissoes_periodo`  AS SELECT `co`.`estabelecimento_id` AS `estabelecimento_id`, `co`.`profissional_id` AS `profissional_id`, `u`.`nome` AS `profissional_nome`, year(`co`.`created_at`) AS `ano`, month(`co`.`created_at`) AS `mes`, count(`co`.`id`) AS `total_atendimentos`, sum(`co`.`valor_base_centavos`) AS `total_base_centavos`, sum(`co`.`valor_comissao_centavos`) AS `total_comissao_centavos`, sum(case when `co`.`status` = 'pago' then `co`.`valor_comissao_centavos` else 0 end) AS `total_pago_centavos`, sum(case when `co`.`status` = 'pendente' then `co`.`valor_comissao_centavos` else 0 end) AS `total_pendente_centavos` FROM ((`comissoes` `co` join `profissionais` `pr` on(`pr`.`id` = `co`.`profissional_id`)) join `usuarios` `u` on(`u`.`id` = `pr`.`usuario_id`)) GROUP BY `co`.`estabelecimento_id`, `co`.`profissional_id`, `u`.`nome`, year(`co`.`created_at`), month(`co`.`created_at`) ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_ranking_clientes`
--
DROP TABLE IF EXISTS `vw_ranking_clientes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`jatoestilos`@`localhost` SQL SECURITY DEFINER VIEW `vw_ranking_clientes`  AS SELECT `cl`.`estabelecimento_id` AS `estabelecimento_id`, `cl`.`id` AS `cliente_id`, `cl`.`nome` AS `nome`, `cl`.`telefone` AS `telefone`, count(`a`.`id`) AS `total_visitas`, coalesce(sum(`a`.`valor_total_centavos`),0) AS `total_gasto_centavos`, max(`a`.`data_inicio`) AS `ultima_visita` FROM (`clientes` `cl` left join `agendamentos` `a` on(`a`.`cliente_id` = `cl`.`id` and `a`.`status` = 'concluido')) WHERE `cl`.`deleted_at` is null GROUP BY `cl`.`estabelecimento_id`, `cl`.`id`, `cl`.`nome`, `cl`.`telefone` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_ranking_servicos`
--
DROP TABLE IF EXISTS `vw_ranking_servicos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`jatoestilos`@`localhost` SQL SECURITY DEFINER VIEW `vw_ranking_servicos`  AS SELECT `sv`.`estabelecimento_id` AS `estabelecimento_id`, `sv`.`id` AS `servico_id`, `sv`.`nome` AS `servico_nome`, count(`ags`.`id`) AS `total_realizacoes`, sum(`ags`.`valor_cobrado_centavos`) AS `total_receita_centavos`, avg(`ags`.`duracao_minutos`) AS `duracao_media_minutos` FROM ((`agendamento_servicos` `ags` join `servicos` `sv` on(`sv`.`id` = `ags`.`servico_id`)) join `agendamentos` `a` on(`a`.`id` = `ags`.`agendamento_id`)) WHERE `a`.`status` = 'concluido' GROUP BY `sv`.`estabelecimento_id`, `sv`.`id`, `sv`.`nome` ORDER BY count(`ags`.`id`) DESC ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_resumo_financeiro_diario`
--
DROP TABLE IF EXISTS `vw_resumo_financeiro_diario`;

CREATE ALGORITHM=UNDEFINED DEFINER=`jatoestilos`@`localhost` SQL SECURITY DEFINER VIEW `vw_resumo_financeiro_diario`  AS SELECT `c`.`estabelecimento_id` AS `estabelecimento_id`, cast(`mc`.`created_at` as date) AS `data`, sum(case when `mc`.`tipo` = 'entrada' then `mc`.`valor_centavos` else 0 end) AS `total_entradas_centavos`, sum(case when `mc`.`tipo` in ('saida','sangria') then `mc`.`valor_centavos` else 0 end) AS `total_saidas_centavos`, sum(case when `mc`.`tipo` = 'entrada' then `mc`.`valor_centavos` else 0 end) - sum(case when `mc`.`tipo` in ('saida','sangria') then `mc`.`valor_centavos` else 0 end) AS `saldo_centavos`, count(case when `mc`.`agendamento_id` is not null then 1 end) AS `total_atendimentos_pagos` FROM (`movimentacoes_caixa` `mc` join `caixas` `c` on(`c`.`id` = `mc`.`caixa_id`)) GROUP BY `c`.`estabelecimento_id`, cast(`mc`.`created_at` as date) ;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD CONSTRAINT `fk_agend_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_agend_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_agend_prof` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `agendamento_servicos`
--
ALTER TABLE `agendamento_servicos`
  ADD CONSTRAINT `fk_as_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_as_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD CONSTRAINT `fk_aval_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_aval_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_aval_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_aval_prof` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `bloqueios_agenda`
--
ALTER TABLE `bloqueios_agenda`
  ADD CONSTRAINT `fk_bloq_prof` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `caixas`
--
ALTER TABLE `caixas`
  ADD CONSTRAINT `fk_caixas_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_caixas_operador` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_clientes_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_clientes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `comissoes`
--
ALTER TABLE `comissoes`
  ADD CONSTRAINT `fk_com_agend` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_com_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_com_prof` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `despesas`
--
ALTER TABLE `despesas`
  ADD CONSTRAINT `fk_desp_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `estabelecimentos`
--
ALTER TABLE `estabelecimentos`
  ADD CONSTRAINT `fk_estab_admin` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_estab_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `horarios_funcionamento`
--
ALTER TABLE `horarios_funcionamento`
  ADD CONSTRAINT `fk_hf_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hf_prof` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `imagens`
--
ALTER TABLE `imagens`
  ADD CONSTRAINT `fk_img_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_img_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `movimentacoes_caixa`
--
ALTER TABLE `movimentacoes_caixa`
  ADD CONSTRAINT `fk_mov_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mov_caixa` FOREIGN KEY (`caixa_id`) REFERENCES `caixas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mov_operador` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `politicas_agendamento`
--
ALTER TABLE `politicas_agendamento`
  ADD CONSTRAINT `fk_politica_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `profissionais`
--
ALTER TABLE `profissionais`
  ADD CONSTRAINT `fk_prof_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prof_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `profissional_servicos`
--
ALTER TABLE `profissional_servicos`
  ADD CONSTRAINT `fk_ps_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ps_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `servicos`
--
ALTER TABLE `servicos`
  ADD CONSTRAINT `fk_servicos_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `tags_cliente`
--
ALTER TABLE `tags_cliente`
  ADD CONSTRAINT `fk_tags_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tags_estab` FOREIGN KEY (`estabelecimento_id`) REFERENCES `estabelecimentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
