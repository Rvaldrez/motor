<?php
/**
 * Seção: Painel Principal (Overview)
 * Included by painel.php — $conn, $user, $tipo, $csrfToken are available.
 */
$userId = (int) $user['id'];

// ── Fetch counts and summary ──────────────────────────────────
$totalVeiculos = 0;
$totalPropostas = 0;
$propostas_pendentes = 0;
$propostas_aceitas = 0;
$veiculosRecentes = [];
$propostasRecentes = [];

if ($tipo === 'vendedor') {
    // Total de veículos do vendedor
    $stmt = $conn->prepare("SELECT COUNT(*) FROM veiculos WHERE usuario_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($totalVeiculos);
        $stmt->fetch();
        $stmt->close();
    }

    // Propostas recebidas nos veículos do vendedor
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($totalPropostas);
        $stmt->fetch();
        $stmt->close();
    }

    // Propostas pendentes (aguardando resposta)
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ? AND p.status IN ('aguardando', 'aguardando_vendedor', 'aguardando_comprador')
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($propostas_pendentes);
        $stmt->fetch();
        $stmt->close();
    }

    // Veículos recentes – usa fotos_veiculos para não depender da coluna foto_principal
    $stmt = $conn->prepare("
        SELECT v.id, v.marca, v.modelo, v.ano_fabrica, v.preco, v.status,
               fv.caminho_foto AS foto_principal
        FROM veiculos v
        LEFT JOIN fotos_veiculos fv ON fv.id = (
            SELECT id FROM fotos_veiculos
            WHERE veiculo_id = v.id
            ORDER BY IFNULL(ordem_exibicao, 0) ASC, id ASC
            LIMIT 1
        )
        WHERE v.usuario_id = ? ORDER BY v.data_cadastro DESC LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $veiculosRecentes[] = $row;
        }
        $stmt->close();
    }

    // Propostas recentes recebidas
    $stmt = $conn->prepare("
        SELECT p.id, p.valor, p.status, p.data_proposta,
               v.marca, v.modelo, v.ano_fabrica,
               u.nome AS investidor_nome
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        LEFT JOIN usuarios u ON u.id = p.usuario_id
        WHERE v.usuario_id = ?
        ORDER BY p.data_proposta DESC LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $propostasRecentes[] = $row;
        }
        $stmt->close();
    }

} elseif ($tipo === 'investidor') {
    // Total de propostas enviadas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM propostas WHERE usuario_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($totalPropostas);
        $stmt->fetch();
        $stmt->close();
    }

    // Propostas pendentes
    $stmt = $conn->prepare("SELECT COUNT(*) FROM propostas WHERE usuario_id = ? AND status IN ('aguardando', 'aguardando_vendedor', 'aguardando_comprador')");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($propostas_pendentes);
        $stmt->fetch();
        $stmt->close();
    }

    // Propostas aceitas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM propostas WHERE usuario_id = ? AND status = 'aceita'");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($propostas_aceitas);
        $stmt->fetch();
        $stmt->close();
    }

    // Veículos disponíveis (total na plataforma)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM veiculos WHERE status IN ('completo', 'disponivel') AND em_negociacao = 0");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($totalVeiculos);
        $stmt->fetch();
        $stmt->close();
    }

    // Propostas recentes enviadas
    $stmt = $conn->prepare("
        SELECT p.id, p.valor, p.status, p.data_proposta,
               v.marca, v.modelo, v.ano_fabrica,
               u.nome AS vendedor_nome
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = v.usuario_id
        WHERE p.usuario_id = ?
        ORDER BY p.data_proposta DESC LIMIT 5
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $propostasRecentes[] = $row;
        }
        $stmt->close();
    }

} elseif ($tipo === 'administrador') {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM veiculos");
    if ($stmt) { $stmt->execute(); $stmt->bind_result($totalVeiculos); $stmt->fetch(); $stmt->close(); }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM propostas");
    if ($stmt) { $stmt->execute(); $stmt->bind_result($totalPropostas); $stmt->fetch(); $stmt->close(); }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM propostas WHERE status IN ('aguardando', 'aguardando_vendedor', 'aguardando_comprador')");
    if ($stmt) { $stmt->execute(); $stmt->bind_result($propostas_pendentes); $stmt->fetch(); $stmt->close(); }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE status_confirmacao = 'pendente'");
    if ($stmt) { $stmt->execute(); $stmt->bind_result($propostas_aceitas); $stmt->fetch(); $stmt->close(); }

    // Últimas propostas
    $stmt = $conn->prepare("
        SELECT p.id, p.valor, p.status, p.data_proposta,
               v.marca, v.modelo,
               u.nome AS investidor_nome
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        INNER JOIN usuarios u ON u.id = p.usuario_id
        ORDER BY p.data_proposta DESC LIMIT 5
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $propostasRecentes[] = $row;
        }
        $stmt->close();
    }
}

