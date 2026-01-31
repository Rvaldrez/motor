-- ============================================================================
-- CRIAR TABELA NEWSLETTER
-- ============================================================================
-- 
-- Esta tabela armazena o histórico de newsletters enviadas aos investidores
-- sobre novos veículos disponíveis no sistema MotorGo.
--
-- Autor: Sistema MotorGo
-- Data: Janeiro 2026
-- ============================================================================

CREATE TABLE IF NOT EXISTS newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do registro',
    usuario_id INT NOT NULL COMMENT 'ID do investidor que recebeu o email',
    email VARCHAR(255) NOT NULL COMMENT 'Email do destinatário',
    assunto VARCHAR(255) NOT NULL COMMENT 'Assunto do email enviado',
    status VARCHAR(50) NOT NULL COMMENT 'Status do envio: enviado, erro',
    veiculos_enviados INT DEFAULT 0 COMMENT 'Quantidade de veículos incluídos no email',
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora do envio',
    erro_mensagem TEXT NULL COMMENT 'Mensagem de erro caso o envio tenha falho',
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_data (data_envio),
    
    CONSTRAINT fk_newsletter_usuario 
        FOREIGN KEY (usuario_id) 
        REFERENCES usuarios(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de newsletters enviadas aos investidores';

-- ============================================================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ============================================================================

-- Índice composto para consultas por período e status
CREATE INDEX idx_data_status ON newsletter(data_envio, status);

-- Índice composto para consultas por usuário e data
CREATE INDEX idx_usuario_data ON newsletter(usuario_id, data_envio);

-- ============================================================================
-- COMENTÁRIOS E DOCUMENTAÇÃO
-- ============================================================================

/*
CAMPOS DA TABELA:
- id: Identificador único auto-incrementado
- usuario_id: Referência ao investidor na tabela usuarios
- email: Email do destinatário (redundante para histórico)
- assunto: Assunto do email enviado
- status: 'enviado' ou 'erro'
- veiculos_enviados: Contador de veículos incluídos no email
- data_envio: Timestamp do envio
- erro_mensagem: Detalhes do erro em caso de falha

CONSULTAS ÚTEIS:

-- Ver últimos 20 envios
SELECT * FROM newsletter ORDER BY data_envio DESC LIMIT 20;

-- Contar envios por dia
SELECT DATE(data_envio) as data, COUNT(*) as total, 
       SUM(CASE WHEN status='enviado' THEN 1 ELSE 0 END) as sucessos,
       SUM(CASE WHEN status='erro' THEN 1 ELSE 0 END) as falhas
FROM newsletter
GROUP BY DATE(data_envio)
ORDER BY data DESC;

-- Investidores mais ativos (que mais receberam newsletters)
SELECT usuario_id, email, COUNT(*) as total_recebidos
FROM newsletter
WHERE status = 'enviado'
GROUP BY usuario_id, email
ORDER BY total_recebidos DESC
LIMIT 10;

-- Performance do envio (últimos 7 dias)
SELECT 
    DATE(data_envio) as data,
    COUNT(*) as total,
    AVG(veiculos_enviados) as media_veiculos,
    SUM(CASE WHEN status='enviado' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN status='erro' THEN 1 ELSE 0 END) as erros
FROM newsletter
WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(data_envio)
ORDER BY data DESC;
*/
