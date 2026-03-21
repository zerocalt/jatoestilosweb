<?php
/**
 * Formata valor em centavos para Real (R$)
 */
function formatMoney($cents) {
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

/**
 * Formata data Y-M-D para D/M/Y
 */
function formatDate($date) {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

/**
 * Formata data e hora para D/M/Y H:i
 */
function formatDateTime($dt) {
    if (!$dt) return '-';
    return date('d/m/Y H:i', strtotime($dt));
}

/**
 * Sanitiza inputs
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Retorna o status formatado com badge
 */
function formatStatus($status) {
    $badges = [
        'pendente' => 'bg-warning',
        'confirmado' => 'bg-info',
        'em_atendimento' => 'bg-primary',
        'concluido' => 'bg-success',
        'cancelado' => 'bg-danger',
        'falta' => 'bg-dark'
    ];

    $labels = [
        'pendente' => 'Pendente',
        'confirmado' => 'Confirmado',
        'em_atendimento' => 'Em Atendimento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'falta' => 'Faltou'
    ];

    $badge = isset($badges[$status]) ? $badges[$status] : 'bg-secondary';
    $label = isset($labels[$status]) ? $labels[$status] : ucfirst($status);

    return "<span class=\"badge $badge\">$label</span>";
}
?>