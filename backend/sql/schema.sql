-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
DROP SCHEMA IF EXISTS `gestprod`;
-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `gestprod` DEFAULT CHARACTER SET utf8mb4 ;
USE `gestprod` ;

-- Desativa chaves para permitir o DROP de tabelas com relacionamentos
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- Table `usuario`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `usuario`;
CREATE TABLE `usuario` (
  `id_usuario` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(45) NOT NULL,
  `email` VARCHAR(45) NOT NULL UNIQUE,
  `senha_hash` CHAR(64) NULL,
  PRIMARY KEY (`id_usuario`))
ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `fornecedor`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `fornecedor`;
CREATE TABLE `fornecedor` (
  `id_fornecedor` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(45) NOT NULL,
  `cnpj` CHAR(14) NOT NULL UNIQUE,
  `contato` VARCHAR(45) NULL,
  `telefone` VARCHAR(20) NULL,
  `status` ENUM('ativo', 'inativo') DEFAULT 'ativo',
  PRIMARY KEY (`id_fornecedor`))
ENGINE = InnoDB;

-- -----------------------------------------------------
-- Table `produto`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `produto`;
CREATE TABLE `produto` (
  `id_produto` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(45) NOT NULL,
  `preco` DECIMAL(10,2) NOT NULL,
  `descricao` TEXT NULL,
  `categoria` VARCHAR(45) NULL,
  `estoque` INT DEFAULT 0,
  `status` ENUM('ativo', 'inativo') DEFAULT 'ativo',
  `id_fornecedor` INT NOT NULL,
  PRIMARY KEY (`id_produto`),
  INDEX `fk_produto_fornecedor_idx` (`id_fornecedor` ASC),
  CONSTRAINT `fk_produto_fornecedor`
    FOREIGN KEY (`id_fornecedor`)
    REFERENCES `fornecedor` (`id_fornecedor`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

-- Reativa as chaves após a criação
SET FOREIGN_KEY_CHECKS = 1;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
