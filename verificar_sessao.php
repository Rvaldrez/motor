<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

// 🔍 Verifica se existe usuário na sessão
$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_nome = $_SESSION['usuario_nome'] ?? null;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? null;

if ($usuario_id) {
    // ✅ Usuário encontrado na sessão
    echo json_encode([
        "success" => true,
        "usuario_id" => $usuario_id,
        "nome" => $usuario_nome,
        "tipo" => $usuario_tipo,
        "message" => "Usuário autenticado"
    ]);
} else {
    // ❌ Usuário não encontrado na sessão
    echo json_encode([
        "success" => false,
        "message" => "Usuário não autenticado"
    ]);
}
?>