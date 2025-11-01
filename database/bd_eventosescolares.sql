-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 01/11/2025 às 22:00
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `bd_eventosescolares`
--
CREATE DATABASE IF NOT EXISTS `bd_eventosescolares` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `bd_eventosescolares`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `eventos`
--

CREATE TABLE `eventos` (
  `id_evento` int(11) NOT NULL,
  `nome_evento` varchar(150) NOT NULL,
  `descricao_evento` text NOT NULL,
  `data_evento` date NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `local` varchar(150) NOT NULL,
  `status_evento` enum('disponível','esgotado','expirado','cancelado') NOT NULL,
  `limite_evento` int(11) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `imagem_evento` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `eventos`
--

INSERT INTO `eventos` (`id_evento`, `nome_evento`, `descricao_evento`, `data_evento`, `id_usuario`, `local`, `status_evento`, `limite_evento`, `hora_inicio`, `hora_fim`, `imagem_evento`) VALUES
(7, 'GESTEC', 'A GESTEC (Gestão e Tecnologia) é uma semana temática da ETEC de Bragança Paulista, ocorrendo de 08 a 12 de setembro de 2025. Durante esse período, estudantes, professores, e parceiros se reúnem em atividades voltadas a tecnologia, inovação e conexões que transformam.', '2025-09-08', 6, 'ETEC de Bragança Paulista', 'expirado', 400, '13:15:00', '18:30:00', '68f324fae3ce2_1760765178.jpg'),
(9, 'IntegraTE', 'O IntegraTEC da ETEC de Bragança Paulista é um evento que funciona como uma vitrine para os projetos desenvolvidos pelos alunos e um ponto de encontro entre a escola, as empresas da região e a comunidade em geral.', '2025-10-18', 6, 'ETEC de Bragança Paulista', 'disponível', 400, '09:00:00', '12:00:00', '68f325583b219_1760765272.png'),
(12, 'Hopi', 'Venha se divertir na noite do terror!', '2025-10-28', 6, 'Hopi Hari', 'disponível', 246, '07:00:00', '22:00:00', '68f39ffd4a301_1760796669.webp'),
(13, 'Maratona Berlin', 'Corrida de Berlim', '2025-11-20', 6, 'Berlim', 'disponível', 500, '07:00:00', '22:00:00', '68f3a707deaa1_1760798471.png');

-- --------------------------------------------------------

--
-- Estrutura para tabela `inscricoes`
--

CREATE TABLE `inscricoes` (
  `id_inscricao` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `data_inscricao` date NOT NULL,
  `status` enum('inscrito','presente','ausente') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `inscricoes`
--

INSERT INTO `inscricoes` (`id_inscricao`, `id_usuario`, `id_evento`, `data_inscricao`, `status`) VALUES
(17, 8, 9, '2025-10-18', 'inscrito'),
(21, 8, 12, '2025-10-18', 'inscrito'),
(23, 8, 13, '2025-10-18', 'inscrito');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `data_cadastro` date NOT NULL,
  `cpf` varchar(15) NOT NULL,
  `tipo_usuario` enum('aluno','professor','administrador') NOT NULL,
  `status_usuario` varchar(100) NOT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `genero` enum('masculino','feminino','outro','nao_informar') DEFAULT 'nao_informar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nome`, `email`, `senha`, `data_cadastro`, `cpf`, `tipo_usuario`, `status_usuario`, `foto_perfil`, `genero`) VALUES
(6, 'Giulia Acsa', 'giuliaacsa14@gmail.com', '$2y$10$7hxHWQOrctjaWVBuZ0Teo.ptBk/sNZzzIOTrM4iie6KSeZZrrx.tC', '2025-09-25', '46971155890', 'administrador', 'ativo', '68f3a1252ed6f_1760796965.jpg', 'feminino'),
(8, 'Manuela', 'manumachado12@gmail.com', '$2y$10$/M1R03x457MagQiwiNLWqOL/YSunMLPrnHAlR9FQzFyVAdpScRtcG', '2025-09-30', '54103849894', 'aluno', 'ativo', '68e0324dd89a7_1759523405.jpg', 'nao_informar');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id_evento`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Índices de tabela `inscricoes`
--
ALTER TABLE `inscricoes`
  ADD PRIMARY KEY (`id_inscricao`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_evento` (`id_evento`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `inscricoes`
--
ALTER TABLE `inscricoes`
  MODIFY `id_inscricao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `eventos`
--
ALTER TABLE `eventos`
  ADD CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Restrições para tabelas `inscricoes`
--
ALTER TABLE `inscricoes`
  ADD CONSTRAINT `inscricoes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `inscricoes_ibfk_2` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id_evento`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
