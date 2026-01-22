<?php
session_start();

// ✅ Encerra a sessão e limpa os dados
session_unset();
session_destroy();

// ✅ Redireciona para o domínio principal
header("Location: https://motorgo.com.br");
exit;
?>
