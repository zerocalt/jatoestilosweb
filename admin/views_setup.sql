-- Database View Definitions for Jato Estilos

-- 1. Agenda Diária
CREATE OR REPLACE VIEW `vw_agenda_dia` AS
SELECT
    `a`.`id` AS `agendamento_id`,
    `a`.`estabelecimento_id` AS `estabelecimento_id`,
    `a`.`profissional_id` AS `profissional_id`,
    `u`.`nome` AS `profissional_nome`,
    `a`.`data_inicio` AS `data_inicio`,
    `a`.`data_fim` AS `data_fim`,
    `a`.`status` AS `status`,
    `a`.`origem` AS `origem`,
    COALESCE(`cl`.`nome`, `a`.`cliente_nome_avulso`, 'Anônimo') AS `cliente_nome`,
    `cl`.`telefone` AS `cliente_telefone`,
    `a`.`valor_total_centavos` AS `valor_total_centavos`,
    `a`.`desconto_centavos` AS `desconto_centavos`,
    `a`.`forma_pagamento` AS `forma_pagamento`,
    GROUP_CONCAT(`sv`.`nome` ORDER BY `ags`.`ordem` ASC SEPARATOR ', ') AS `servicos_nomes`,
    SUM(`ags`.`duracao_minutos`) AS `duracao_total_minutos`
FROM `agendamentos` `a`
JOIN `profissionais` `pr` ON `pr`.`id` = `a`.`profissional_id`
JOIN `usuarios` `u` ON `u`.`id` = `pr`.`usuario_id`
LEFT JOIN `clientes` `cl` ON `cl`.`id` = `a`.`cliente_id`
LEFT JOIN `agendamento_servicos` `ags` ON `ags`.`agendamento_id` = `a`.`id`
LEFT JOIN `servicos` `sv` ON `sv`.`id` = `ags`.`servico_id`
GROUP BY `a`.`id`;

-- 2. Comissões por Período
CREATE OR REPLACE VIEW `vw_comissoes_periodo` AS
SELECT
    `co`.`estabelecimento_id` AS `estabelecimento_id`,
    `co`.`profissional_id` AS `profissional_id`,
    `u`.`nome` AS `profissional_nome`,
    YEAR(`co`.`created_at`) AS `ano`,
    MONTH(`co`.`created_at`) AS `mes`,
    COUNT(`co`.`id`) AS `total_atendimentos`,
    SUM(`co`.`valor_base_centavos`) AS `total_base_centavos`,
    SUM(`co`.`valor_comissao_centavos`) AS `total_comissao_centavos`,
    SUM(CASE WHEN `co`.`status` = 'pago' THEN `co`.`valor_comissao_centavos` ELSE 0 END) AS `total_pago_centavos`,
    SUM(CASE WHEN `co`.`status` = 'pendente' THEN `co`.`valor_comissao_centavos` ELSE 0 END) AS `total_pendente_centavos`
FROM `comissoes` `co`
JOIN `profissionais` `pr` ON `pr`.`id` = `co`.`profissional_id`
JOIN `usuarios` `u` ON `u`.`id` = `pr`.`usuario_id`
GROUP BY `co`.`estabelecimento_id`, `co`.`profissional_id`, YEAR(`co`.`created_at`), MONTH(`co`.`created_at`);

-- 3. Ranking de Clientes
CREATE OR REPLACE VIEW `vw_ranking_clientes` AS
SELECT
    `cl`.`estabelecimento_id` AS `estabelecimento_id`,
    `cl`.`id` AS `cliente_id`,
    `cl`.`nome` AS `nome`,
    `cl`.`telefone` AS `telefone`,
    COUNT(`a`.`id`) AS `total_visitas`,
    COALESCE(SUM(`a`.`valor_total_centavos`), 0) AS `total_gasto_centavos`,
    MAX(`a`.`data_inicio`) AS `ultima_visita`
FROM `clientes` `cl`
LEFT JOIN `agendamentos` `a` ON `a`.`cliente_id` = `cl`.`id` AND `a`.`status` = 'concluido'
WHERE `cl`.`deleted_at` IS NULL
GROUP BY `cl`.`id`;

-- 4. Ranking de Serviços
CREATE OR REPLACE VIEW `vw_ranking_servicos` AS
SELECT
    `sv`.`estabelecimento_id` AS `estabelecimento_id`,
    `sv`.`id` AS `servico_id`,
    `sv`.`nome` AS `servico_nome`,
    COUNT(`ags`.`id`) AS `total_realizacoes`,
    SUM(`ags`.`valor_cobrado_centavos`) AS `total_receita_centavos`,
    AVG(`ags`.`duracao_minutos`) AS `duracao_media_minutos`
FROM `agendamento_servicos` `ags`
JOIN `servicos` `sv` ON `sv`.`id` = `ags`.`servico_id`
JOIN `agendamentos` `a` ON `a`.`id` = `ags`.`agendamento_id`
WHERE `a`.`status` = 'concluido'
GROUP BY `sv`.`id`;

-- 5. Resumo Financeiro Diário
CREATE OR REPLACE VIEW `vw_resumo_financeiro_diario` AS
SELECT
    `c`.`estabelecimento_id` AS `estabelecimento_id`,
    CAST(`mc`.`created_at` AS DATE) AS `data`,
    SUM(CASE WHEN `mc`.`tipo` = 'entrada' THEN `mc`.`valor_centavos` ELSE 0 END) AS `total_entradas_centavos`,
    SUM(CASE WHEN `mc`.`tipo` IN ('saida', 'sangria') THEN `mc`.`valor_centavos` ELSE 0 END) AS `total_saidas_centavos`,
    SUM(CASE WHEN `mc`.`tipo` = 'entrada' THEN `mc`.`valor_centavos` ELSE 0 END) - SUM(CASE WHEN `mc`.`tipo` IN ('saida', 'sangria') THEN `mc`.`valor_centavos` ELSE 0 END) AS `saldo_centavos`,
    COUNT(CASE WHEN `mc`.`agendamento_id` IS NOT NULL THEN 1 END) AS `total_atendimentos_pagos`
FROM `movimentacoes_caixa` `mc`
JOIN `caixas` `c` ON `c`.`id` = `mc`.`caixa_id`
GROUP BY `c`.`estabelecimento_id`, CAST(`mc`.`created_at` AS DATE);
