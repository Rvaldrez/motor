<?php
$senha = "240316";  // 🔹 Substitua pela senha que deseja salvar
$hash = password_hash($senha, PASSWORD_DEFAULT);
echo "Novo hash da senha: " . $hash;
?>