// ── Chart data: proposal status distribution ─────────────────
$chartData = ['pendentes' => 0, 'aceitas' => 0, 'recusadas' => 0, 'negociando' => 0];
if ($tipo === 'vendedor') {
    $stmtChart = $conn->prepare("
        SELECT
            SUM(CASE WHEN p.status IN ('aguardando_vendedor','aguardando','aguardando_comprador') THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN p.status = 'aceita' THEN 1 ELSE 0 END) AS aceitas,
            SUM(CASE WHEN p.status = 'recusada' THEN 1 ELSE 0 END) AS recusadas,
            SUM(CASE WHEN p.status IN ('contraoferta','contraproposta','negociando') THEN 1 ELSE 0 END) AS negociando
        FROM propostas p
        INNER JOIN veiculos v ON v.id = p.veiculo_id
        WHERE v.usuario_id = ?
    ");
    if ($stmtChart) $stmtChart->bind_param('i', $userId);
} elseif ($tipo === 'investidor') {
    $stmtChart = $conn->prepare("
        SELECT
            SUM(CASE WHEN status IN ('aguardando_vendedor','aguardando','aguardando_comprador') THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN status = 'aceita' THEN 1 ELSE 0 END) AS aceitas,
            SUM(CASE WHEN status = 'recusada' THEN 1 ELSE 0 END) AS recusadas,
            SUM(CASE WHEN status IN ('contraoferta','contraproposta','negociando') THEN 1 ELSE 0 END) AS negociando
        FROM propostas
        WHERE usuario_id = ?
    ");
    if ($stmtChart) $stmtChart->bind_param('i', $userId);
} else {
    $stmtChart = $conn->prepare("
        SELECT
            SUM(CASE WHEN status IN ('aguardando_vendedor','aguardando','aguardando_comprador') THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN status = 'aceita' THEN 1 ELSE 0 END) AS aceitas,
            SUM(CASE WHEN status = 'recusada' THEN 1 ELSE 0 END) AS recusadas,
            SUM(CASE WHEN status IN ('contraoferta','contraproposta','negociando') THEN 1 ELSE 0 END) AS negociando
        FROM propostas
    ");
}
if ($stmtChart) {
    $stmtChart->execute();
    $chartRow = $stmtChart->get_result()->fetch_assoc();
    $stmtChart->close();
} else {
    $chartRow = null;
}
if ($chartRow) {
    $chartData['pendentes']  = (int) $chartRow['pendentes'];
    $chartData['aceitas']    = (int) $chartRow['aceitas'];
    $chartData['recusadas']  = (int) $chartRow['recusadas'];
    $chartData['negociando'] = (int) $chartRow['negociando'];
}
$showChart = ($chartData['pendentes'] + $chartData['aceitas'] + $chartData['recusadas'] + $chartData['negociando']) > 0;

// Status badge helper
function painel_statusBadge(string $status): string {
    $map = [
        'disponivel'          => ['#d1fae5','#065f46','Disponível'],
        'completo'            => ['#d1fae5','#065f46','Disponível'],   // legado
        'incompleto'          => ['#f3f4f6','#6b7280','Incompleto'],   // legado
        'aguardando'          => ['#fef3c7','#92400e','Aguardando'],
        'aguardando_vendedor' => ['#fef3c7','#92400e','Aguardando'],
        'aguardando_comprador'=> ['#fef3c7','#92400e','Aguardando'],
        'aceita'              => ['#d1fae5','#065f46','Aceita'],
        'recusada'            => ['#fee2e2','#991b1b','Recusada'],
        'cancelada'           => ['#f3f4f6','#6b7280','Cancelada'],
        'contraproposta'      => ['#ede9fe','#5b21b6','Contraproposta'],
        'vendido'             => ['#dbeafe','#1e40af','Vendido'],
        'em_negociacao' => ['#fef3c7','#92400e','Em Negociação'],
        'pausado'     => ['#f3f4f6','#6b7280','Pausado'],
        'pendente'    => ['#fef3c7','#92400e','Pendente'],
        'finalizada'  => ['#dbeafe','#1e40af','Finalizada'],
    ];
    $d = $map[$status] ?? ['#f3f4f6','#6b7280', ucfirst($status)];
    return '<span style="background:' . $d[0] . ';color:' . $d[1] . ';padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;">'
        . htmlspecialchars($d[2], ENT_QUOTES, 'UTF-8') . '</span>';
}
?>

<div class="painel-overview">

    <!-- Welcome banner -->
    <div class="overview-welcome">
        <div class="welcome-text">
            <h2>Olá, <?= htmlspecialchars(explode(' ', $user['nome'])[0], ENT_QUOTES, 'UTF-8') ?>! 👋</h2>
            <p>
                <?php if ($tipo === 'vendedor'): ?>
                    Acompanhe seus veículos e propostas recebidas.
                <?php elseif ($tipo === 'investidor'): ?>
                    Confira as oportunidades e o andamento de suas propostas.
                <?php else: ?>
                    Visão geral da plataforma MotorGo.
                <?php endif; ?>
            </p>
        </div>
        <div class="welcome-badge">
            <i class="fa-solid fa-<?= $tipo === 'vendedor' ? 'car-side' : ($tipo === 'investidor' ? 'chart-line' : 'shield-halved') ?>"></i>
            <span><?= $tipo === 'vendedor' ? 'Vendedor' : ($tipo === 'investidor' ? 'Investidor' : 'Administrador') ?></span>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="stats-cards-grid">
        <?php if ($tipo === 'vendedor'): ?>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(37,99,235,0.1);">
                <i class="fa-solid fa-car" style="color:#2563eb;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$totalVeiculos ?></span>
                <span class="stat-card-label">Meus Veículos</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(217,119,6,0.1);">
                <i class="fa-solid fa-file-invoice-dollar" style="color:#d97706;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$totalPropostas ?></span>
                <span class="stat-card-label">Propostas Recebidas</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(178,34,34,0.1);">
                <i class="fa-solid fa-clock" style="color:#B22222;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$propostas_pendentes ?></span>
                <span class="stat-card-label">Propostas Pendentes</span>
            </div>
        </div>

        <?php elseif ($tipo === 'investidor'): ?>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(22,163,74,0.1);">
                <i class="fa-solid fa-car" style="color:#16a34a;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$totalVeiculos ?></span>
                <span class="stat-card-label">Veículos Disponíveis</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(37,99,235,0.1);">
                <i class="fa-solid fa-paper-plane" style="color:#2563eb;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$totalPropostas ?></span>
                <span class="stat-card-label">Propostas Enviadas</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(217,119,6,0.1);">
                <i class="fa-solid fa-clock" style="color:#d97706;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$propostas_pendentes ?></span>
                <span class="stat-card-label">Aguardando Resposta</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(22,163,74,0.1);">
                <i class="fa-solid fa-circle-check" style="color:#16a34a;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$propostas_aceitas ?></span>
                <span class="stat-card-label">Propostas Aceitas</span>
            </div>
        </div>

        <?php else: // administrador ?>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(37,99,235,0.1);">
                <i class="fa-solid fa-car" style="color:#2563eb;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$totalVeiculos ?></span>
                <span class="stat-card-label">Total de Veículos</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(217,119,6,0.1);">
                <i class="fa-solid fa-file-invoice-dollar" style="color:#d97706;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$totalPropostas ?></span>
                <span class="stat-card-label">Total de Propostas</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(178,34,34,0.1);">
                <i class="fa-solid fa-clock" style="color:#B22222;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$propostas_pendentes ?></span>
                <span class="stat-card-label">Propostas Pendentes</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:rgba(234,179,8,0.1);">
                <i class="fa-solid fa-user-clock" style="color:#ca8a04;"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= (int)$propostas_aceitas ?></span>
                <span class="stat-card-label">Usuários Pendentes</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Proposal status chart -->
    <?php if ($showChart): ?>
    <div class="overview-chart-card">
        <div class="table-card-header">
            <h3><i class="fa-solid fa-chart-pie"></i> Distribuição das Propostas</h3>
        </div>
        <div class="chart-wrapper">
            <canvas id="propostasChart" width="260" height="260"></canvas>
            <div class="chart-legend">
                <?php if ($chartData['pendentes'] > 0): ?>
                <div class="chart-legend-item">
                    <span class="chart-legend-dot" style="background:#f59e0b;"></span>
                    <span>Pendentes: <strong><?= $chartData['pendentes'] ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if ($chartData['negociando'] > 0): ?>
                <div class="chart-legend-item">
                    <span class="chart-legend-dot" style="background:#8b5cf6;"></span>
                    <span>Em Negociação: <strong><?= $chartData['negociando'] ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if ($chartData['aceitas'] > 0): ?>
                <div class="chart-legend-item">
                    <span class="chart-legend-dot" style="background:#10b981;"></span>
                    <span>Aceitas: <strong><?= $chartData['aceitas'] ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if ($chartData['recusadas'] > 0): ?>
                <div class="chart-legend-item">
                    <span class="chart-legend-dot" style="background:#ef4444;"></span>
                    <span>Recusadas: <strong><?= $chartData['recusadas'] ?></strong></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    (function() {
        var ctx = document.getElementById('propostasChart');
        if (!ctx || typeof Chart === 'undefined') return;
        var labels = [], data = [], colors = [];
        <?php if ($chartData['pendentes'] > 0): ?>
        labels.push('Pendentes'); data.push(<?= $chartData['pendentes'] ?>); colors.push('#f59e0b');
        <?php endif; ?>
        <?php if ($chartData['negociando'] > 0): ?>
        labels.push('Em Negociação'); data.push(<?= $chartData['negociando'] ?>); colors.push('#8b5cf6');
        <?php endif; ?>
        <?php if ($chartData['aceitas'] > 0): ?>
        labels.push('Aceitas'); data.push(<?= $chartData['aceitas'] ?>); colors.push('#10b981');
        <?php endif; ?>
        <?php if ($chartData['recusadas'] > 0): ?>
        labels.push('Recusadas'); data.push(<?= $chartData['recusadas'] ?>); colors.push('#ef4444');
        <?php endif; ?>
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }]
            },
            options: {
                responsive: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c) { return ' ' + c.label + ': ' + c.raw; }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    })();
    </script>
    <?php endif; ?>

    <!-- Recent tables -->
    <div class="overview-tables">

        <?php if (!empty($veiculosRecentes)): ?>
        <div class="overview-table-card">
            <div class="table-card-header">
                <h3><i class="fa-solid fa-car-side"></i> Veículos Recentes</h3>
                <button class="btn-section-link" onclick="navegarSecao('veiculos')">Ver todos →</button>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Veículo</th>
                            <th>Ano</th>
                            <th>Preço</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($veiculosRecentes as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['marca'] . ' ' . $v['modelo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($v['ano_fabrica'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= formatMoney((float)$v['preco']) ?></td>
                            <td><?= painel_statusBadge($v['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($propostasRecentes)): ?>
        <div class="overview-table-card">
            <div class="table-card-header">
                <h3><i class="fa-solid fa-file-invoice-dollar"></i>
                    <?= $tipo === 'vendedor' ? 'Propostas Recebidas' : 'Minhas Propostas' ?>
                </h3>
                <button class="btn-section-link" onclick="navegarSecao('propostas')">Ver todas →</button>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Veículo</th>
                            <th><?= $tipo === 'vendedor' ? 'Investidor' : 'Vendedor' ?></th>
                            <th>Valor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($propostasRecentes as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['marca'] . ' ' . $p['modelo'] . ' ' . ($p['ano_fabrica'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p[$tipo === 'vendedor' ? 'investidor_nome' : 'vendedor_nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= formatMoney((float)$p['valor']) ?></td>
                            <td><?= painel_statusBadge($p['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($veiculosRecentes) && empty($propostasRecentes)): ?>
        <div class="empty-state-card">
            <div class="empty-icon"><i class="fa-solid fa-rocket"></i></div>
            <h3>Tudo pronto!</h3>
            <p>
                <?php if ($tipo === 'vendedor'): ?>
                    Comece cadastrando seu primeiro veículo para receber propostas.
                <?php else: ?>
                    Explore os veículos disponíveis e envie sua primeira proposta.
                <?php endif; ?>
            </p>
            <button class="btn-empty-action" onclick="navegarSecao('<?= $tipo === 'vendedor' ? 'veiculos' : 'oferta' ?>')">
                <?= $tipo === 'vendedor' ? 'Cadastrar Veículo' : 'Ver Veículos' ?>
            </button>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
.painel-overview { max-width: 1000px; }
.overview-welcome {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.welcome-text h2 { font-size: 1.375rem; margin-bottom: 0.25rem; }
.welcome-text p { color: var(--color-text-muted); font-size: 0.9rem; margin: 0; }
.welcome-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(178,34,34,0.08);
    color: var(--color-primary);
    font-size: 0.875rem;
    font-weight: 700;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-full);
}
.stats-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.stat-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-card-icon {
    width: 46px; height: 46px; border-radius: var(--radius-lg);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-card-icon i { font-size: 1.125rem; }
.stat-card-value { display: block; font-size: 1.75rem; font-weight: 800; color: var(--color-secondary); letter-spacing: -0.04em; line-height: 1.1; }
.stat-card-label { font-size: 0.8125rem; color: var(--color-text-muted); font-weight: 500; }
.overview-tables { display: flex; flex-direction: column; gap: 1.5rem; }
.overview-table-card, .empty-state-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.125rem 1.5rem;
    border-bottom: 1px solid var(--color-border);
}
.table-card-header h3 {
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}
.table-card-header h3 i { color: var(--color-primary); }
.btn-section-link {
    background: none; border: none; color: var(--color-primary); font-size: 0.8125rem;
    font-weight: 600; cursor: pointer; transition: var(--transition);
}
.btn-section-link:hover { color: var(--color-primary-dark); }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
    padding: 0.75rem 1.5rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    background: var(--color-bg);
    border-bottom: 1px solid var(--color-border);
}
.data-table td {
    padding: 0.875rem 1.5rem;
    font-size: 0.875rem;
    color: var(--color-text);
    border-bottom: 1px solid var(--color-border);
}
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #fafafa; }
.empty-state-card {
    padding: 3rem 2rem;
    text-align: center;
}
.empty-icon {
    width: 64px; height: 64px; background: rgba(178,34,34,0.08);
    border-radius: var(--radius-xl); display: flex; align-items: center;
    justify-content: center; margin: 0 auto 1rem;
}
.empty-icon i { font-size: 1.625rem; color: var(--color-primary); }
.empty-state-card h3 { font-size: 1.125rem; margin-bottom: 0.5rem; }
.empty-state-card p { font-size: 0.9rem; color: var(--color-text-muted); margin-bottom: 1.5rem; }
.btn-empty-action {
    padding: 0.625rem 1.5rem; background: var(--color-primary); color: #fff;
    border: none; border-radius: var(--radius-full); font-weight: 700; font-size: 0.875rem;
    cursor: pointer; transition: var(--transition);
}
.btn-empty-action:hover { background: var(--color-primary-dark); }
.overview-chart-card {
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
}
.chart-wrapper {
    display: flex;
    align-items: center;
    gap: 2rem;
    padding: 1.5rem;
    flex-wrap: wrap;
}
.chart-legend {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}
.chart-legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}
.chart-legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}
@media (max-width: 640px) {
    .overview-welcome { flex-direction: column; gap: 1rem; align-items: flex-start; }
    .stats-cards-grid { grid-template-columns: 1fr 1fr; }
    .chart-wrapper { justify-content: center; }
}
</style>
