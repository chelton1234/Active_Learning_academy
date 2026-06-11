-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 11-Jun-2026 às 12:00
-- Versão do servidor: 10.4.28-MariaDB
-- versão do PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `Sistema_login`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `agendamentos_aulas`
--

CREATE TABLE `agendamentos_aulas` (
  `id` int(11) NOT NULL,
  `aluno_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED DEFAULT NULL,
  `data_hora` datetime NOT NULL,
  `materia` varchar(100) DEFAULT NULL,
  `conteudo_abordado` text DEFAULT NULL,
  `dificuldades_identificadas` text DEFAULT NULL,
  `observacoes_professor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('agendado','realizado','cancelado_aluno','cancelado_professor','pendente_professor') DEFAULT 'agendado',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `agendamentos_aulas`
--

INSERT INTO `agendamentos_aulas` (`id`, `aluno_id`, `professor_id`, `data_hora`, `materia`, `conteudo_abordado`, `dificuldades_identificadas`, `observacoes_professor`, `status`, `criado_em`) VALUES
(201, 119, 22, '2026-05-20 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 20/05/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(202, 119, 22, '2026-05-21 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 21/05/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(203, 119, 22, '2026-05-25 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 25/05/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(204, 119, 22, '2026-05-26 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 26/05/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(205, 119, 22, '2026-05-27 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 27/05/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(206, 119, 22, '2026-05-28 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 28/05/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(207, 119, 22, '2026-06-01 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 01/06/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(208, 119, 22, '2026-06-02 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 02/06/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(209, 119, 22, '2026-06-03 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 03/06/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(210, 119, 22, '2026-06-04 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 04/06/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(211, 119, 22, '2026-06-08 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 08/06/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(212, 119, 22, '2026-06-09 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 09/06/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(213, 119, 22, '2026-06-10 14:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 10/06/2026 14:00', 'cancelado_aluno', '2026-05-20 21:55:33'),
(214, 119, 22, '2026-06-11 14:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-05-20 21:55:33'),
(215, 119, 22, '2026-06-15 14:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-05-20 21:55:33'),
(216, 119, 22, '2026-06-16 14:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-05-20 21:55:33'),
(217, 119, 22, '2026-06-17 14:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-05-20 21:55:33'),
(218, 119, 22, '2026-06-18 14:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-05-20 21:55:33'),
(219, 128, 23, '2026-06-09 10:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 09/06/2026 10:00', 'cancelado_aluno', '2026-06-08 13:00:16'),
(220, 128, 23, '2026-06-10 10:00:00', NULL, NULL, NULL, 'âŒ Aula cancelada automaticamente por falta de registro. Data: 10/06/2026 10:00', 'cancelado_aluno', '2026-06-08 13:00:16'),
(221, 128, 23, '2026-06-11 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(222, 128, 23, '2026-06-16 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(223, 128, 23, '2026-06-17 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(224, 128, 23, '2026-06-18 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(225, 128, 23, '2026-06-23 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(226, 128, 23, '2026-06-24 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(227, 128, 23, '2026-06-25 10:00:00', NULL, NULL, NULL, NULL, 'cancelado_professor', '2026-06-08 13:00:16'),
(228, 128, 23, '2026-06-30 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(229, 128, 23, '2026-07-01 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(230, 128, 23, '2026-07-02 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(231, 128, 23, '2026-07-07 10:00:00', NULL, NULL, NULL, NULL, 'agendado', '2026-06-08 13:00:16'),
(232, 128, 23, '2026-06-10 10:00:00', NULL, NULL, NULL, 'aaaaaa', 'realizado', '2026-06-10 17:43:59');

-- --------------------------------------------------------

--
-- Estrutura da tabela `aulas_reposicao`
--

CREATE TABLE `aulas_reposicao` (
  `id` int(11) NOT NULL,
  `ficha_id` int(10) UNSIGNED NOT NULL,
  `aula_original_id` int(11) DEFAULT NULL,
  `data_geracao` date NOT NULL,
  `data_expiracao` date NOT NULL,
  `status` enum('disponivel','usado','expirado') DEFAULT 'disponivel',
  `data_uso` datetime DEFAULT NULL,
  `aula_reposicao_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `aula_itens`
--

CREATE TABLE `aula_itens` (
  `id` int(11) NOT NULL,
  `aula_id` int(11) NOT NULL,
  `disciplina` varchar(100) NOT NULL,
  `conteudo_abordado` text DEFAULT NULL,
  `dificuldades_identificadas` text DEFAULT NULL,
  `observacoes_professor` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `aula_itens`
--

INSERT INTO `aula_itens` (`id`, `aula_id`, `disciplina`, `conteudo_abordado`, `dificuldades_identificadas`, `observacoes_professor`, `created_at`) VALUES
(14, 232, 'FrancÃªs', 'testando ', 'Testando', 'Testando', '2026-06-10 17:43:59');

-- --------------------------------------------------------

--
-- Estrutura da tabela `creditos_reposicao`
--

CREATE TABLE `creditos_reposicao` (
  `id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `aula_original_id` int(11) NOT NULL,
  `status` enum('pendente','utilizado','expirado') DEFAULT 'pendente',
  `data_gerado` datetime DEFAULT current_timestamp(),
  `data_expiracao` date DEFAULT NULL,
  `utilizado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `fichas`
--

CREATE TABLE `fichas` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `idade` int(11) DEFAULT NULL,
  `classe` varchar(20) NOT NULL DEFAULT '',
  `sexo` enum('m','f') DEFAULT NULL,
  `localizacao` text DEFAULT NULL,
  `dificuldade` text DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `provincia` varchar(50) DEFAULT NULL,
  `pacote` varchar(50) DEFAULT NULL,
  `permite_finsemana` tinyint(1) DEFAULT 0,
  `dias_semana` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dias_semana`)),
  `horarios_json` longtext DEFAULT NULL,
  `contacto_encarregado` varchar(30) DEFAULT NULL,
  `escola` varchar(100) NOT NULL DEFAULT '',
  `nivel` enum('primary','secondary','cambridge') DEFAULT 'primary',
  `nivel_cambridge` varchar(50) DEFAULT NULL,
  `internet_casa` tinyint(1) DEFAULT NULL,
  `regime_presencial` tinyint(1) DEFAULT NULL,
  `regime_online` tinyint(1) DEFAULT NULL,
  `regime_domicilio` tinyint(1) DEFAULT 0,
  `regime_hibrido` tinyint(1) DEFAULT NULL,
  `data_submissao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `validada` tinyint(1) DEFAULT 0,
  `professor_atribuido` varchar(255) DEFAULT NULL,
  `professor_id` int(11) DEFAULT NULL,
  `aulas_agendadas` text DEFAULT NULL,
  `pacote_confirmado` varchar(50) DEFAULT NULL,
  `ficha_validada` tinyint(1) DEFAULT 0,
  `pacote_valido_ate` date DEFAULT NULL,
  `aulas_restantes` int(11) DEFAULT NULL,
  `valor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_mensal` decimal(10,2) DEFAULT 0.00,
  `pagamento_status` enum('pendente','pago') NOT NULL DEFAULT 'pendente',
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `data_pagamento` datetime DEFAULT NULL,
  `data_validade` date DEFAULT NULL,
  `recibo_pdf` varchar(255) DEFAULT NULL,
  `aulas_contratadas_mes` int(11) DEFAULT 0,
  `aulas_credito_reposicao` int(11) DEFAULT 0,
  `aulas_restantes_inicio_mes` int(11) DEFAULT 0,
  `ultima_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `fichas`
--

INSERT INTO `fichas` (`id`, `usuario_id`, `nome`, `email`, `idade`, `classe`, `sexo`, `localizacao`, `dificuldade`, `data_nascimento`, `provincia`, `pacote`, `permite_finsemana`, `dias_semana`, `horarios_json`, `contacto_encarregado`, `escola`, `nivel`, `nivel_cambridge`, `internet_casa`, `regime_presencial`, `regime_online`, `regime_domicilio`, `regime_hibrido`, `data_submissao`, `data_cadastro`, `data_atualizacao`, `validada`, `professor_atribuido`, `professor_id`, `aulas_agendadas`, `pacote_confirmado`, `ficha_validada`, `pacote_valido_ate`, `aulas_restantes`, `valor_total`, `valor_mensal`, `pagamento_status`, `valor_pago`, `data_pagamento`, `data_validade`, `recibo_pdf`, `aulas_contratadas_mes`, `aulas_credito_reposicao`, `aulas_restantes_inicio_mes`, `ultima_atualizacao`) VALUES
(119, 68, 'Amelia Nhabinde', 'amelia@gmail.com', NULL, '', NULL, 'Tchumene 2', '0', NULL, NULL, '4', 0, '{\"dias\":[\"Segunda\",\"Ter\\u00e7a\",\"Quarta\",\"Quinta\"]}', '{\"preferencial\":\"14h-15h30\"}', '858724687', '', 'cambridge', 'a_level', NULL, 1, NULL, 0, NULL, '2026-05-20 21:33:09', '2026-05-20 21:33:09', '2026-05-20 22:13:17', 0, 'Renato  Madeia', 5, NULL, NULL, 0, '2026-06-19', 18, 4000.00, 4000.00, 'pago', 4000.00, '2026-05-20 23:55:33', NULL, NULL, 18, 0, 0, '2026-05-20 22:13:17'),
(126, 76, 'Andre Croft', 'cheltonrui16@gmail.com', NULL, '', NULL, 'Ndavela', '0', NULL, NULL, '4', 0, '{\"dias\":[\"Segunda\",\"Ter\\u00e7a\",\"Sexta\",\"S\\u00e1bado\"]}', '{\"preferencial\":\"16h-17h30\"}', '858724687', '', 'cambridge', 'lower_secondary', NULL, 0, NULL, 1, NULL, '2026-05-24 21:04:42', '2026-05-24 21:04:42', '2026-05-24 21:04:42', 0, 'Renato  Madeia', 5, NULL, NULL, 0, NULL, NULL, 4500.00, 4000.00, 'pendente', NULL, NULL, NULL, NULL, 0, 0, 0, '2026-05-24 21:04:42'),
(128, 78, 'Bruno Vilanculo', 'bruno@gmail.com', NULL, '', NULL, 'Tchumene 2', '0', NULL, NULL, '3', 0, '{\"dias\":[\"Ter\\u00e7a\",\"Quarta\",\"Quinta\"]}', '{\"preferencial\":\"10h-11h30\"}', '858724687', '', 'cambridge', 'igcse', NULL, 1, NULL, 0, NULL, '2026-06-08 12:10:59', '2026-06-08 12:10:59', '2026-06-08 13:00:16', 0, 'Batista', 6, NULL, NULL, 0, '2026-07-08', 13, 3000.00, 3000.00, 'pago', 3000.00, '2026-06-08 15:00:16', NULL, NULL, 13, 0, 0, '2026-06-08 13:00:16');

-- --------------------------------------------------------

--
-- Estrutura da tabela `horarios_aulas`
--

CREATE TABLE `horarios_aulas` (
  `id` int(10) UNSIGNED NOT NULL,
  `ficha_id` int(10) UNSIGNED NOT NULL,
  `dia_semana` enum('segunda','terca','quarta','quinta','sexta','sabado','domingo') NOT NULL,
  `horario` varchar(20) NOT NULL COMMENT 'Formato: 8:00-9:30'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `horarios_aulas`
--

INSERT INTO `horarios_aulas` (`id`, `ficha_id`, `dia_semana`, `horario`) VALUES
(108, 119, 'segunda', '14:00'),
(109, 119, 'terca', '14:00'),
(110, 119, 'quarta', '14:00'),
(111, 119, 'quinta', '14:00'),
(132, 126, 'segunda', '16h-17h30'),
(133, 126, 'terca', '16h-17h30'),
(134, 126, 'sexta', '16h-17h30'),
(135, 126, 'sabado', '16h-17h30'),
(140, 128, 'terca', '10h-11h30'),
(141, 128, 'quarta', '10h-11h30'),
(142, 128, 'quinta', '10h-11h30');

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_usuario` enum('aluno','professor') NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `notificacoes`
--

INSERT INTO `notificacoes` (`id`, `usuario_id`, `tipo_usuario`, `titulo`, `mensagem`, `link`, `lida`, `data_criacao`) VALUES
(22, 22, 'professor', 'Aula cancelada pelo aluno', 'O aluno catu cancelou a aula do dia 20/03/2026 14:00. Motivo: Falha tÃ©cnica', 'dashboard_professor.php?mes=03&ano=2026', 0, '2026-03-19 19:00:32'),
(24, 22, 'professor', 'Aula cancelada pelo aluno', 'O aluno catu cancelou a aula do dia 03/04/2026 14:00. Motivo: Infelicidades', 'dashboard_professor.php?mes=04&ano=2026', 0, '2026-03-31 21:09:35'),
(27, 22, 'professor', 'Aula cancelada pelo aluno', 'O aluno Clesio cancelou a aula do dia 17/05/2026 17:30. Motivo: Teste de notificacoes ', 'dashboard_professor.php?mes=05&ano=2026', 0, '2026-04-22 21:20:54'),
(29, 67, 'aluno', '⚠️ Atenção: Defina os seus horários', 'O pagamento foi confirmado, mas não foram encontrados horários de aula. Por favor, complete o seu perfil com os dias e horários.', 'dashboard.php', 0, '2026-05-19 22:56:22'),
(30, 68, 'aluno', '✅ Aulas Agendadas!', 'Suas aulas foram agendadas com sucesso! Você tem 18 aulas neste mês.', 'dashboard.php', 0, '2026-05-20 21:55:33'),
(31, 78, 'aluno', '✅ Aulas Agendadas!', 'Suas aulas foram agendadas com sucesso! Você tem 13 aulas neste mês.', 'dashboard.php', 0, '2026-06-08 13:00:16'),
(32, 78, 'aluno', 'Aula cancelada pelo professor', 'O professor Batista cancelou a aula do dia 25/06/2026 10:00. Motivo: Emergência familiar. Um crédito de reposição foi gerado.', 'dashboard.php?mes=06&ano=2026', 0, '2026-06-09 09:10:29');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pagamentos`
--

CREATE TABLE `pagamentos` (
  `id` int(11) NOT NULL,
  `ficha_id` int(11) NOT NULL,
  `referencia` varchar(100) NOT NULL,
  `metodo` varchar(50) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `estado` enum('pendente','pago','falhado') DEFAULT 'pendente',
  `confirmado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `pagamentos`
--

INSERT INTO `pagamentos` (`id`, `ficha_id`, `referencia`, `metodo`, `valor`, `estado`, `confirmado_em`, `criado_em`, `atualizado_em`) VALUES
(137, 114, 'PAY202604198355', 'Emola', 6000.00, 'pago', '2026-04-19 00:15:19', '2026-04-18 22:15:19', '2026-04-18 22:15:19'),
(147, 118, 'PAY202605204554', 'Emola', 3000.00, 'pago', '2026-05-20 00:56:22', '2026-05-19 22:56:22', '2026-05-19 22:56:22'),
(153, 119, 'PAY202605205977', 'Emola', 4000.00, 'pago', '2026-05-20 23:55:33', '2026-05-20 21:55:33', '2026-05-20 21:55:33'),
(154, 128, 'PAY202606081981', 'Mpesa', 3000.00, 'pago', '2026-06-08 15:00:16', '2026-06-08 13:00:16', '2026-06-08 13:00:16');

-- --------------------------------------------------------

--
-- Estrutura da tabela `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expira_em`, `usado`, `criado_em`) VALUES
(32, 'amelia@gmail.com', 'ebad79d0700be8aab382781a49d124fff610466b64c7c97a14b0ea3d67ac4cc8', '2026-06-10 15:53:01', 0, '2026-06-10 12:53:01'),
(34, 'cheltonrui16@gmail.com', '919474a3dd9b406565b45da0f02eb1c4f72c5e3bd5412215f5d890c7741a4e54', '2026-06-10 20:31:17', 1, '2026-06-10 17:31:17'),
(35, 'bruno@gmail.com', '589e8b5dcbde014e008f709f2b81406951f344a318bf20e17706d3f3f7e868d6', '2026-06-10 20:44:45', 1, '2026-06-10 17:44:45');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pedidos_explicadores`
--

CREATE TABLE `pedidos_explicadores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `ficha_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contacto` varchar(20) NOT NULL,
  `localizacao` varchar(150) DEFAULT NULL,
  `nivel_cambridge` varchar(50) NOT NULL,
  `tipo_aula` enum('presencial','domicilio') NOT NULL,
  `pacote` enum('2dias','3dias','4dias') NOT NULL,
  `preco_base` int(11) NOT NULL,
  `preco_total` int(11) NOT NULL,
  `dias_semana` varchar(200) NOT NULL,
  `horario` varchar(30) NOT NULL,
  `observacoes` text DEFAULT NULL,
  `data_submissao` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pendente','aprovado','rejeitado','convertido') DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `pedidos_explicadores`
--

INSERT INTO `pedidos_explicadores` (`id`, `usuario_id`, `ficha_id`, `nome`, `email`, `contacto`, `localizacao`, `nivel_cambridge`, `tipo_aula`, `pacote`, `preco_base`, `preco_total`, `dias_semana`, `horario`, `observacoes`, `data_submissao`, `status`) VALUES
(2, 73, 123, 'chelton mucivane', 'mucivanechelton@gmail.com', '858724687', 'Tchumene 2', 'a_level', 'domicilio', '3dias', 3000, 3500, 'Segunda, TerÃ§a, Quarta', '8h-9h30', 'aaaaa', '2026-05-07 20:26:35', 'aprovado'),
(3, 68, 119, 'Amelia Nhabinde', 'amelia@gmail.com', '858724687', 'Tchumene 2', 'a_level', 'presencial', '4dias', 4000, 4000, 'Segunda, TerÃ§a, Quarta, Quinta', '14h-15h30', 'aaaaaaa', '2026-05-07 20:30:04', 'aprovado'),
(7, 67, 118, 'shelton Valerio', 'shelton@gmail.com', '858724687', 'Tchumene 2', 'primary_checkpoint', 'presencial', '3dias', 3000, 3000, 'TerÃ§a, Quarta, Quinta', '8h-9h30', 'aaaa', '2026-05-15 23:02:32', 'aprovado'),
(8, 70, 120, 'catu', 'catu@gmail.com', '858724687', 'patrice', 'lower_secondary', 'domicilio', '4dias', 4000, 4500, 'Segunda, Quarta, Sexta, SÃ¡bado', '18h-19h30', 'Need it urgently', '2026-05-22 22:11:23', 'aprovado'),
(9, 71, 121, 'AndreCroft', 'cheltonrui16@gmail.com', '878244687', 'Ndavela', 'lower_secondary', 'domicilio', '3dias', 3000, 3500, 'Segunda, TerÃ§a, Quarta', '14h-15h30', 'testar email', '2026-05-22 23:24:26', 'aprovado'),
(10, 72, 122, 'Chelton Rui', 'cheltonrui16@gmail.com', '858724687', 'Tchumene2', 'lower_secondary', 'domicilio', '3dias', 3000, 3500, 'TerÃ§a, Quarta, Quinta', '14h-15h30', 'Testar email', '2026-05-23 21:08:55', 'aprovado'),
(11, 75, 125, 'Chelton Rui', 'mucivanechelton@gmail.com', '858724687', 'Tchumene2', 'primary_checkpoint', 'domicilio', '4dias', 4000, 4500, 'Segunda, Quarta, Sexta, SÃ¡bado', '14h-15h30', 'fixing email problems', '2026-05-23 21:51:44', 'aprovado'),
(12, 74, 124, 'AndreCroft', 'cheltonrui16@gmail.com', '858724687', 'Tchumene2', 'lower_secondary', 'domicilio', '3dias', 3000, 3500, 'Segunda, TerÃ§a, Quarta', '12h-13h30', 'Fixing email problems', '2026-05-23 22:35:52', 'aprovado'),
(13, 76, 126, 'Andre Croft', 'cheltonrui16@gmail.com', '858724687', 'Ndavela', 'lower_secondary', 'domicilio', '4dias', 4000, 4500, 'Segunda, TerÃ§a, Sexta, SÃ¡bado', '16h-17h30', 'Fixing email issues', '2026-05-24 20:58:57', 'aprovado'),
(14, 77, 127, 'shelton Sitole', 'shelton@gmail.com', '858724687', 'Tchumene2', 'lower_secondary', 'domicilio', '4dias', 4000, 4500, 'TerÃ§a, Quarta, Sexta, SÃ¡bado', '18h-19h30', 'Fixing email issues', '2026-05-24 21:38:12', 'aprovado'),
(15, 78, 128, 'Bruno Vilanculo', 'bruno@gmail.com', '858724687', 'Tchumene 2', 'igcse', 'presencial', '3dias', 3000, 3000, 'TerÃ§a, Quarta, Quinta', '10h-11h30', 'Testando', '2026-06-08 12:09:08', 'aprovado');

-- --------------------------------------------------------

--
-- Estrutura da tabela `professores`
--

CREATE TABLE `professores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `especialidade` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `disponivel` enum('sim','nao') DEFAULT 'sim',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `professores`
--

INSERT INTO `professores` (`id`, `usuario_id`, `especialidade`, `telefone`, `disponivel`, `criado_em`) VALUES
(5, 22, 'ProgramaÃ§Ã£o', '878244687', 'sim', '2025-09-10 22:23:35'),
(6, 23, 'Redes', '858724687', 'sim', '2025-09-11 07:09:14');

-- --------------------------------------------------------

--
-- Estrutura da tabela `registros_aulas`
--

CREATE TABLE `registros_aulas` (
  `id` int(11) NOT NULL,
  `aluno_id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `data_aula` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time DEFAULT NULL,
  `materia` varchar(100) NOT NULL,
  `conteudo_abordado` text NOT NULL,
  `dificuldades_identificadas` text DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `status` enum('realizado','cancelado') DEFAULT 'realizado',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` varchar(20) DEFAULT 'aluno',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `ultimo_login` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo`, `data_criacao`, `ultimo_login`, `ativo`) VALUES
(7, 'Administrador', 'admin@gmail.com', '$2y$10$mSTp0.mvST07j6plTjYp8eglqHnJOCSvLJt2M7ZJXAGBfGiIEvV.i', 'admin', '2026-03-16 13:58:56', '2026-03-16 14:11:49', 1),
(22, 'Renato  Madeia', 'madeia@gmail.com', '$2y$10$E6jhbQJG6.XTTOOoQcXvAe9wwe0YhboxbAJXEfFDkvTQWFm0rnuqG', 'professor', '2026-03-16 13:58:56', '2026-03-16 15:00:40', 1),
(23, 'Batista', 'batista@gmail.com', '$2y$10$HLJObRxPcOIWp61.PzfhEuR.nrsTjoazSuP1NwEvghhvVoDNyJyqG', 'professor', '2026-03-16 13:58:56', '2026-03-16 16:03:43', 1),
(68, 'Amelia Nhabinde', 'amelia@gmail.com', '$2y$10$vGVFSm4WQSqSXPJD.xrwY./yd5Xe92F4xD7wAzGBlqhGSUwbO9gPe', 'aluno', '2026-05-20 23:33:09', NULL, 1),
(76, 'Andre Croft', 'cheltonrui16@gmail.com', '$2y$10$bl.3/2pz/I6.kCYrhthFLOcpE9cRh1ANWIyrjAu7IOUGyWcfRFK3m', 'aluno', '2026-05-24 23:04:42', NULL, 1),
(78, 'Bruno Vilanculo', 'bruno@gmail.com', '$2y$10$etCoKCGwT7kB1X/CCTfs2O.FYez/8b47pZtzs7vzmL2Un.jDqpmTi', 'aluno', '2026-06-08 14:10:58', NULL, 1);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `agendamentos_aulas`
--
ALTER TABLE `agendamentos_aulas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_aluno` (`aluno_id`),
  ADD KEY `fk_agendamentos_professor` (`professor_id`);

--
-- Índices para tabela `aulas_reposicao`
--
ALTER TABLE `aulas_reposicao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ficha_id` (`ficha_id`),
  ADD KEY `idx_aula_original` (`aula_original_id`),
  ADD KEY `idx_expiracao` (`data_expiracao`,`status`);

--
-- Índices para tabela `aula_itens`
--
ALTER TABLE `aula_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aula_id` (`aula_id`);

--
-- Índices para tabela `creditos_reposicao`
--
ALTER TABLE `creditos_reposicao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aluno` (`aluno_id`),
  ADD KEY `idx_professor` (`professor_id`),
  ADD KEY `aula_original_id` (`aula_original_id`);

--
-- Índices para tabela `fichas`
--
ALTER TABLE `fichas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_pagamento_status` (`pagamento_status`);

--
-- Índices para tabela `horarios_aulas`
--
ALTER TABLE `horarios_aulas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dia_ficha` (`ficha_id`,`dia_semana`),
  ADD KEY `idx_ficha_id` (`ficha_id`),
  ADD KEY `idx_dia_horario` (`dia_semana`,`horario`);

--
-- Índices para tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`,`tipo_usuario`,`lida`),
  ADD KEY `idx_data` (`data_criacao`);

--
-- Índices para tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referencia` (`referencia`),
  ADD KEY `ficha_id` (`ficha_id`);

--
-- Índices para tabela `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `pedidos_explicadores`
--
ALTER TABLE `pedidos_explicadores`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `professores`
--
ALTER TABLE `professores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices para tabela `registros_aulas`
--
ALTER TABLE `registros_aulas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `aluno_id` (`aluno_id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Índices para tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `agendamentos_aulas`
--
ALTER TABLE `agendamentos_aulas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=233;

--
-- AUTO_INCREMENT de tabela `aulas_reposicao`
--
ALTER TABLE `aulas_reposicao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `aula_itens`
--
ALTER TABLE `aula_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `creditos_reposicao`
--
ALTER TABLE `creditos_reposicao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fichas`
--
ALTER TABLE `fichas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT de tabela `horarios_aulas`
--
ALTER TABLE `horarios_aulas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=143;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT de tabela `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `pedidos_explicadores`
--
ALTER TABLE `pedidos_explicadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `professores`
--
ALTER TABLE `professores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `registros_aulas`
--
ALTER TABLE `registros_aulas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `agendamentos_aulas`
--
ALTER TABLE `agendamentos_aulas`
  ADD CONSTRAINT `fk_agendamentos_professor` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `aulas_reposicao`
--
ALTER TABLE `aulas_reposicao`
  ADD CONSTRAINT `fk_aulas_reposicao_aula_original` FOREIGN KEY (`aula_original_id`) REFERENCES `agendamentos_aulas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_aulas_reposicao_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `aula_itens`
--
ALTER TABLE `aula_itens`
  ADD CONSTRAINT `aula_itens_ibfk_1` FOREIGN KEY (`aula_id`) REFERENCES `agendamentos_aulas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `creditos_reposicao`
--
ALTER TABLE `creditos_reposicao`
  ADD CONSTRAINT `creditos_reposicao_ibfk_1` FOREIGN KEY (`aula_original_id`) REFERENCES `agendamentos_aulas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `fichas`
--
ALTER TABLE `fichas`
  ADD CONSTRAINT `fichas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `horarios_aulas`
--
ALTER TABLE `horarios_aulas`
  ADD CONSTRAINT `horarios_aulas_ibfk_1` FOREIGN KEY (`ficha_id`) REFERENCES `fichas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `professores`
--
ALTER TABLE `professores`
  ADD CONSTRAINT `professores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `registros_aulas`
--
ALTER TABLE `registros_aulas`
  ADD CONSTRAINT `registros_aulas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `fichas` (`id`),
  ADD CONSTRAINT `registros_aulas_ibfk_2` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
