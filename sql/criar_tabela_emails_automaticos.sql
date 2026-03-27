-- Tabela para rastrear emails automáticos enviados
CREATE TABLE IF NOT EXISTS `emails_automaticos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `tipo` VARCHAR(100) NOT NULL,
  `data_envio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_usuario_id` (`usuario_id`),
  INDEX `idx_tipo` (`tipo`),
  INDEX `idx_data_envio` (`data_envio`),
  CONSTRAINT `fk_emails_automaticos_usuario` 
    FOREIGN KEY (`usuario_id`) 
    REFERENCES `usuarios` (`id`) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
